<?php

namespace diCore\Push;

/** POST в сервис push. Замыкание $post – подмена в тестах: ($url, $body, $headers) => [status, array]. */
final class Http
{
    public const CONNECT_TIMEOUT = 5;
    public const TIMEOUT = 12;

    public function __construct(private readonly ?\Closure $post = null)
    {
    }

    /** @return array{0: int, 1: array} код ответа и разобранный JSON (пустой массив, если не JSON) */
    public function post(string $url, string $body, array $headers): array
    {
        if ($this->post) {
            return ($this->post)($url, $body, $headers);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new TransportError('Push transport unavailable (curl init)');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            // Номер ошибки curl (таймаут, DNS, TLS) – без адреса и тела запроса.
            throw new TransportError('Push transport unavailable (curl ' . curl_errno($curl) . ')');
        }
        $json = json_decode($raw, true);

        return [$status, is_array($json) ? $json : []];
    }
}
