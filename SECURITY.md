# Security Guidelines

## Threat model (kort)
- **Aanvalsoppervlak:** WordPress admin-instellingen, admin-post formulieren, AJAX endpoints en REST statusroute.
- **Waarschijnlijke dreigingen:** CSRF op admin-acties, privilege-escalatie zonder capability checks, XSS via ongeschoonde output, en gevoelige data-lekkage via feedback/analytics.
- **Beschermingsdoelen:** vertrouwelijkheid van API-keys, integriteit van configuratie en minimale opslag van gebruikers-/feedbackdata.

## Operationele richtlijnen
1. **Least privilege:** alle admin-acties vereisen `manage_options`.
2. **CSRF-bescherming:** alle form submits en AJAX calls gebruiken en valideren nonces.
3. **Input sanitization:** sanitize alle settings en concept/chatinvoer op server-side.
4. **Output escaping:** escape alle dynamische output in admin UI en responses.
5. **Dataminimalisatie en anonimisering:**
   - sla alleen noodzakelijke analytics/feedbackvelden op;
   - pseudonimiseer identifiers (hashing met WP salts);
   - vermijd opslag van ruwe tekst en PII tenzij expliciet nodig;
   - hanteer korte retentie en periodieke opschoning.
6. **API-key beheer:** bewaar API-keys in aparte opties, toon ze niet in logs en beperk toegang tot beheerders.
7. **Monitoring:** log alleen operationele metadata, geen secrets of direct herleidbare data.
