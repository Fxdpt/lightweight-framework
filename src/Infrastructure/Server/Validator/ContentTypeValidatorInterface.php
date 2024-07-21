<?php

namespace PhpServer\Infrastructure\Server\Validator;

interface ContentTypeValidatorInterface
{
    /**
     * Indicates if the class implementing the interface supports the given content-type
     *
     * @param string $type
     *
     * @return bool
     */
    public function supports(string $type): bool;

    /**
     * Validate that the body format matching the content-type
     *
     * @param string $body
     *
     * @return bool
     */
    public function isValid(string $body): bool;
}