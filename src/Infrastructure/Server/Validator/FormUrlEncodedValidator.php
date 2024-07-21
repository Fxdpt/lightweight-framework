<?php

namespace PhpServer\Infrastructure\Server\Validator;

final class FormUrlEncodedValidator implements ContentTypeValidatorInterface
{
    private const SUPPORTED_TYPE = "application/x-www-form-urlencoded";

    public function supports(string $type): bool
    {
        return $type === self::SUPPORTED_TYPE;
    }

    public function isValid(string $body): bool
    {
        return (bool) preg_match("/^(?:(?:\w+)(?:\[(?:\d*|'[^']*')\])*=[\w\-\+\.%]*(?:&|$))+$/", $body);
    }
}