# Testplan – Drie kritieke stromen

## 1) Feedback submit → opslag → summary zichtbaar

### Doel
Valideren dat een geldige feedbacksubmit via de API:
1. door permissiecontroles komt,
2. correct wordt opgeslagen,
3. zichtbaar wordt in geaggregeerde admin/summary output.

### Positieve checks
- Geldig gesigneerd request accepteert permissiecallback.
- Submit retourneert `success=true` met `feedback_id`.
- Feedback-rij wordt opgeslagen met juiste event/score-semantiek.
- `ddo_get_feedback_summary()` toont item count/event.
- `ddo_render_feedback_summary_cards()` rendert KPI’s snapshot-achtig.

### Negatieve checks
- Nonce-fout geeft `ddo_feedback_nonce_invalid`.
- Ongeldige/minimale payload geeft `ddo_feedback_payload_missing_field`.
- Lege dataset rendert exact: `Geen data in gekozen periode.`.

## 2) Scheduler run-now → metadata update → status render

### Doel
Borgen dat handmatige run-acties en scheduler-observability consistent zijn.

### Positieve checks
- Geldige run-now nonce resulteert in `notice=ok` en trigger van de juiste hook.
- `ddo_execute_scheduled_job()` schrijft success metadata (`last_success`, duur, lege foutmelding).
- `ddo_render_scheduler_status_block()` toont status `OK` en actie `Run now`.

### Negatieve checks
- Stale jobs worden als `Stale` gerenderd met verklarende oorzaak.
- Snapshot-achtige controle op admin notice-output voor nonce-fout.

## 3) Concept preview AJAX → fout/succes/live-region gedrag

### Doel
Valideren dat concept-preview gedrag contractueel stabiel blijft voor UI-koppeling.

### Positieve checks
- Geldige AJAX nonce geeft JSON success met summarytekst (`Concept ontvangen ...`).

### Negatieve checks
- Ongeldige AJAX nonce resulteert in 403 errorpad.
- Live-region contract blijft intact: container met
  `id=ddo-ajax-preview-response`, `role=status`, `aria-live=polite`, `aria-atomic=true`.

## Fixtures
- Centrale feedback-fixtures in `tests/fixtures/FeedbackFixtures.php` voor:
  - valide payload,
  - gesigneerd REST request,
  - representatieve feedbackdataset.
