# Security Guidelines

## Threat model (kort)
### Misbruikscenario's
1. **Publieke feedback endpoint abuse**
   - Botnet stuurt hoge volumes ondertekende requests om storage/kosten te verhogen of service te degraderen.
2. **Input poisoning / data quality attack**
   - Aanvaller probeert ongeldige identifiers/events in feedback te injecteren om rapportages te vervuilen of onverwachte output te forceren.
3. **Secret leakage naar logs/UI**
   - Gevoelige waarden (API-keys, tokens, signatures, hashes) lekken via debug-logging, foutmeldingen of admin-schermen.
4. **Admin action misuse**
   - CSRF of privilege bypass op admin-post/AJAX acties om jobs te triggeren of instellingen te wijzigen.

### Mitigaties
- **Sterke input-hygiëne:** consistente sanitize + validate op REST args, admin `$_GET`/`$_POST`, settings callbacks en server-side escaping in UI.
- **Rate limiting op ingest:** throttling op IP + payload fingerprint met expliciete `429` response op burst-abuse.
- **Secret hygiene:** API-keys versleuteld opgeslagen; gevoelige context en message patronen worden geredigeerd voor logging; UI toont alleen gemaskeerde key-status.
- **Toegangscontrole + anti-CSRF:** `manage_options` checks voor admin paths plus nonce-validatie voor admin-post/AJAX.
- **Dataminimalisatie:** alleen noodzakelijke feedbackvelden, gehashte client-identifiers en retentiebeleid met cleanup.

## Operationele richtlijnen
1. **Least privilege:** alle admin-acties vereisen `manage_options`.
2. **CSRF-bescherming:** alle form submits en AJAX calls gebruiken en valideren nonces.
3. **Input sanitization/validation:** sanitize én valideer alle settings, feedback- en admin-input server-side.
4. **Output escaping:** escape alle dynamische output in admin UI en responses.
5. **Dataminimalisatie en anonimisering:**
   - sla alleen noodzakelijke analytics/feedbackvelden op;
   - pseudonimiseer identifiers (hashing met WP salts);
   - vermijd opslag van ruwe tekst en PII tenzij expliciet nodig;
   - hanteer korte retentie en periodieke opschoning.
6. **API-key beheer:** bewaar API-keys in aparte opties, versleutel in opslag, toon nooit plaintext in UI/logs.
7. **Monitoring:** log alleen operationele metadata; redigeer secrets/hashes/tokens uit context en messages.
