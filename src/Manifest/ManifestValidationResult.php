<?php

declare(strict_types=1);

namespace BlackCat\CliSpec\Manifest;

final class ManifestValidationResult
{
    /**
     * @param ManifestValidationError[] $errors
     */
    public function __construct(
        private readonly array $errors
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return ManifestValidationError[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

