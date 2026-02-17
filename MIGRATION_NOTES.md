# Migration Notes

## Schema 1.4.0
- Voegt `{$wpdb->prefix}ddo_pageviews_data` toe voor opslag van pageview-metrics (`metric_date`, `page_path`, `pageviews`, `source`).
- Bevat indexen op `metric_date`, `page_path`, `source` en samengestelde indexen voor snellere aggregatie op datum/pagina/source combinaties.
- Introduceert batch-opslag via `ddo_store_pageviews_rows()` met validatie en prepared statements.

## Schema 1.3.0
- Bevat het scoringsmodel met `is_scored` als leidende kolom voor scored/unscored semantiek.
- Zorg dat bestaande feedbackdata op `score = 0` correct naar `is_scored = 0` en `score = NULL` migreert.
