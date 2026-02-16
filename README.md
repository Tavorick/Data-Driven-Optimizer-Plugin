Data-Driven Optimizer Plugin — Whitepaper & Stappenplan
Versie: 1.1
 Datum: April 16, 2025
 Auteur: ChatGPT (framework)

1. Executive Summary

## Admin UI conventions
- **Design tokens**: admin-styling gebruikt herbruikbare CSS-variabelen voor card-oppervlak (achtergrond, border, radius, shadow), spacing (`8/12/16/24`) en typografie (`heading/body/meta`).
- **Consistente componenttaal**: `.ddo-admin-wrap`, cards en tabelcontainers delen dezelfde card-surface zodat secties visueel één geheel vormen.
- **Interactiestates**: links, knoppen en tabelrijen hebben hover/focus states met subtiele kleurverschuiving en duidelijke focus-ringen voor toetsenbordnavigatie.
- **Responsive gedrag**: cards schalen naar één kolom op smallere schermen; tabelcontainers ondersteunen horizontale overflow zodat content leesbaar blijft op mobiele viewports.


## Huidige implementatiestatus (codebase)
- ✅ Basis plugin-bootstrap, activatie/deactivatie en module-loading.
- ✅ Settings API met losse opties (`ddo_enabled`, primaire/secundaire API-key).
- ✅ API-keys worden versleuteld opgeslagen met key-materiaal afgeleid van WordPress salts.
- ✅ Admin dashboard met conceptinvoer + AJAX-preview.
- ✅ WP-Cron orchestration voor 15-min fetch, weekly retrain en daily introspect.
- ✅ Database schema met tabellen voor FB/GA-data, concepten en feedback.
- ✅ REST status endpoint (`/ddo/v1/status`).
- ✅ Nieuwe feedback-flow: REST submit + feedbacksamenvatting endpoint en dashboard-inzichten.

## REST API voorbeelden: feedback

### POST `/wp-json/ddo/v1/feedback`

Request:

```bash
curl -X POST "https://example.com/wp-json/ddo/v1/feedback" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{
    "event": "cta_click",
    "score": 8,
    "client_id": "visitor-42",
    "campaign_id": "campaign-spring-2025",
    "ad_id": "ad-17"
  }'
```

Succes response (`200`):

```json
{
  "success": true,
  "feedback_id": 123
}
```

Validatiefout response (`400/422`):

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
