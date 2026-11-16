<?php
/**
 * REST-клиент ЮKassa API v3.
 *
 * @link https://yookassa.ru/developers/api
 */

namespace diCore\Payment\YooKassa;

class MerchantApi
{
    const ERROR_INVALID_PAYMENT_ID = 'Invalid payment id';
    const API_URL = 'https://api.yookassa.ru/v3/';

    /** Зависший шлюз не должен держать воркер FPM */
    const CONNECT_TIMEOUT_SEC = 5;
    // ЮKassa сама отвечает 500, если не уложилась в 30 секунд
    const TIMEOUT_SEC = 35;

    /** Предел ЮKassa для `Idempotence-Key` */
    const IDEMPOTENCE_KEY_MAX_LEN = 64;

    /** @var string|null доп. CA-бандл; null – системное хранилище */
    private $caBundlePath = null;

    private $shopId;
    private $secretKey;

    /** @var int TIMEOUT_SEC или короче – для запроса, без которого можно обойтись */
    private $timeoutSec = self::TIMEOUT_SEC;

    private $error;
    private $httpCode;
    private $response;

    public function __construct($shopId, $secretKey)
    {
        $this->shopId = (string) $shopId;
        $this->secretKey = (string) $secretKey;
    }

    public function setCaBundle($path)
    {
        $this->caBundlePath = $path !== null && $path !== '' ? (string) $path : null;

        return $this;
    }

    public function getCaBundle()
    {
        return $this->caBundlePath;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * ЮKassa такого платежа не знает (404) или id не её формата – повтор
     * запроса ничего не изменит. Обрыв связи (код 0) сюда не относится: его
     * надо повторить.
     */
    public function isUnknownPayment(): bool
    {
        return $this->httpCode === 404 ||
            $this->error === self::ERROR_INVALID_PAYMENT_ID;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setTimeout(int $sec)
    {
        $this->timeoutSec = max(1, $sec);

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeoutSec;
    }

    /**
     * @param string $idempotenceKey повтор с тем же ключом в течение 24 часов
     *   возвращает тот же платёж, а не создаёт второй
     */
    public function createPayment(array $body, string $idempotenceKey): ?array
    {
        if (
            $idempotenceKey === '' ||
            strlen($idempotenceKey) > static::IDEMPOTENCE_KEY_MAX_LEN
        ) {
            $this->resetState();
            $this->error = "Invalid Idempotence-Key: $idempotenceKey";

            return null;
        }

        return $this->request('POST', 'payments', $body, $idempotenceKey);
    }

    public function getPayment(string $paymentId): ?array
    {
        // id уходит в путь запроса: только формат ЮKassa, без «../» и запросов
        if (!preg_match('/^[0-9a-f-]{8,64}\z/i', $paymentId)) {
            $this->resetState();
            $this->error = self::ERROR_INVALID_PAYMENT_ID;

            return null;
        }

        return $this->request('GET', "payments/$paymentId");
    }

    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotenceKey = null
    ): ?array {
        $this->resetState();

        $headers = ['Content-Type: application/json'];

        if ($idempotenceKey !== null) {
            $headers[] = "Idempotence-Key: $idempotenceKey";
        }

        [$code, $out, $transportError] = $this->send(
            $method,
            static::API_URL . $path,
            $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE),
            $headers
        );

        $this->httpCode = $code;
        $this->response = $out;

        if ($out === false) {
            $this->error = 'cURL error: ' . ($transportError ?: 'unknown');

            return null;
        }

        return $this->handleResponse((string) $out);
    }

    /**
     * Транспорт отдельно от разбора ответа – чтобы тест подменил сеть.
     *
     * @return array{0: int, 1: string|false, 2: string} код, тело, ошибка
     */
    protected function send(
        string $method,
        string $url,
        ?string $body,
        array $headers
    ): array {
        $curl = curl_init();

        if (!$curl) {
            return [0, false, 'unable to init cURL'];
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // Канал несёт секретный ключ и возвращает адрес, куда отправим
        // плательщика: без проверки TLS его подменит любой посредник
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "$this->shopId:$this->secretKey");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::CONNECT_TIMEOUT_SEC);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeoutSec);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        if ($this->caBundlePath !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->caBundlePath);
        }

        $out = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = $out === false ? curl_error($curl) : '';

        curl_close($curl);

        return [$code, $out, $error];
    }

    /**
     * Успех – только 200 с объектом, у которого есть `id`. Ошибка ЮKassa
     * приходит телом `{"type": "error", "code", "description", "parameter"}`;
     * 500 значит «исход неизвестен», а не «не создан».
     */
    protected function handleResponse(string $out): ?array
    {
        $json = json_decode($out, true);

        if (!is_array($json)) {
            $this->error =
                'Invalid JSON from YooKassa (HTTP ' .
                (string) $this->httpCode .
                '): ' .
                Helper::sanitizeForLog($out);

            return null;
        }

        if (
            $this->httpCode !== 200 ||
            ($json['type'] ?? null) === 'error' ||
            empty($json['id'])
        ) {
            $this->error =
                'YooKassa refused the request (HTTP ' .
                (string) $this->httpCode .
                '): ' .
                Helper::sanitizeForLog(
                    trim(
                        ($json['code'] ?? '') .
                            ' ' .
                            ($json['description'] ?? '') .
                            (isset($json['parameter'])
                                ? ' [' . $json['parameter'] . ']'
                                : '')
                    ) ?:
                    $out
                );

            return null;
        }

        return $json;
    }

    /**
     * Экземпляр кешируется хелпером: всё, что описывает ответ, сбрасывается до
     * запроса, иначе значение прошлого вызова достанется чужому платежу
     */
    private function resetState(): void
    {
        $this->error = '';
        $this->httpCode = null;
        $this->response = null;
    }
}
