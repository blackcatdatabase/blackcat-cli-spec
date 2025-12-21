# BlackCat CLI Spec

This repository defines the **manifest format** used by `blackcat-cli` to discover CLI capabilities provided by installed BlackCat components.

Scope:
- JSON Schema (`schema/blackcat-cli.manifest.schema.json`)
- PHP manifest validator (`BlackCat\\CliSpec\\Manifest\\ManifestValidator`)

The spec is intentionally **declarative**: it describes *what a component provides* (metadata), not how the CLI implements it (logic stays in `blackcat-cli`).

## Manifest file

Recommended filename: `blackcat-cli.json` (stored at the component repository root).

## Example

See `examples/blackcat-cli.json`.

## Validation (PHP)

```bash
php bin/validate-manifest examples/blackcat-cli.json
```

