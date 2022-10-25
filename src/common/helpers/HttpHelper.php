<?php

namespace yii2custom\common\helpers;

use Yii;
use yii\base\BaseObject;

/**
 * @property-read bool $success
 */
class TResponse extends BaseObject
{
    public int $status = 0;
    public array $headers = [];
    public array $cookies = [];
    public $body = '';
    public $data = null;

    public function __construct(int $status, string $body, array $headers = [], $cookies = [], $json = true)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->body = $body;
        if ($json) {
            $this->data = $body ? json_decode($body, true) : [];
        }
    }

    public function getSuccess()
    {
        return $this->status && $this->status < 300;
    }
}

class HttpHelper
{
    public static function get(string $url, array $query = [], ?string $token = null, array $headers = [], $json = true): TResponse
    {
        $ch = curl_init($url . ($query ? ('?' . http_build_query($query)) : ''));

        if (!empty($_SERVER['SHELL'])) {
            echo 'GET: ' . ($url . ($query ? ('?' . http_build_query($query)) : '')) . "\n";
        }

        if ($json) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($token) {
            $headers[] = 'Authorization: Token ' . $token;
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = static::parseHeaders(substr($body, 0, $header_size));
        $cookies = static::parseCookies(substr($body, 0, $header_size));
        $body = substr($body, $header_size);
        curl_close($ch);

        return new TResponse($status, $body, $headers, $cookies, $json);
    }

    public static function post(string $url, array $data, ?string $token = null, array $headers = [], $json = true): TResponse
    {
        $ch = curl_init($url);

        if ($json) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($token) {
            $headers[] = 'Authorization: Token ' . $token;
        }

        if (!empty($_SERVER['SHELL'])) {
            echo 'POST: ' . $url . "\n";
        }

        $body = $json ? json_encode($data) : http_build_query($data);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $time = time();
        $dir = Yii::getAlias('@runtime/logs');
        $file = "$dir/post-$time.log";
        $c = 1;

        while (file_exists($file)) {
            $file = "$dir/post-$time-". $c++ .".log";
        }

        file_put_contents($file, $body);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = static::parseHeaders(substr($response, 0, $header_size));
        $cookies = static::parseCookies(substr($response, 0, $header_size));
        $response = substr($response, $header_size);
        curl_close($ch);

        return new TResponse($status, $response, $headers, $cookies, $json);
    }

    ///
    /// Protected
    ///

    protected static function parseHeaders(string $headers)
    {
        $result = [];

        foreach (explode("\r\n", $headers) as $part) {
            if (count($matches = explode(':', $part, 2)) == 2) {
                $result[strtolower($matches[0])] = trim($matches[1]);
            }
        }

        return $result;
    }

    protected static function parseCookies(string $headers)
    {
        $result = [];

        foreach (explode("\r\n", $headers) as $part) {
            if (count($matches = explode(':', $part, 2)) == 2) {
                if ($matches[0] == 'set-cookie') {
                    $result[] = trim($matches[1]);
                }
            }
        }

        return $result;
    }
}