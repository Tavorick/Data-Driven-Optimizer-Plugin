# Release checklist

Gebruik deze checklist voordat je een release tagt:

- [ ] Tests zijn groen (`composer test`).
- [ ] `CHANGELOG.md` is bijgewerkt met de nieuwe pluginversie.
- [ ] `MIGRATION_NOTES.md` bevat migratienotes voor de actuele `DDO_SCHEMA_VERSION`.
- [ ] `php scripts/release-check.php` slaagt zonder fouten.
