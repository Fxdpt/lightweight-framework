<?php

namespace PhpServer\Infrastructure\Server;

use Exception;
use PhpServer\Infrastructure\Server\Validator\ContentTypeValidatorInterface;
use ReflectionClass;
use PhpServer\Attributes\InterfaceIterator;

final class Request
{
    public const CONTENT_TYPE_HEADER = 'Content-Type';

    /**
     * @var ContentTypeValidatorInterface[]
     */
    private static array $supportedValidators = [];

    /**
     *
     * @param ContentTypeValidatorInterface[] $validators
     * @param array{
     *  http_method: string,
     *  request_target: string,
     *  http_version: string
     * } $requestLines
     * @param array<string, string> $headers
     * @param string|null $body
     */
    #[InterfaceIterator('validators', ContentTypeValidatorInterface::class)]
    public function __construct(
        #[InterfaceIterator(ContentTypeValidatorInterface::class)] private readonly array $validators,
        private readonly array $requestLines = [],
        private readonly array $headers = [],
        private readonly ?string $body = null,
    ) {

        self::$supportedValidators = $validators;
    }

    /**
     * Create a request with parsed request line, headers and body
     *
     * @param string $httpRequest
     * @return self
     */
    public function withFullParts(string $httpRequest): self
    {
        return (new Request(
            requestLines: self::parseRequestLines($httpRequest),
            headers: self::parseHeaders($httpRequest),
            body: self::parseBody($httpRequest),
            validators: self::$supportedValidators
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

    /**
     * Validate request body is valid according to request Content-Type
     *
     * @param string $body
     * @param string $contentType
     * @return void
     */
    private static function validateBodyFormat(string $body, string $contentType): void
    {
        foreach (self::$supportedValidators as $validator) {
            if ($validator->supports($contentType)) {
                if (! $validator->isValid($body)) {
                    throw new Exception(sprintf("Request body mismatch format for Content-Type: [%s]", $contentType));
                }

                return;
            }
        }

        throw new Exception(sprintf("No validator found for Content-Type: [%s]", $contentType));
    }
}
