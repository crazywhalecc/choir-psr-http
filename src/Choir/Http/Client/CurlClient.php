<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Choir\Http\Client;

use Choir\Http\Client\Exception\ClientException;
use Choir\Http\Client\Exception\NetworkException;
use Choir\Http\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Curl HTTP Client based on PSR-18.
 * @see https://github.com/sunrise-php/http-client-curl/blob/master/src/Client.php
 */
class CurlClient implements ClientInterface, TimeoutInterface
{
    protected array $curl_options;

    /**
     * @throws ClientException
     */
    public function __construct(array $curl_options = [])
    {
        if (!extension_loaded('curl')) { // 必须安装 Curl 扩展才能使用
            throw new ClientException('Curl extension is not loaded');
        }
        $this->curl_options = $curl_options;
    }

    public function setTimeout(int $timeout)
    {
        $this->curl_options[CURLOPT_TIMEOUT_MS] = $timeout;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = $this->createHandle($request);
        $success = curl_exec($handle);
        if ($success === false) {
            throw new NetworkException($request, curl_error($handle), curl_errno($handle));
        }
        $response = $this->createResponse($handle);
        curl_close($handle);
        return $response;
    }

    /**
     * @throws ClientException
     * @return \CurlHandle|false|resource
     */
    private function createHandle(RequestInterface $request) /* @phpstan-ignore-line */
    {
        $this->curl_options[CURLOPT_RETURNTRANSFER] = true; // 返回的内容作为变量储存，而不是直接输出
        $this->curl_options[CURLOPT_HEADER] = true; // 获取结果返回时包含Header数据
        $this->curl_options[CURLOPT_CUSTOMREQUEST] = $request->getMethod(); // 设置请求方式
        $this->curl_options[CURLOPT_URL] = (string) $request->getUri(); // 设置请求的URL
        $this->curl_options[CURLOPT_POSTFIELDS] = (string) $request->getBody(); // 设置请求的Body
        $this->curl_options[CURLOPT_SSL_VERIFYHOST] = false;    // 取消认证ssl
        $this->curl_options[CURLOPT_SSL_VERIFYPEER] = false;    // 取消认证ssl
        // 设置请求头
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->curl_options[CURLOPT_HTTPHEADER][] = sprintf('%s: %s', $name, $value);
            }
        }

        /** @var \CurlHandle|false|resource $curl_handle */
        $curl_handle = curl_init(); /* @phpstan-ignore-line */
        if ($curl_handle === false) {
            throw new ClientException('Unable to initialize a cURL handle');
        }

        $success = curl_setopt_array($curl_handle, $this->curl_options);
        if ($success === false) {
            throw new ClientException('Unable to configure a cURL handle');
        }

        return $curl_handle;
    }

    /**
     * @param \CurlHandle|int|resource $handle
     */
    private function createResponse($handle): ResponseInterface /* @phpstan-ignore-line */
    {
        $status_code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $response = HttpFactory::createResponse($status_code);

        /** @var null|string $message */
        $message = curl_multi_getcontent($handle); /* @phpstan-ignore-line */
        if ($message === null) {
            return $response;
        }

        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $header = substr($message, 0, $header_size);

        $fields = explode("\n", $header);
        foreach ($fields as $field) {
            $colpos = strpos($field, ':');
            if ($colpos === false) { // Status Line
                continue;
            }
            if ($colpos === 0) { // HTTP/2 Field
                continue;
            }

            [$name, $value] = explode(':', $field, 2);

            $response = $response->withAddedHeader(trim($name), trim($value));
        }

        $body = substr($message, $header_size);
        $response->getBody()->write($body);

        return $response;
    }
}
