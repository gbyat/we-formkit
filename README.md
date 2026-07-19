# WE Formkit

Modular WordPress form builder with typed fields, entries, and a developer module API.

## Development

```bash
composer install
npm install
npm run wp-cli:install
```

### Translations

```bash
npm run i18n
```

Generates/updates `.pot`, `.po`, `.mo`, and block-editor `.json` files in `languages/`.

### Release

```bash
npm run release:patch
```

This bumps the version, updates changelog/readme files, refreshes translations, commits, tags, and pushes. GitHub Actions builds the distributable ZIP and publishes the release.

Local installable ZIP:

```bash
npm run build
```
