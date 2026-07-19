# Translation catalogs

Store one JSON file per locale here, keyed by the English `msgid` from the POT file:

- `de_DE.json`
- `it_IT.json`
- …

## Workflow

1. Update strings in code and run `npm run pot`.
2. Add or edit translations in `scripts/translations/<locale>.json`.
3. Run `npm run i18n:translate` (normalizes encoding, syncs keys to POT, applies to PO).
4. Or run the full chain: `npm run i18n`.

## Export from an existing PO file

Use the script file output argument (do **not** use PowerShell `>` redirects — they can add a UTF-8 BOM):

```bash
node scripts/export-po-catalog.js languages/<text-domain>-de_DE.po scripts/translations/de_DE.json
```

## Encoding

- Save catalogs as UTF-8 without BOM.
- Run `npm run i18n:normalize` to repair common mojibake (`ÔÇª` → `…`, etc.).
- Run `npm run i18n:check` to fail when suspicious sequences remain in catalogs, PO, or JSON.
