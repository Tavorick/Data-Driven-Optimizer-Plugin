Data-Driven Optimizer Plugin — Whitepaper & Stappenplan
Versie: 1.1
 Datum: April 16, 2025
 Auteur: ChatGPT (framework)

1. Executive Summary

## Handmatige testmatrix (compact)

| Scenario | Stappen | Verwacht resultaat |
|---|---|---|
| Instellingen opslaan | 1) Open **DD Optimizer > Instellingen**. 2) Wijzig minimaal 1 waarde. 3) Klik **Instellingen opslaan**. | Success-notice zichtbaar, waarden blijven behouden na refresh. |
| Scheduler run-now | 1) Open **DD Optimizer > Scheduler**. 2) Klik **Run now** (indien aanwezig). | Actie-notice bevestigt start/resultaat; scheduler-statusblok ververst zonder foutmelding. |
| AJAX preview succes/fout | 1) Open **Conceptinvoer**. 2) Trigger preview met geldige input. 3) Trigger preview met ongeldige/lege input of forceer fout. | Bij succes verschijnt preview-output in live region; bij fout verschijnt duidelijke foutmelding in live region. |
| Feedback leeg/gevuld | 1) Open **Feedback inzichten** in een lege omgeving. 2) Herhaal met aanwezige feedbackdata. | Leeg: duidelijke “geen data”-melding. Gevuld: samenvattingskaarten + eventtabel met waarden. |

## Definition of Done (UI tickets)

Een UI-ticket is pas gereed als alle onderstaande punten aantoonbaar zijn afgevinkt:

- **A11y verplicht**: labels, focus-states, keyboard-navigatie en live-regions (waar relevant) voldoen aan WCAG AA-baseline.
- **i18n verplicht**: alle zichtbare UI-strings staan in vertaalfuncties en gebruiken het plugin text-domain.
- **Responsive verplicht**: gedrag is gevalideerd op smalle en brede admin viewports zonder verlies van functionaliteit/leesbaarheid.
- **State handling verplicht**: loading/succes/fout/lege-status zijn expliciet afgehandeld in UI en feedbackberichten.

## Changelog (admin-UI wijzigingen)

Gebruik deze sectie om zichtbare admin-UI wijzigingen traceerbaar vast te leggen.

| Datum | Onderdeel | Wijziging | Impact voor gebruiker |
|---|---|---|---|
| 2026-02-17 | Releasebeheer | Pluginversie en constante verhoogd naar 1.2.4 en release-changelog aangevuld voor betere traceerbaarheid. | Duidelijke releasehistorie en consistente versieadministratie per afgeronde taak. |
| 2026-02-17 | Releasebeheer | Pluginversie en constante verhoogd naar 1.2.3 als afgeronde taak-bump. | Versiebeheer blijft synchroon en opleveringen zijn beter traceerbaar. |
| 2026-02-16 | QA & documentatie | Handmatige testmatrix toegevoegd, UI Definition of Done aangescherpt en render-output checks in tests uitgebreid. | Snellere regressiecontrole en duidelijkere acceptatiecriteria voor UI-tickets. |

## Admin UI conventions
- **Design tokens**: admin-styling gebruikt herbruikbare CSS-variabelen voor card-oppervlak (achtergrond, border, radius, shadow), spacing (`8/12/16/24`) en typografie (`heading/body/meta`).
- **Consistente componenttaal**: `.ddo-admin-wrap`, cards en tabelcontainers delen dezelfde card-surface zodat secties visueel één geheel vormen.
- **Interactiestates**: links, knoppen en tabelrijen hebben hover/focus states met subtiele kleurverschuiving en duidelijke focus-ringen voor toetsenbordnavigatie.
- **Responsive gedrag**: cards schalen naar één kolom op smallere schermen; tabelcontainers ondersteunen horizontale overflow zodat content leesbaar blijft op mobiele viewports.

## A11y-checklist voor admin UI wijzigingen
- [ ] Formuliervelden hebben een expliciete `<label for="...">` en contextuele hulptekst via `aria-describedby` waar relevant.
- [ ] Visuele statusindicatoren (zoals pills/badges) communiceren status ook via screenreader-tekst.
- [ ] Kleurcontrast van statuspills, notices en live-feedback voldoet minimaal aan WCAG AA voor normale tekst.
- [ ] Alle interactieve elementen zijn met toetsenbord bereikbaar en hebben een duidelijke `:focus-visible` staat.
- [ ] Dynamische statusberichten (zoals AJAX-resultaten) gebruiken passende live-region attributen (`role="status"`, `aria-live`).
- [ ] Wijzigingen zijn handmatig getest in de admin UI op toetsenbordnavigatie (Tab/Shift+Tab/Enter/Spatie).


## Huidige implementatiestatus (codebase)
- ✅ Basis plugin-bootstrap, activatie/deactivatie en module-loading.
- ✅ Settings API met losse opties (`ddo_enabled`, primaire/secundaire API-key).
- ✅ API-keys worden versleuteld opgeslagen met key-materiaal afgeleid van WordPress salts.
- ✅ Admin dashboard met conceptinvoer + AJAX-preview.
- ✅ WP-Cron orchestration voor 15-min fetch, weekly retrain en daily introspect.
- ✅ Database schema met tabellen voor FB/GA-data, concepten en feedback.
- ✅ REST status endpoint (`/ddo/v1/status`).
- ✅ Nieuwe feedback-flow: REST submit + feedbacksamenvatting endpoint en dashboard-inzichten.


## Eerste databron activeren: GA4 pageviews

Volg exact deze stappen om de eerste bron (GA4 pageviews) te activeren:

1. Open **WordPress Admin → DD Optimizer → Instellingen**.
2. Vul **GA4 Property ID** in met alleen cijfers (bijv. `123456789`).
3. Vul **GA4 service account JSON/tokenreferentie** in met je service account JSON of tokenreferentie.
4. Klik **Instellingen opslaan**.
5. Open **DD Optimizer → Scheduler** en start de fetch-job handmatig (Run now), of wacht op de eerstvolgende geplande run.

### Handmatige validatie (stap-voor-stap)

1. Controleer na een run in **Scheduler status** dat de job niet faalt op `ga4-missing-config`.
2. Controleer in **Recente scheduler events** dat je een succesvolle fetch ziet voor de bron `ga4`.
3. Open **DD Optimizer → Pageviews**.
4. Verifieer dat de card zichtbaar is met:
   - **Pageviews laatste 7 dagen**
   - **Top 5 page paths**
5. Valideer API-response handmatig als admin:

```bash
curl -X GET "https://example.com/wp-json/ddo/v1/pageviews/summary?days=7" \
  -H "X-WP-Nonce: <admin_nonce>"
```

Verwachte shape:

```json
{
  "days": 7,
  "totalPageviews": 1234,
  "topPages": [
    {
      "page_path": "/",
      "total_pageviews": 456
    }
  ]
}
```

## DDO REST API contracten

Alle routes vallen onder namespace `ddo/v1`.

### 1) `GET /wp-json/ddo/v1/status`

**Doel**
Geeft plugin-status, versie en enable-flag terug voor beheerdiagnostiek.

**Permissiemodel**
- `permission_callback`: `ddo_api_manage_options_permission`.
- Vereist capability: `manage_options` (typisch administrators).
- Zonder rechten geeft WordPress REST standaard `rest_forbidden` met status `401` (niet ingelogd) of `403` (wel ingelogd, onvoldoende rechten).

**Request voorbeeld**

```bash
curl -X GET "https://example.com/wp-json/ddo/v1/status" \
  -H "X-WP-Nonce: <admin_nonce>"
```

**Succesresponse (`200`)**

```json
{
  "plugin": "data-driven-optimizer",
  "version": "1.3.0",
  "enabled": true
}
```

**Mogelijke foutcodes**
- `401/403 rest_forbidden`: caller mist `manage_options`.

---

### 2) `POST /wp-json/ddo/v1/feedback`

**Doel**
Publieke ingest-route voor event feedback met score, hardening en rate limiting.

**Permissiemodel**
- `permission_callback`: `ddo_api_submit_feedback_permission`.
- Geen ingelogde admin vereist, maar request moet cryptografisch geldig zijn.
- Verplicht: `event`, `score`, `client_id`, `campaign_id`, `ad_id`, `nonce`, `timestamp`, `signature`.
- Signature: `HMAC-SHA256(nonce|timestamp|json_payload, secret)`.
- Timestamp-window: maximaal ±5 minuten.
- Throttling: max 30 requests per 5 minuten per IP+nonce+payload fingerprint.

**Request voorbeeld**

```bash
curl -X POST "https://example.com/wp-json/ddo/v1/feedback" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "cta_click",
    "score": 8,
    "client_id": "visitor-42",
    "campaign_id": "campaign-spring-2025",
    "ad_id": "ad-17",
    "nonce": "AbCdEfGh12345678",
    "timestamp": 1737026400,
    "signature": "9f1ed5b74f5e2f8f5d4bc4f0bc5d8b3911d51aef68d2b0bcf4b4f8a2b46f8b0f"
  }'
```

**Succesresponse (`200`)**

```json
{
  "success": true,
  "feedback_id": 123
}
```

**Validatiefoutvoorbeeld (`422`)**

```json
{
  "code": "ddo_feedback_score_range_invalid",
  "message": "Het veld \"score\" moet tussen 0 en 10 liggen.",
  "data": {
    "status": 422,
    "param": "score"
  }
}
```

**Mogelijke foutcodes**
- `400 ddo_feedback_payload_invalid|missing_field|too_small`: payload mist minimumvoorwaarden.
- `413 ddo_feedback_payload_too_large`: te veel velden of payload > 4096 bytes.
- `422 ddo_feedback_event_*|score_*|*_length_invalid|*_value_invalid`: veldvalidatie.
- `403 ddo_feedback_nonce_invalid|timestamp_invalid|signature_invalid|signature_mismatch`: hardening/signature checks gefaald.
- `429 ddo_feedback_rate_limited`: throttling geactiveerd.
- `500 ddo_feedback_insert_failed`: opslagfout in database.

---

### 3) `GET /wp-json/ddo/v1/feedback/summary`

**Doel**
Geeft geaggregeerde feedbackinzichten terug (`totals`, top events, recente items).

**Permissiemodel**
- `permission_callback`: `ddo_api_manage_options_permission`.
- Vereist capability: `manage_options`.

**Request voorbeeld**

```bash
curl -X GET "https://example.com/wp-json/ddo/v1/feedback/summary" \
  -H "X-WP-Nonce: <admin_nonce>"
```

**Succesresponse (`200`)**

```json
{
  "totals": {
    "count": 36,
    "averageScore": 7.4,
    "highestScore": 10,
    "lowestScore": 3,
    "unscored": 5
  },
  "events": [
    {
      "event_name": "cta_click",
      "total_items": 21,
      "average_score": 7.9
    }
  ],
  "recent": [
    {
      "id": 321,
      "event_name": "cta_click",
      "score": 8,
      "feedback_date": "2026-02-16",
      "status": "open",
      "campaign_id": "campaign-spring-2025",
      "ad_id": "ad-17"
    }
  ],
  "filters": {
    "days": 30,
    "sort": "count_desc"
  }
}
```

**Mogelijke foutcodes**
- `401/403 rest_forbidden`: caller mist `manage_options`.

---

### 4) `GET /wp-json/ddo/v1/pageviews/summary`

**Doel**
Geeft een compacte pageviews-samenvatting terug voor het dashboard (totaal + top pagina's).

**Permissiemodel**
- `permission_callback`: `ddo_api_manage_options_permission`.
- Vereist capability: `manage_options`.

**Query params**
- `days` (optioneel, integer): aantal dagen terug.
- Default: `7`.

**Request voorbeeld**

```bash
curl -X GET "https://example.com/wp-json/ddo/v1/pageviews/summary?days=7" \
  -H "X-WP-Nonce: <admin_nonce>"
```

**Succesresponse (`200`)**

```json
{
  "days": 7,
  "totalPageviews": 1234,
  "topPages": [
    {
      "page_path": "/pricing",
      "total_pageviews": 240
    }
  ]
}
```

**Mogelijke foutcodes**
- `401/403 rest_forbidden`: caller mist `manage_options`.

## Scoremodel en `unscored`-semantiek

### Definitie
- `score` is numeriek en alleen geldig binnen **0..10**.
- `is_scored` is de **leidende waarheid** voor scored/unscored status.
- Een item telt als **unscored** wanneer `is_scored = 0` (en in migratie-normalisatie ook `score = NULL`).

### Aggregatiegedrag
- `averageScore`, `highestScore`, `lowestScore` gebruiken alleen records met `is_scored = 1`.
- `unscored` (API veld) / `unscored_items` (SQL alias) telt records met `is_scored = 0`.
- Event-gemiddelden gebruiken uitsluitend gescoorde records binnen hetzelfde event.

### Migratie-impact
Bij schema-installatie/upgrade draait `ddo_migrate_feedback_scoring_model()` om legacy-data te normaliseren:
1. Legacy regels met impliciete “geen score” (`score = 0` + lege `feedback_text`) worden `is_scored = 0` en `score = NULL`.
2. Records met bestaande score maar zonder expliciete flag krijgen `is_scored = 1`.
3. Records zonder score worden altijd `is_scored = 0`.
4. Records met `is_scored = 0` krijgen altijd `score = NULL` om ambiguïteit uit te sluiten.

**Praktisch gevolg:** integraties die historisch `score = 0` als “niet beoordeeld” interpreteerden, moeten overschakelen op `is_scored`/`unscored` semantiek.

## Compatibiliteitsregels (SemVer voor deze plugin)

### Patch (`x.y.Z`)
Alleen bugfixes en interne wijzigingen zonder contractbreuk, bijvoorbeeld:
- Correctie van validatiebericht of loggingtekst.
- Prestatie-optimalisatie zonder wijziging van routepad, response keys, permissies of scoremodel.

### Minor (`x.Y.z`)
Backwards-compatible uitbreidingen:
- Nieuwe optionele responsevelden.
- Nieuwe route of queryfilter die bestaande clients niet breekt.
- Nieuwe admin-optie met veilige default.

### Major (`X.y.z`)
Breaking changes:
- Verwijderen/renamen van routes, responsevelden of foutcodes.
- Aanscherpen van permissies waardoor bestaande callers geweigerd worden.
- Wijziging in scoremodel of semantiek die output van KPI’s functioneel verandert.
- Datamigratie die oude clients/synchronisaties vereist aan te passen.

## Changelog & breaking changes

Gebruik dit formaat voor elke release:

```md
### [versie] - YYYY-MM-DD
- Type: patch|minor|major
- Breaking: ja|nee
- Wijzigingen:
  - ...
- Migratie-instructies:
  - ...
```

### [1.2.4] - 2026-02-17
- Type: **patch**
- Breaking: **nee**
- Wijzigingen:
  - Plugin header `Version` en `DDO_PLUGIN_VERSION` verhoogd naar `1.2.4`.
  - README changelog uitgebreid met taakgerichte release-notitie voor versiebeheer.
- Migratie-instructies:
  - Geen.

### [1.3.0] - 2026-02-16
- Type: **major**
- Breaking: **ja**
- Wijzigingen:
  - `is_scored` is leidend gemaakt voor scored/unscored interpretatie.
  - Aggregaties rapporteren expliciet `unscored` op basis van `is_scored = 0`.
  - Feedback ingest vereist hardeningvelden (`nonce`, `timestamp`, `signature`) voor permissiecheck.
- Migratie-instructies:
  - Update clients op `/feedback` zodat hardeningvelden worden meegestuurd.
  - Behandel `score = NULL` + `is_scored = 0` als “unscored”; gebruik **niet** meer `score = 0` als sentinel.
  - Valideer dashboards/exports op gewijzigde KPI-betekenis (`averageScore` alleen over gescoorde items).

## Lokale tests draaien (kritieke paden)

```bash
composer install
vendor/bin/phpunit --testsuite "DDO Plugin Tests"
```

Deze testset dekt:
- settings sanitization (`ddo_sanitize_enabled`, `ddo_sanitize_api_key`)
- REST route registratie + permission callback
- feedback payload prep + score clamping
- cron event registratie + clearing
- expliciete testdata-isolatie voor `ddo_feedback`

## Migratie van API-key opslag (v1.1+)
- `ddo_api_key_primary` en `ddo_api_key_secondary` worden bij opslaan versleuteld opgeslagen.
- Bestaande plaintext API-keys worden automatisch gemigreerd:
  - éénmalig tijdens activatie/opstart (upgradepad), en
  - als veilige fallback tijdens uitlezen (migratie-on-read).
- In de instellingen-UI worden bestaande sleutels niet teruggetoond; laat een wachtwoordveld leeg om de huidige sleutel ongewijzigd te laten.
- Rollback-gedrag: als je teruggaat naar een oudere pluginversie zonder decryptielaag, dan zijn de opgeslagen (versleutelde) sleutels niet direct bruikbaar totdat je opnieuw plaintext sleutels invoert of terugkeert naar een versie met decryptie-ondersteuning.

Dit document biedt een gedetailleerd overzicht en stappenplan voor de ontwikkeling van de Data-Driven Optimizer (DDO) WordPress-plugin. De plugin:
Verzamelt en analyseert data uit Facebook (inclusief Pixel), Google Ads & Analytics en je WordPress-omgeving.


Voert NLP- en beeldanalyse uit op advertenties, blogs en landingspagina’s.


Genereert automatisch conceptcontent via de ChatGPT API.


Biedt een semi-automatische review- en goedkeuringsworkflow.


Bevat een zelflerende module die op basis van feedback en performance aanbevelingen verfijnt.


Heeft introspectieve functionaliteit: de plugin analyseert eigen codebase en doet voorstellen voor optimalisaties of uitbreidingen.


Integreert een chatinterface voor directe communicatie met het systeem.



2. Introductie en Doelstellingen
Achtergrond: Als professioneel hypnotherapeut wil je marketingautomatisering en optimalisatie benutten, maar wel controle behouden.


Hoofddoel: Bouw een WordPress-plugin die:


Data integreert en analyseert uit Facebook, Google en WordPress.


Content en advertenties analyseert (tekst + afbeeldingen).


Conceptcontent genereert via ChatGPT.


Een reviewflow biedt waarin jij final control hebt.


Een zelflerende feedbackloop bevat die aanbevelingen verfijnt.


Zelf code-introspectie kan uitvoeren en verbetervoorstellen genereert.


Een chatinterface aan admin dashboard toevoegt voor interactieve dialoog.



3. Functionele Requirements
API-Integratie


Facebook Marketing API (advertenties + Pixel)


Google Ads & Analytics API


Content Analyse


Tekstanalyse (NLP via ChatGPT / NLP-tools)


Beeldanalyse (Google Cloud Vision of equivalent)


Concept Generatie


ChatGPT API voor blog- en advertentieconcepten


Review Workflow


Concepten opgeslagen als drafts in WordPress


UI met “Goedkeuren & Publiceren”/* “Bewaren als concept” knoppen


Zelflerend Systeem


Feedback logging (tabel ddo_feedback)


ML-module voor parameteroptimalisatie


Code Introspectie


Module die eigen plugin-codestructure uitleest


Analyseert codekwaliteit, detecteert antipatterns en doet verbetervoorstellen


Chat Interface


Geïntegreerde chatwidget in admin-dashboard


Communiceert met ChatGPT API voor vragen over plugin, status of uitbreidingsvoorstellen


Beveiliging & GDPR


Versleutelde opslag van API-sleutels


Anonimisering waar nodig, cookie-consent



4. Niet-Functionele Requirements
Performance: WP‑Cron max. 1× per 15 min voor zware data‑calls


Schaalbaarheid: Custom tables met indexen; code-introspectie lichtgewicht


Onderhoudbaarheid: Modulaire PSR-standaarden, duidelijke docstrings en comments


UI/UX: Responsief dashboard, duidelijke workflow, intuïtieve chatinterface



5. Systeemarchitectuur
5.1 Architectuuroverzicht
[Facebook API]   [Google API]   [WordPress REST]    
    ↓                 ↓              ↓                
   ETL → Database → Analyse Modules → Dashboard w/ Chat UI
                 ↘ ChatGPT API → Concept Mgmt → Review
                 ↘ Vision API  → Image Analysis
                 ↘ ML Feedback Loop → Parameter Update
                 ↘ Code Introspectie Module → Recommender

5.2 Current architecture (exacte require_once volgorde)
Onderstaande modules zijn **daadwerkelijk geïmplementeerd** en worden geladen via `data-driven-optimizer.php`:

1. `includes/settings.php`
2. `includes/admin-dashboard.php`
3. `includes/api-handlers.php`
4. `includes/ml-feedback.php`
5. `includes/code-introspect.php`
6. `includes/logger.php`
7. `includes/cron.php`
8. `includes/db-schema.php`

Aanwezige front-end assets in de repository:

- `assets/css/admin.css`
- `assets/js/admin.js`



6. Gedetailleerd Ontwerp
6.1 Plugin Structuur (huidig)
wp-content/plugins/data-driven-optimizer/
├─ data-driven-optimizer.php
├─ includes/
│   ├─ admin-dashboard.php
│   ├─ api-handlers.php
│   ├─ code-introspect.php
│   ├─ cron.php
│   ├─ db-schema.php
│   ├─ logger.php
│   ├─ ml-feedback.php
│   └─ settings.php
└─ assets/
    ├─ css/
    └─ js/

6.2 Code Introspectie Module
code-introspect.php: scant pluginmap


Berekent statische metrics (complexiteit, duplicatie) via PHP-parser


Stuurt bevindingen naar ChatGPT API voor aanbevelingen


6.3 Admin Chat Interface
Nog niet geïmplementeerd als aparte module. Chatfunctionaliteit staat op de roadmap (zie sectie 7).


6.4 Workflow en Cron
Schedules: `ddo_hourly_fetch` (draait elke 15 minuten), `ddo_weekly_retrain`, `ddo_daily_introspect`, `ddo_daily_feedback_cleanup`


ETL: halen, transformeren, opslaan data + content extraction


6.5 Dashboard UI
Tabs: Dashboard, Concepts, Code Insights, Chat


Components: Grafieken (Chart.js), concepttabellen, code-analyse rapporten, chatpaneel



7. Implementatie Roadmap

## 7.1 Statuslabels
- **Done**: aanwezig in codebase.
- **In Progress**: deels aanwezig, nog niet compleet.
- **Planned**: nog niet aanwezig in codebase.

## 7.2 Roadmap per module
- **Done** — `includes/settings.php` (instellingen + beveiligde API-key opslag)
- **Done** — `includes/admin-dashboard.php` (admin-scherm + AJAX-preview)
- **Done** — `includes/api-handlers.php` (REST endpoints)
- **Done** — `includes/ml-feedback.php` (feedbackverwerking + retrain hook)
- **Done** — `includes/code-introspect.php` (introspectiejob)
- **Done** — `includes/logger.php` (centrale logging)
- **Done** — `includes/cron.php` (scheduler + callbacks; 15-minuten interval)
- **Done** — `includes/db-schema.php` (custom tables + upgrades)
- **Planned** — `includes/chatgpt-integration.php`
- **Planned** — `includes/content-analysis.php`
- **Planned** — `includes/image-analysis.php`
- **Planned** — `includes/admin-chat.php`
- **Planned** — `includes/facebook-handler.php`
- **Planned** — `includes/google-handler.php`

## 7.3 Statuswijzigingen (changelog)
- **2026-02-16** — Documentatie opgeschoond: module-overzichten afgestemd op daadwerkelijke bestanden in `includes/` en `assets/`; niet-bestaande modules verplaatst naar roadmap met statuslabels.
- **2026-02-16** — Terminologie geharmoniseerd: “hourly fetch” in documentatie vervangen door “15-minuten fetch” (cron hooknaam `ddo_hourly_fetch` blijft ongewijzigd voor backwards compatibility).


8. Technologie Stack
Backend: PHP 7.4+, WordPress Coding Standards


Frontend: React of Vue.js, Chart.js, custom JS-widgets


Database: MySQL (WP-DB) met custom tables


APIs: OpenAI ChatGPT, Facebook Marketing, Google Ads/Analytics, Google Cloud Vision


ML: Extern Python-script of PHP ML-lib voor parametertraining



9. Security & Compliance
API Keys: Versleuteld in wp_options, capability checks


GDPR: Anonimisering, privacybeleid, cookie-consent-tools


Access Control: Alleen manage_options gebruikers voor settings, review, chat



10. Monitoring & Onderhoud
Logging: API-call fouten, ETL-errors, introspectie alerts


Updates: WordPress plugin versiebeheer + changelog


Documentatie: Inline comments, function-docblocks, gebruikershandleiding



11. Conclusie
Dit whitepaper en stappenplan leiden je door de ontwikkeling van een geavanceerde, zelflerende en introspectieve WordPress-plugin met geïntegreerde chatinterface. Volg de roadmap en ontwerpprincipes om een robuuste oplossing te bouwen die jou helpt marketing en contentcreatie te optimaliseren terwijl je volledige controle behoudt.

12. Bijlagen
User Stories


Als gebruiker wil ik data uit FB en Google automatisch laten verzamelen.


Als gebruiker wil ik conceptblogposts ontvangen die ik kan reviewen.


Als gebruiker wil ik dat het systeem uit eigen code optimalisatievoorstellen doet.


Als gebruiker wil ik direct via een chatinterface vragen kunnen stellen over de plugin.


Voorbeeld Cron Jobs


ddo_hourly_fetch


ddo_weekly_retrain


ddo_daily_introspect



Einde Document
