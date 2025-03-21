<?php

declare(strict_types=1);

namespace Choir\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    use MessageTrait;

    /** @var array Map of standard HTTP status code/reason phrases */
    private const PHRASES = [
        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-status', 208 => 'Already Reported',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 306 => 'Switch Proxy', 307 => 'Temporary Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Time-out', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Large', 415 => 'Unsupported Media Type', 416 => 'Requested range not satisfiable', 417 => 'Expectation Failed', 418 => 'I\'m a teapot', 422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Unordered Collection', 426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests', 431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Time-out', 505 => 'HTTP Version not supported', 506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected', 511 => 'Network Authentication Required',
    ];

    private string $str_cache = '';

    /** @var string */
    private $reasonPhrase;

    /** @var int */
    private $statusCode;

    /**
     * @param int|string                           $status  Status code
     * @param array                                $headers Response headers
     * @param null|resource|StreamInterface|string $body    Response body
     * @param string                               $version Protocol version
     * @param null|string                          $reason  Reason phrase (when empty a default will be used based on the status code)
     */
    public function __construct($status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        // If we got nobody, defer initialization of the stream until Response::getBody()
        if ($body !== '' && $body !== null) {
            $this->stream = Stream::create($body);
        }
        if (\is_string($status)) {
            $status = (int) $status;
        }
        $this->statusCode = $status;
        $this->setHeaders($headers);
        if ($reason === null && isset(self::PHRASES[$this->statusCode])) {
            $this->reasonPhrase = self::PHRASES[$status];
        } else {
            $this->reasonPhrase = $reason ?? '';
        }

        $this->protocol = $version;
    }

    public function __toString()
    {
        if ($this->str_cache !== '') {
            return $this->str_cache;
        }
        $reason = $this->reasonPhrase;
        $this->getBody()->rewind();
        $body_len = $this->getBody()->getSize();
        $this->getBody()->rewind();

        if (empty($this->headers)) {
            return $this->str_cache = "HTTP/{$this->protocol} {$this->statusCode} {$reason}\r\nContent-Type: text/html;charset=utf-8\r\nContent-Length: {$body_len}\r\nConnection: keep-alive\r\n\r\n{$this->getBody()->getContents()}";
        }

        $head = "HTTP/{$this->protocol} {$this->statusCode} {$reason}\r\n";
        $headers = $this->headers;
        foreach ($headers as $name => $value) {
            if (\is_array($value)) {
                foreach ($value as $item) {
                    $head .= "{$name}: {$item}\r\n";
                }
                continue;
            }
            $head .= "{$name}: {$value}\r\n";
        }

        if (!isset($headers['Connection'])) {
            $head .= "Connection: keep-alive\r\n";
        }

        if (!isset($headers['Content-Type'])) {
            $head .= "Content-Type: text/html;charset=utf-8\r\n";
        } elseif ($headers['Content-Type'] === 'text/event-stream') {
            return $this->str_cache = $head . $this->getBody()->getContents();
        }

        if (!isset($headers['Transfer-Encoding'])) {
            $head .= "Content-Length: {$body_len}\r\n\r\n";
        } else {
            return $this->str_cache = "{$head}\r\n" . dechex($body_len) . "\r\n{$this->getBody()->getContents()}\r\n";
        }

        // The whole http package
        return $this->str_cache = $head . $this->getBody()->getContents();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus($code, $reasonPhrase = ''): self
    {
        if (!\is_int($code) && !\is_string($code)) {
            throw new \InvalidArgumentException('Status code has to be an integer');
        }

        $code = (int) $code;
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException(\sprintf('Status code has to be an integer between 100 and 599. A status code of %d was given', $code));
        }

        $new = clone $this;
        $new->statusCode = $code;
        if (($reasonPhrase === null || $reasonPhrase === '') && isset(self::PHRASES[$new->statusCode])) {
            $reasonPhrase = self::PHRASES[$new->statusCode];
        }
        $new->reasonPhrase = $reasonPhrase;

        return $new;
    }
}
