<?php

declare(strict_types=1);

namespace BlackCat\CliSpec\Manifest;

final class ManifestValidationError
{
    public function __construct(
        private readonly string $path,
        private readonly string $message
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function message(): string
    {
        return $this->message;
    }
}

