<?php

namespace PhpServer\Infrastructure\Server;

use Exception;
use PhpServer\Infrastructure\Server\Validator\ContentTypeValidatorInterface;
use ReflectionClass;

final class Request
{
    public const CONTENT_TYPE_HEADER = 'Content-Type';

    /**
     * @param array{
     *  http_method: string,
     *  request_target: string,
     *  http_version: string
     * } $requestLines
     * @param array<string, string> $headers
     * @param string|null $body
     */
    public function __construct(
        private readonly array $requestLines = [],
        private readonly array $headers = [],
        private readonly ?string $body = null
    ) {
    }

    /**
     * Create a request with parsed request line, headers and body
     *
     * @param string $httpRequest
     * @return self
     */
    public static function withFullParts(string $httpRequest): self
    {
        return (new Request(
            requestLines: self::parseRequestLines($httpRequest),
            headers: self::parseHeaders($httpRequest),
            body: self::parseBody($httpRequest)
        ));
    }

    /**
     * Parse the request lines into its three parts
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Messages#request_line
     *
     * @param string $httpRequest
     * @return array
     */
    private static function parseRequestLines(string $httpRequest): array
    {
        $requestParts = explode("\r\n", $httpRequest);
        $requestLines = $requestParts[0];
        $requestLinesParts = explode(" ", $requestLines);

        return [
            'http_method' => $requestLinesParts[0],
            'request_target' => $requestLinesParts[1],
            'http_version' => $requestLinesParts[2]
        ];
    }

    /**
     * Parse headers on format [<header name> => <header value>]
     *
     * @param string $httpRequest
     * @return array<string, string>
     */
    private static function parseHeaders(string $httpRequest): array
    {
        $requestParts = explode("\r\n", $httpRequest);
        $headers = array_diff_key($requestParts, [0,1,2]);
        unset($headers[array_key_last($headers)]);
        $parsedHeaders = [];
        foreach ($headers as $header) {
            if (strpos($header, ':')) {
                $headerParts = explode(":", $header);
                $parsedHeaders[trim($headerParts[0])] = trim($headerParts[1]);
            }
        }

        return $parsedHeaders;
    }

    /**
     * Parse body and match content-type if given with body format.
     *
     * @param string $httpRequest
     * @return string
     */
    private static function parseBody(string $httpRequest): string
    {
        $requestParts = explode("\r\n", $httpRequest);
        $headers = self::parseHeaders($httpRequest);
        $body = end($requestParts);
        if (array_key_exists(self::CONTENT_TYPE_HEADER, $headers)) {
            self::validateBodyFormat($body, $headers[self::CONTENT_TYPE_HEADER]);
        }

        return $body;
    }

    private static function validateBodyFormat(string $body, string $contentType): void
    {
        $validators = self::getValidators();

        foreach ($validators as $validator) {
            if ($validator->supports($contentType)) {
                if (! $validator->isValid($body)) {
                    throw new Exception(sprintf("Request body mismatch format for Content-Type: [%s]", $contentType));
                }

                return;
            }
        }

        throw new Exception(sprintf("No validator found for Content-Type: [%s]", $contentType));
    }

    /**
     *
     *
     * @return ContentTypeValidatorInterface[]
     */
    private static function getValidators(): array
    {
        $classes = get_declared_classes();
        $validators = [];
        foreach ($classes as $class) {
            if((new ReflectionClass($class))->implementsInterface(ContentTypeValidatorInterface::class)) {
                $validators[] = new $class();
            }
        }

        return $validators;
    }
}
