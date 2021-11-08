<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.12.2016
 * Time: 11:23
 */

namespace diCore\Payment\Paypal;

use diCore\Tool\Logger;

class Transport
{
	const applicationName = '#';
	const debug = false;
	const certFilename = null;

	/**
	 * @return Transport
	 */
	public static function create()
	{
		$className = \diLib::getChildClass(self::class, 'Transport');

		$pp = new $className();

		return $pp;
	}

	public static function requestSockets($url, $query)
	{
		$url = parse_url($url);
		$queryStr = http_build_query($query);

		$fp = fsockopen($url['host'], 443, $errNum, $errStr, 30);

		if (!$fp) {
			static::log('Sockets: error (' . $errNum . ') ' . $errStr);

			return null;
		}

		fputs($fp, "POST {$url['path']} HTTP/1.1\r\n");
		fputs($fp, "Host: {$url['host']}\r\n");
		fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
		fputs($fp, "Content-length: " . strlen($queryStr) . "\r\n");
		fputs($fp, "Connection: close\r\n\r\n");
		fputs($fp, $queryStr . "\r\n\r\n");

		$response = '';

		while (!feof($fp)) {
			$response .= fgets($fp, 1024);
		}

		static::log('Sockets response: ' . $response);

		fclose($fp);

		return $response;
	}

	public static function requestCUrl($url, $query)
	{
		$queryStr = http_build_query($query);

		if (static::debug) {
		    $sslInfo = OPENSSL_VERSION_NUMBER < 0x009080bf
                ? 'OpenSSL Version Out-of-Date'
                : 'OpenSSL Version OK';
			static::log('OPENSSL_VERSION_NUMBER: ' . OPENSSL_VERSION_NUMBER . ', ' . $sslInfo);
		}

		static::log('CURL query: ' . $queryStr);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, static::debug ? 1 : 0);
		curl_setopt($curl, CURLINFO_HEADER_OUT, static::debug ? 1 : 0);
		curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $queryStr);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);

		if (static::certFilename) {
			curl_setopt($curl, CURLOPT_CAINFO, \diPaths::fileSystem() . static::certFilename);
		}

		if (static::debug) {
			curl_setopt($curl, CURLOPT_VERBOSE, 1);
			$verbose = fopen('php://temp', 'w+');
			curl_setopt($curl, CURLOPT_STDERR, $verbose);
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Connection: Close',
			'User-Agent: ' . static::applicationName,
		]);
		$response = curl_exec($curl);

		if (static::debug) {
			static::log('CURL error: ' . curl_error($curl) . ', ' . curl_errno($curl));

			rewind($verbose);
			$verboseLog = stream_get_contents($verbose);

			static::log('Verbose information: ' . $verboseLog);
		}

		curl_close($curl);

		return $response;
	}

	/**
	 * @link https://github.com/guzzle/guzzle
	 */
	public static function requestGuzzle($url, $query)
	{
		$client = new \GuzzleHttp\Client();
		$res = $client->post($url, [
			'form_params' => $query,
		]);

		return $res->getBody();
	}

	/**
	 * @link http://requests.ryanmccue.info/
	 */
	public static function requestRequests($url, $query)
	{
		if (static::debug) {
			static::log('Requests: ' . $url . ' ? ' . print_r($query, true));
		}

		$request = \Requests::post($url, [], $query, [
            'transport' => 'Requests_Transport_fsockopen',
		]);

		if (static::debug) {
			static::log('Requests response: ' . print_r($request, true));
		}

		return $request->body;
	}

	public static function log($message)
	{
        Logger::getInstance()->log($message, 'PayPal Transport', '-payment');
	}
}