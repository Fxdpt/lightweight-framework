<?php

namespace PhpServer\Infrastructure\Server\Validator;

final class ApplicationFormUrlEncodedValidator implements ContentTypeValidatorInterface
{
    private const SUPPORTED_TYPE = "application/x-www-form-urlencoded";

    /**
     * @inheritDoc
     */
    public function supports(string $type): bool
    {
        return $type === self::SUPPORTED_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function isValid(string $body): bool
    {
        return (bool) preg_match("/^(?:(?:\w+)(?:\[(?:\d*|'[^']*')\])*=[\w\-\+\.%]*(?:&|$))+$/", $body);
    }
}