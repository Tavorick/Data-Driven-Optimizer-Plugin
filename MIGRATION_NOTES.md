# Migration Notes

## Schema 1.3.0
- Bevat het scoringsmodel met `is_scored` als leidende kolom voor scored/unscored semantiek.
- Zorg dat bestaande feedbackdata op `score = 0` correct naar `is_scored = 0` en `score = NULL` migreert.
