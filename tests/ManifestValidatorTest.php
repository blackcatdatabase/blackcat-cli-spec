<?php

declare(strict_types=1);

namespace BlackCat\CliSpec\Tests;

use BlackCat\CliSpec\Manifest\ManifestValidationResult;
use BlackCat\CliSpec\Manifest\ManifestValidator;
use PHPUnit\Framework\TestCase;

final class ManifestValidatorTest extends TestCase
{
    public function testValidManifestPasses(): void
    {
        $result = ManifestValidator::validateArray([
            'schema_version' => 1,
            'component' => [
                'id' => 'blackcat-crypto',
                'name' => 'BlackCat Crypto',
                'composer_package' => 'blackcat/crypto',
            ],
            'cli' => [
                'entrypoints' => [
                    [
                        'id' => 'crypto',
                        'command' => 'crypto',
                        'summary' => 'Crypto operations',
                        'type' => 'proxy',
                        'proxy' => [
                            'runner' => 'php',
                            'script' => 'bin/crypto',
                            'args' => [],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($result->isValid(), implode("\n", self::stringifyErrors($result)));
        self::assertSame([], $result->errors());
    }

    public function testInvalidJsonFails(): void
    {
        $result = ManifestValidator::validateJson('{', 'bad.json');

        self::assertFalse($result->isValid());
        self::assertNotSame([], $result->errors());
        self::assertStringContainsString('Invalid JSON', $result->errors()[0]->message());
    }

    public function testUnsupportedSchemaVersionFails(): void
    {
        $result = ManifestValidator::validateArray([
            'schema_version' => 2,
            'component' => ['id' => 'x', 'name' => 'X'],
            'cli' => ['entrypoints' => [['id' => 'x', 'command' => 'x', 'summary' => 'x', 'type' => 'builtin']]],
        ]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('Unsupported schema_version', implode("\n", self::stringifyErrors($result)));
    }

    public function testDuplicateCommandsFail(): void
    {
        $result = ManifestValidator::validateArray([
            'schema_version' => 1,
            'component' => ['id' => 'x', 'name' => 'X'],
            'cli' => [
                'entrypoints' => [
                    ['id' => 'a', 'command' => 'crypto', 'summary' => 'a', 'type' => 'builtin'],
                    ['id' => 'b', 'command' => 'crypto', 'summary' => 'b', 'type' => 'builtin'],
                ],
            ],
        ]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString('Duplicate entrypoint.command', implode("\n", self::stringifyErrors($result)));
    }

    public function testBuiltinMustNotContainProxy(): void
    {
        $result = ManifestValidator::validateArray([
            'schema_version' => 1,
            'component' => ['id' => 'x', 'name' => 'X'],
            'cli' => [
                'entrypoints' => [
                    [
                        'id' => 'a',
                        'command' => 'a',
                        'summary' => 'a',
                        'type' => 'builtin',
                        'proxy' => ['runner' => 'php', 'script' => 'bin/a'],
                    ],
                ],
            ],
        ]);

        self::assertFalse($result->isValid());
        self::assertStringContainsString(
            'must not be present for type=builtin',
            implode("\n", self::stringifyErrors($result))
        );
    }

    /**
     * @return string[]
     */
    private static function stringifyErrors(ManifestValidationResult $result): array
    {
        return array_map(
            static fn ($e): string => ($e->path() === '' ? '(root)' : $e->path()) . ': ' . $e->message(),
            $result->errors()
        );
    }
}

