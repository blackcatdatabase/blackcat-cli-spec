<?php

declare(strict_types=1);

namespace BlackCat\CliSpec\Manifest;

use JsonException;

final class ManifestValidator
{
    private const int SUPPORTED_SCHEMA_VERSION = 1;

    private const string ID_PATTERN = '/^[a-z0-9][a-z0-9-]{0,62}$/';
    private const string COMPOSER_PKG_PATTERN = '/^[a-z0-9_.-]+\\/[a-z0-9_.-]+$/';

    /**
     * @return ManifestValidationResult
     */
    public static function validateFile(string $path): ManifestValidationResult
    {
        if (!is_file($path)) {
            return new ManifestValidationResult([
                new ManifestValidationError('', "File not found: {$path}"),
            ]);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return new ManifestValidationResult([
                new ManifestValidationError('', "Unable to read file: {$path}"),
            ]);
        }

        return self::validateJson($json, $path);
    }

    public static function validateJson(string $json, string $sourceName = '(json)'): ManifestValidationResult
    {
        try {
            /** @var mixed $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return new ManifestValidationResult([
                new ManifestValidationError('', "Invalid JSON in {$sourceName}: {$e->getMessage()}"),
            ]);
        }

        if (!is_array($data)) {
            return new ManifestValidationResult([
                new ManifestValidationError('', "Manifest root must be an object in {$sourceName}."),
            ]);
        }

        /** @var array<string,mixed> $data */
        return self::validateArray($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function validateArray(array $data): ManifestValidationResult
    {
        $errors = [];

        $allowedTopLevel = ['schema_version', 'component', 'cli', 'extensions'];
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowedTopLevel, true)) {
                $errors[] = new ManifestValidationError(self::ptr($key), "Unknown top-level key '{$key}'.");
            }
        }

        $schemaVersion = $data['schema_version'] ?? null;
        if (!is_int($schemaVersion)) {
            $errors[] = new ManifestValidationError(self::ptr('schema_version'), 'schema_version must be an integer.');
        } elseif ($schemaVersion !== self::SUPPORTED_SCHEMA_VERSION) {
            $errors[] = new ManifestValidationError(
                self::ptr('schema_version'),
                'Unsupported schema_version. Expected ' . self::SUPPORTED_SCHEMA_VERSION . '.'
            );
        }

        $component = $data['component'] ?? null;
        if (!is_array($component)) {
            $errors[] = new ManifestValidationError(self::ptr('component'), 'component must be an object.');
        } else {
            /** @var array<string,mixed> $component */
            self::validateComponent($component, $errors);
        }

        $cli = $data['cli'] ?? null;
        if (!is_array($cli)) {
            $errors[] = new ManifestValidationError(self::ptr('cli'), 'cli must be an object.');
        } else {
            /** @var array<string,mixed> $cli */
            self::validateCli($cli, $errors);
        }

        if (array_key_exists('extensions', $data) && !is_array($data['extensions'])) {
            $errors[] = new ManifestValidationError(self::ptr('extensions'), 'extensions must be an object.');
        }

        return new ManifestValidationResult($errors);
    }

    /**
     * @param array<string,mixed> $component
     * @param ManifestValidationError[] $errors
     */
    private static function validateComponent(array $component, array &$errors): void
    {
        $allowed = ['id', 'name', 'description', 'homepage', 'composer_package'];
        foreach (array_keys($component) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = new ManifestValidationError(self::ptr('component', $key), "Unknown component key '{$key}'.");
            }
        }

        $id = $component['id'] ?? null;
        if (!is_string($id) || $id === '') {
            $errors[] = new ManifestValidationError(self::ptr('component', 'id'), 'component.id must be a non-empty string.');
        } elseif (!preg_match(self::ID_PATTERN, $id)) {
            $errors[] = new ManifestValidationError(
                self::ptr('component', 'id'),
                'component.id must match ' . self::ID_PATTERN . '.'
            );
        }

        $name = $component['name'] ?? null;
        if (!is_string($name) || $name === '') {
            $errors[] = new ManifestValidationError(self::ptr('component', 'name'), 'component.name must be a non-empty string.');
        }

        if (array_key_exists('description', $component) && !is_string($component['description'])) {
            $errors[] = new ManifestValidationError(self::ptr('component', 'description'), 'component.description must be a string.');
        }

        if (array_key_exists('homepage', $component) && !is_string($component['homepage'])) {
            $errors[] = new ManifestValidationError(self::ptr('component', 'homepage'), 'component.homepage must be a string.');
        }

        if (array_key_exists('composer_package', $component)) {
            $pkg = $component['composer_package'];
            if (!is_string($pkg) || $pkg === '') {
                $errors[] = new ManifestValidationError(
                    self::ptr('component', 'composer_package'),
                    'component.composer_package must be a non-empty string.'
                );
            } elseif (!preg_match(self::COMPOSER_PKG_PATTERN, $pkg)) {
                $errors[] = new ManifestValidationError(
                    self::ptr('component', 'composer_package'),
                    'component.composer_package must match ' . self::COMPOSER_PKG_PATTERN . '.'
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $cli
     * @param ManifestValidationError[] $errors
     */
    private static function validateCli(array $cli, array &$errors): void
    {
        $allowed = ['entrypoints'];
        foreach (array_keys($cli) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = new ManifestValidationError(self::ptr('cli', $key), "Unknown cli key '{$key}'.");
            }
        }

        $entrypoints = $cli['entrypoints'] ?? null;
        if (!is_array($entrypoints)) {
            $errors[] = new ManifestValidationError(self::ptr('cli', 'entrypoints'), 'cli.entrypoints must be an array.');
            return;
        }

        if ($entrypoints === []) {
            $errors[] = new ManifestValidationError(self::ptr('cli', 'entrypoints'), 'cli.entrypoints must not be empty.');
            return;
        }

        $seenEntrypointIds = [];
        $seenCommands = [];
        $seenAliases = [];

        foreach ($entrypoints as $i => $entrypoint) {
            $ptr = self::ptr('cli', 'entrypoints', (string) $i);
            if (!is_array($entrypoint)) {
                $errors[] = new ManifestValidationError($ptr, 'Entry point must be an object.');
                continue;
            }

            /** @var array<string,mixed> $entrypoint */
            self::validateEntrypoint($entrypoint, $ptr, $errors, $seenEntrypointIds, $seenCommands, $seenAliases);
        }
    }

    /**
     * @param array<string,mixed> $entrypoint
     * @param ManifestValidationError[] $errors
     * @param array<string,bool> $seenEntrypointIds
     * @param array<string,bool> $seenCommands
     * @param array<string,bool> $seenAliases
     */
    private static function validateEntrypoint(
        array $entrypoint,
        string $ptr,
        array &$errors,
        array &$seenEntrypointIds,
        array &$seenCommands,
        array &$seenAliases
    ): void {
        $allowed = ['id', 'command', 'summary', 'description', 'type', 'proxy', 'aliases', 'since', 'deprecated'];
        foreach (array_keys($entrypoint) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = new ManifestValidationError("{$ptr}/{$key}", "Unknown entrypoint key '{$key}'.");
            }
        }

        $id = $entrypoint['id'] ?? null;
        if (!is_string($id) || $id === '') {
            $errors[] = new ManifestValidationError("{$ptr}/id", 'entrypoint.id must be a non-empty string.');
        } elseif (!preg_match(self::ID_PATTERN, $id)) {
            $errors[] = new ManifestValidationError("{$ptr}/id", 'entrypoint.id must match ' . self::ID_PATTERN . '.');
        } elseif (isset($seenEntrypointIds[$id])) {
            $errors[] = new ManifestValidationError("{$ptr}/id", "Duplicate entrypoint.id '{$id}'.");
        } else {
            $seenEntrypointIds[$id] = true;
        }

        $command = $entrypoint['command'] ?? null;
        if (!is_string($command) || $command === '') {
            $errors[] = new ManifestValidationError("{$ptr}/command", 'entrypoint.command must be a non-empty string.');
        } elseif (!preg_match(self::ID_PATTERN, $command)) {
            $errors[] = new ManifestValidationError(
                "{$ptr}/command",
                'entrypoint.command must match ' . self::ID_PATTERN . '.'
            );
        } elseif (isset($seenCommands[$command])) {
            $errors[] = new ManifestValidationError("{$ptr}/command", "Duplicate entrypoint.command '{$command}'.");
        } else {
            $seenCommands[$command] = true;
        }

        $summary = $entrypoint['summary'] ?? null;
        if (!is_string($summary) || $summary === '') {
            $errors[] = new ManifestValidationError("{$ptr}/summary", 'entrypoint.summary must be a non-empty string.');
        }

        if (array_key_exists('description', $entrypoint) && !is_string($entrypoint['description'])) {
            $errors[] = new ManifestValidationError("{$ptr}/description", 'entrypoint.description must be a string.');
        }

        $type = $entrypoint['type'] ?? null;
        if (!is_string($type) || $type === '') {
            $errors[] = new ManifestValidationError("{$ptr}/type", 'entrypoint.type must be a non-empty string.');
        } elseif (!in_array($type, ['proxy', 'builtin'], true)) {
            $errors[] = new ManifestValidationError("{$ptr}/type", "Unsupported entrypoint.type '{$type}'.");
        }

        if (array_key_exists('since', $entrypoint) && !is_string($entrypoint['since'])) {
            $errors[] = new ManifestValidationError("{$ptr}/since", 'entrypoint.since must be a string.');
        }

        if (array_key_exists('deprecated', $entrypoint) && !is_bool($entrypoint['deprecated'])) {
            $errors[] = new ManifestValidationError("{$ptr}/deprecated", 'entrypoint.deprecated must be a boolean.');
        }

        $aliases = $entrypoint['aliases'] ?? null;
        if ($aliases !== null) {
            if (!is_array($aliases)) {
                $errors[] = new ManifestValidationError("{$ptr}/aliases", 'entrypoint.aliases must be an array.');
            } else {
                foreach ($aliases as $j => $alias) {
                    if (!is_string($alias) || $alias === '') {
                        $errors[] = new ManifestValidationError("{$ptr}/aliases/{$j}", 'Alias must be a non-empty string.');
                        continue;
                    }
                    if (!preg_match(self::ID_PATTERN, $alias)) {
                        $errors[] = new ManifestValidationError(
                            "{$ptr}/aliases/{$j}",
                            'Alias must match ' . self::ID_PATTERN . '.'
                        );
                        continue;
                    }
                    if ($alias === $command) {
                        $errors[] = new ManifestValidationError("{$ptr}/aliases/{$j}", 'Alias must not equal command.');
                        continue;
                    }
                    if (isset($seenAliases[$alias]) || isset($seenCommands[$alias])) {
                        $errors[] = new ManifestValidationError("{$ptr}/aliases/{$j}", "Duplicate alias '{$alias}'.");
                        continue;
                    }
                    $seenAliases[$alias] = true;
                }
            }
        }

        if ($type === 'proxy') {
            $proxy = $entrypoint['proxy'] ?? null;
            if (!is_array($proxy)) {
                $errors[] = new ManifestValidationError("{$ptr}/proxy", 'entrypoint.proxy must be an object for type=proxy.');
                return;
            }
            /** @var array<string,mixed> $proxy */
            self::validateProxy($proxy, "{$ptr}/proxy", $errors);
            return;
        }

        if ($type === 'builtin' && array_key_exists('proxy', $entrypoint)) {
            $errors[] = new ManifestValidationError("{$ptr}/proxy", 'entrypoint.proxy must not be present for type=builtin.');
        }
    }

    /**
     * @param array<string,mixed> $proxy
     * @param ManifestValidationError[] $errors
     */
    private static function validateProxy(array $proxy, string $ptr, array &$errors): void
    {
        $allowed = ['runner', 'script', 'args'];
        foreach (array_keys($proxy) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = new ManifestValidationError("{$ptr}/{$key}", "Unknown proxy key '{$key}'.");
            }
        }

        $runner = $proxy['runner'] ?? null;
        if (!is_string($runner) || $runner === '') {
            $errors[] = new ManifestValidationError("{$ptr}/runner", 'proxy.runner must be a non-empty string.');
        }

        $script = $proxy['script'] ?? null;
        if (!is_string($script) || $script === '') {
            $errors[] = new ManifestValidationError("{$ptr}/script", 'proxy.script must be a non-empty string.');
        }

        $args = $proxy['args'] ?? null;
        if ($args !== null) {
            if (!is_array($args)) {
                $errors[] = new ManifestValidationError("{$ptr}/args", 'proxy.args must be an array.');
            } else {
                foreach ($args as $i => $arg) {
                    if (!is_string($arg)) {
                        $errors[] = new ManifestValidationError("{$ptr}/args/{$i}", 'proxy.args items must be strings.');
                    }
                }
            }
        }
    }

    private static function ptr(string ...$segments): string
    {
        $encoded = array_map(
            static fn (string $s): string => str_replace('/', '~1', str_replace('~', '~0', $s)),
            $segments
        );

        return '/' . implode('/', $encoded);
    }
}

