<?php

namespace PhpServer\Infrastructure\Server\Validator;

final class ApplicationJsonValidator implements ContentTypeValidatorInterface
{
    private const SUPPORTED_TYPE = "application/json";

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
        return json_validate($body);
    }
}
