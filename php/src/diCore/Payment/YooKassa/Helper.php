<?php
/**
 * ЮKassa по API v3: платёж с редиректом, уведомления, статус по запросу.
 *
 * @link https://yookassa.ru/developers/api
 */

namespace diCore\Payment\YooKassa;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Helper\Phone;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;
use diCore\Payment\Yandex\Vendor;

class Helper extends BaseHelper
{
    const system = System::yandex_kassa;

    const STATUS_PENDING = 'pending';
    const STATUS_WAITING_FOR_CAPTURE = 'waiting_for_capture';
    const STATUS_SUCCEEDED = 'succeeded';
    const STATUS_CANCELED = 'canceled';

    /** Ключ `metadata`, по которому платёж находит свой черновик */
    const METADATA_DRAFT_ID = 'draft_id';

    const DESCRIPTION_MAX_LEN = 128;

    const PAYMENT_URL_MAX_LEN = 1024;

    /**
     * Чек 54-ФЗ через ЮKassa. Выключен по умолчанию: проект со своей кассой
     * получил бы второй чек на ту же покупку. Включивший – проверяет константы
     * RECEIPT_* под свой договор.
     */
    const SEND_RECEIPT = false;

    /** 1 – без НДС; справочник vat_code ЮKassa */
    const RECEIPT_VAT_CODE = 1;
    /** null – не передавать: у магазина одна система налогообложения */
    const RECEIPT_TAX_SYSTEM_CODE = null;
    const RECEIPT_PAYMENT_SUBJECT = 'service';
    const RECEIPT_PAYMENT_MODE = 'full_payment';

    /**
     * Адреса, с которых ЮKassa шлёт уведомления. Подписи у уведомления нет,
     * поэтому решение принимается по ответу API, а этот список – лишь сигнал.
     *
     * @link https://yookassa.ru/developers/using-api/webhooks
     */
    const NOTIFICATION_NETWORKS = [
        '185.71.76.0/27',
        '185.71.77.0/27',
        '77.75.153.0/25',
        '77.75.156.11/32',
        '77.75.156.35/32',
        '77.75.154.128/25',
        '2a02:5180::/32',
    ];

    /** @var MerchantApi */
    protected $api;

    /** @var array|null разобранное тело уведомления */
    protected $notification;

    protected function getApi(): MerchantApi
    {
        if (!$this->api) {
            $this->api = $this->createApi();
        }

        return $this->api;
    }

    /** Единственное место сборки клиента – подменяется в проекте и тестах */
    protected function createApi(): MerchantApi
    {
        $api = new MerchantApi(static::getLogin(), static::getPassword());
        $api->setCaBundle(static::getCaBundlePath());

        return $api;
    }

    /** api.yookassa.ru подписан публичным УЦ, доп. бандл не нужен */
    protected static function getCaBundlePath()
    {
        return null;
    }

    public function getApiError(): string
    {
        return (string) $this->getApi()->getError();
    }

    public function isUnknownPayment(): bool
    {
        return $this->getApi()->isUnknownPayment();
    }

    /** HTTP-код последнего ответа; 0 – запроса не было или он не дошёл */
    public function getApiHttpCode(): int
    {
        return (int) $this->getApi()->getHttpCode();
    }

    /**
     * Создаёт платёж и возвращает адрес, куда отправить плательщика.
     *
     * @throws \Exception шлюз отказал или вернул адрес, которому нельзя верить
     */
    public function getFormUri(Draft $draft, array $opts = []): string
    {
        $body = static::paymentBody($draft, $opts);

        // Черновик заводится на каждую попытку: повтор запроса с тем же ключом
        // вернёт тот же платёж, а не оставит второй неоплаченный
        $payment = $this->getApi()->createPayment(
            $body,
            static::idempotenceKey($draft)
        );

        static::log("payments:\n" . static::sanitizeForLog(print_r($body, true)));
        static::log(
            'Response: ' . static::sanitizeForLog($this->getApi()->getResponse())
        );

        if ($payment === null) {
            throw new \Exception(
                'YooKassa payment error: ' . $this->getApi()->getError()
            );
        }

        $url = $payment['confirmation']['confirmation_url'] ?? null;

        if (!static::isPaymentUrl($url)) {
            throw new \Exception(
                'YooKassa returned no usable confirmation URL: ' .
                    static::sanitizeForLog($this->getApi()->getResponse())
            );
        }

        try {
            $this->afterPaymentCreated($draft, (string) $payment['id']);
        } catch (\Throwable $e) {
            static::log("afterPaymentCreated failed: {$e->getMessage()}");
        }

        return $url;
    }

    public static function idempotenceKey(Draft $draft): string
    {
        return "draft-{$draft->getId()}";
    }

    /**
     * Тело `POST /v3/payments`. Чистая функция: правила тела проверяются
     * тестом без сети.
     *
     * @param array $opts returnUrl, description, customerEmail, customerPhone
     */
    public static function paymentBody(Draft $draft, array $opts = []): array
    {
        $opts = extend(
            [
                'returnUrl' => '',
                'description' => '',
                'customerEmail' => '',
                'customerPhone' => '',
            ],
            $opts
        );

        $body = [
            'amount' => static::amount($draft->getAmount()),
            // Одностадийная схема: холд нам ни к чему, его никто не подтвердит
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => (string) $opts['returnUrl'],
            ],
            'metadata' => [
                static::METADATA_DRAFT_ID => (string) $draft->getId(),
            ],
        ];

        $description = trim((string) $opts['description']);

        if ($description !== '') {
            $body['description'] = mb_substr(
                $description,
                0,
                static::DESCRIPTION_MAX_LEN
            );
        }

        $type = Vendor::apiType($draft->getVendor());

        // Без способа ЮKassa покажет плательщику все, что включены в магазине
        if ($type !== null) {
            $body['payment_method_data'] = ['type' => $type];
        }

        $receipt = static::SEND_RECEIPT
            ? static::receiptFor($draft, ['description' => $description] + $opts)
            : null;

        if ($receipt) {
            $body['receipt'] = $receipt;
        }

        return $body;
    }

    /** Строка с двумя знаками: число 543.2000000001 ЮKassa отвергнет */
    public static function amount($value): array
    {
        return [
            'value' => number_format(round((float) $value, 2), 2, '.', ''),
            'currency' => 'RUB',
        ];
    }

    /**
     * Чек из одной позиции на сумму черновика. Переопределяется проектом с
     * корзиной; null – чек не передавать.
     */
    protected static function receiptFor(Draft $draft, array $opts): ?array
    {
        $customer = array_filter([
            'email' => (string) ($opts['customerEmail'] ?? ''),
            // E.164 без плюса; «8 (900)…» цифрами вышел бы 8900… – чек отклонят
            'phone' => ltrim(Phone::e164((string) ($opts['customerPhone'] ?? '')), '+'),
        ]);

        // Без контакта ЮKassa отклонит чек, а с ним и платёж
        if (!$customer) {
            return null;
        }

        $receipt = [
            'customer' => $customer,
            'items' => [
                [
                    'description' => mb_substr(
                        (string) ($opts['description'] ?: 'Payment'),
                        0,
                        static::DESCRIPTION_MAX_LEN
                    ),
                    'quantity' => '1.00',
                    'amount' => static::amount($draft->getAmount()),
                    'vat_code' => static::RECEIPT_VAT_CODE,
                    'payment_subject' => static::RECEIPT_PAYMENT_SUBJECT,
                    'payment_mode' => static::RECEIPT_PAYMENT_MODE,
                ],
            ],
        ];

        if (static::RECEIPT_TAX_SYSTEM_CODE !== null) {
            $receipt['tax_system_code'] = static::RECEIPT_TAX_SYSTEM_CODE;
        }

        return $receipt;
    }

    /**
     * Вызывается с id платежа ЮKassa сразу после создания. Проект сохраняет
     * его, чтобы на возврате плательщика спросить статус. Исключение
     * проглатывается вызывающим: оплату это не ломает.
     */
    protected function afterPaymentCreated(Draft $draft, string $paymentId)
    {
        return $this;
    }

    /**
     * Этот адрес и есть место, куда уйдут деньги: «любой https от шлюза» мало,
     * подменённый ответ или страница посредника платёжной не являются.
     */
    public static function isPaymentUrl($url): bool
    {
        if (!is_string($url) || strlen($url) > static::PAYMENT_URL_MAX_LEN) {
            return false;
        }

        // Печатный ASCII, \z: `$` пропускает перевод строки в конце
        if (!preg_match('/^[\x21-\x7e]+\z/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        // parse_url оставляет в хосте `\`, а браузер читает его как `/`:
        // `evil.com\.yoomoney.ru` прошёл бы суффикс и увёл на evil.com
        if (!preg_match('/^[a-z0-9.-]+\z/', $host)) {
            return false;
        }

        foreach (static::getPaymentUrlDomains() as $domain) {
            $domain = strtolower($domain);

            if (
                $host === $domain ||
                substr($host, -strlen($domain) - 1) === ".$domain"
            ) {
                return true;
            }
        }

        return false;
    }

    /** Домены страницы подтверждения, сам домен и поддомены */
    protected static function getPaymentUrlDomains(): array
    {
        return ['yoomoney.ru', 'yookassa.ru'];
    }

    /** Текущее состояние платежа; null – не удалось узнать (см. getApiError()) */
    public function fetchPayment(string $paymentId): ?array
    {
        $payment = $this->getApi()->getPayment($paymentId);

        if ($payment === null) {
            // id из неподписанного тела: перевод строки в нём подделал бы запись
            $safeId = static::sanitizeForLog($paymentId, 100);
            static::log("GET payments/$safeId failed: {$this->getApi()->getError()}");
        }

        return $payment;
    }

    public function readNotification(?string $rawBody = null)
    {
        $raw = $rawBody === null ? (string) \diRequest::rawPost() : $rawBody;
        $json = json_decode($raw, true);

        $this->notification = is_array($json) ? $json : [];

        return $this;
    }

    public function getNotification(): array
    {
        return $this->notification ?: [];
    }

    public function getNotificationEvent(): string
    {
        return (string) ($this->getNotification()['event'] ?? '');
    }

    /**
     * id платежа из уведомления о платеже; null для чужих событий (возвраты,
     * выплаты) и мусора. Больше из тела не берётся ничего: оно не подписано.
     */
    public function getNotificationPaymentId(): ?string
    {
        if (strpos($this->getNotificationEvent(), 'payment.') !== 0) {
            return null;
        }

        $id = $this->getNotification()['object']['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function isNotificationIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        foreach (static::NOTIFICATION_NETWORKS as $cidr) {
            if (static::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton((string) $net);

        // ::ffff:a.b.c.d от dual-stack сокета – тот же IPv4, иначе ложная тревога
        if (
            $ipBin !== false &&
            strlen($ipBin) === 16 &&
            strncmp($ipBin, str_repeat("\0", 10) . "\xff\xff", 12) === 0
        ) {
            $ipBin = substr($ipBin, 12);
        }

        if (
            $ipBin === false ||
            $netBin === false ||
            strlen($ipBin) !== strlen($netBin)
        ) {
            return false;
        }

        $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;

        if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xff << (8 - $rest)) & 0xff;

        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }

    public static function status(array $payment): string
    {
        return (string) ($payment['status'] ?? '');
    }

    /** Деньги списаны: только `succeeded` с `paid` */
    public static function isPaid(array $payment): bool
    {
        return static::status($payment) === static::STATUS_SUCCEEDED &&
            ($payment['paid'] ?? null) === true;
    }

    /** Деньги заблокированы, но не списаны: ни оплата, ни отказ */
    public static function isHold(array $payment): bool
    {
        return static::status($payment) === static::STATUS_WAITING_FOR_CAPTURE;
    }

    public static function isCanceled(array $payment): bool
    {
        return static::status($payment) === static::STATUS_CANCELED;
    }

    /** Признак тестового магазина. Нет поля – боевой, иначе пропали бы оплаты */
    public static function isTest(array $payment): bool
    {
        return ($payment['test'] ?? false) === true;
    }

    public static function draftIdOf(array $payment): int
    {
        return (int) ($payment['metadata'][static::METADATA_DRAFT_ID] ?? 0);
    }

    public static function amountOf(array $payment): float
    {
        return (float) ($payment['amount']['value'] ?? 0);
    }

    public static function currencyOf(array $payment): string
    {
        return strtoupper((string) ($payment['amount']['currency'] ?? ''));
    }

    public static function vendorOf(array $payment): ?int
    {
        return Vendor::fromPaymentMethod($payment['payment_method'] ?? null);
    }

    /** Поля для диагностики отказа: что и кто отменил */
    public static function failurePayload(array $payment): array
    {
        return [
            'id' => (string) ($payment['id'] ?? ''),
            'status' => static::status($payment),
            'party' => (string) ($payment['cancellation_details']['party'] ?? ''),
            'reason' => (string) ($payment['cancellation_details']['reason'] ?? ''),
        ];
    }

    /**
     * Неподписанное тело и ответы шлюза идут в лог: без переводов строк (подделка
     * записей), без неограниченного роста и без секретов
     */
    public static function sanitizeForLog($text, $limit = 2000)
    {
        $text = preg_replace(
            '/("(?:secret|password|token)[a-z_]*"\s*:\s*")[^"]*"/i',
            '$1***"',
            (string) $text
        );
        $text = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $text);

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '… [truncated]';
        }

        return $text;
    }
}
