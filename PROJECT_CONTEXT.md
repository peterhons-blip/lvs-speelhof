# LVS Speelhof — technische projectcontext

> Bijgewerkt op 11 november 2026 op basis van de volledige lokale Codex-analyse (checkout `main`, commit `bbb5831`) en de eerdere GitHub-inventarisatie. **Documentatie, geen live statuscontrole of functionele audit.** Controleer bij elke nieuwe taak de actuele code. De databankbevindingen zijn afkomstig uit alleen-lezen metadata-inspectie van de lokaal ingestelde MySQL-databank, niet uit GitHub.

## 1. Doel, omgeving en werkwijze

Leerlingvolgsysteem voor GO! Atheneum Sint-Truiden, campus Speelhof, met voorbereiding op meerdere scholen.

- Repository: https://github.com/peterhons-blip/lvs-speelhof
- Branch: `main`; wijzigingen liefst via feature/fix-branch en Pull Request.
- Publieke toepassing: https://lvs.atheneumsinttruidencampusspeelhof.be/
- Ontwikkeling: VS Code op Windows, XAMPP; productie haalt code binnen uit GitHub.
- Laravel 12 (lokaal vastgesteld 12.34.0), PHP 8.2+ (lokaal 8.2.12), MySQL, Eloquent en query builder.
- UI: Blade, `public/css/lvs.css`, `public/js/lvs.js`; Vite/Tailwind/Axios staan in het project maar worden niet via de actieve layout geladen.
- E-mail: Laravel Mailable via SMTP. Een queueconfiguratie is aanwezig maar de huidige zakelijke processen worden direct uitgevoerd.
- AI-ontwikkelafspraken staan in `AGENTS.md`. Dit document beschrijft **hoe het LVS nu werkt**; `AGENTS.md` beschrijft **hoe wijzigingen worden uitgevoerd**.

## 2. Applicatie-architectuur

Monolithische Laravel-app met webfunctionaliteit en nachtelijke Artisan-commando's.

| Onderdeel | Bestanden | Rol |
| --- | --- | --- |
| Routes | `routes/web.php`, `routes/console.php` | Webtoegang en scheduler |
| OAuth | `app/Http/Controllers/Auth/SmartschoolController.php` | Smartschool-login, userinfo, lokaal account, sessie |
| Middleware | `app/Http/Middleware/EnsureSmartschoolAuthenticated.php` | Controle op `session('ss_user')` |
| Leerlingen | `app/Http/Controllers/LeerlingenController.php` | Lijst, detail, verjaardagen |
| Meldingen | `app/Http/Controllers/MeldingController.php` | Meldingen bekijken en toevoegen |
| Modellen | `app/Models/` | Scholen, klassen, leerlingen, users, meldingen, soorten, categorieën |
| SOAP-service | `app/Services/SmartschoolSoap.php` | Berichten, co-accounts, klassen en accounts ophalen |
| Sync | `app/Console/Commands/SmartschoolSyncLeerlingen.php` | Smartschoolklassen en -leerlingen in MySQL bijwerken |
| 18-jaarflow | `app/Console/Commands/CheckLeeftijdLeerlingen.php` | Voorafberichten, accountaanpassingen, overzichten |
| Verjaardagen | `app/Console/Commands/CheckVerjaardagen.php` | Felicitaties voor leerlingen en personeel |
| Mail | `app/Mail/LeerlingenWorden18.php` | E-mailoverzicht |
| Templates | `resources/views/` | Webpagina's, e-mails, Smartschool-berichten |

Er zijn geen uitgewerkte eigen policies, queue-jobs of afzonderlijke domeinservices aangetroffen. De webcontrollers en Artisan-commando's bevatten de meeste bedrijfslogica.

## 3. Webroutes en functionaliteit

| Methode | Pad | Functie |
| --- | --- | --- |
| GET | `/` | Publieke welkomstpagina |
| GET | `/login` | Smartschool OAuth starten |
| GET | `/auth/smartschool/callback` | OAuth-code verwerken |
| GET | `/logout` | `ss_user` uit sessie verwijderen |
| GET | `/leerlingen` | Actieve leerlingen zoeken (sessie vereist) |
| GET | `/leerlingen/verjaardagen` | Jarigen van vandaag t/m +7 dagen (sessie vereist) |
| GET | `/leerlingen/{id}` | Leerlingfiche en meldingen (sessie vereist) |
| GET/POST | `/meldingen/add/{id}` | Meldingen bekijken/toevoegen (sessie vereist) |

Daarnaast bestaan test-/diagnostische Smartschool-routes en een `/fake-login`-route in `routes/web.php`; laat deze ongemoeid bij niet-gerelateerde wijzigingen. Hun aanwezigheid is **geen** toestemming om ze te gebruiken tegen productie. Laravel heeft ook de health-route `/up`.

De navigatie verwijst naar `/ema`, maar bij de analyse bestond daarvoor nog geen uitgewerkte route, model of controller. `HomeController` is niet in gebruik via de huidige routes. Er zijn geen eigen API-routes aangetroffen.

### Leerlingen en meldingen
- Lijst en fiche filteren op `leerlingen.active = 1`.
- Leerlingenlijst brengt actieve leerlingen in de pagina; JavaScript zoekt lokaal op naam of klas, vanaf twee tekens of met een klas-prefix.
- De verjaardagspagina houdt rekening met jaarwisselingen; periode inclusief vandaag en zeven volgende dagen.
- Meldingen hebben categorie, soort, gebeurtenisdatum en commentaar; de gekozen soort moet bij de categorie horen; datum niet in de toekomst, commentaar minstens vijf tekens.
- Meldingen toevoegen stuurt geen automatisch Smartschool-bericht/e-mail; bewerken, verwijderen en escaleren ontbreken.
- Het veld `warn_after` bij meldingssoorten is aanwezig maar werd niet actief gebruikt.
- Bij het opslaan wordt geen meldingsauteur vastgelegd.
- Meldingsroutes zoeken ook leerlingen op ID zonder dezelfde `active=1`-controle als de leerlingfiche.

## 4. Smartschool OAuth en sessies

De OAuth-flow:
1. Redirect naar Smartschool met scope `userinfo`.
2. Autorisatiecode omwisselen voor access token.
3. Smartschool-userinfo ophalen.
4. Lokaal `User` zoeken op `gebruikersnaam`.
5. Alleen login toelaten als `allowed_login = 1`.
6. Groepsnaam uit `groups` ophalen en identiteit in `session('ss_user')` plaatsen.

Het token wordt niet bewaard voor de nachtelijke SOAP-processen. De middleware controleert of `ss_user` bestaat; `Auth::login()` wordt niet gebruikt.

Huidige toegangsbeperkingen: groepsnamen worden getoond maar geven nog geen afzonderlijke rechten; schooltoegang op leerlingen en meldingen wordt niet per sessiegebruiker afgedwongen; `allowed_login` wordt niet opnieuw gecheckt in bestaande sessies. In de analyse werd ook vastgesteld dat `intended_url` niet door de callback wordt gebruikt en dat OAuth-sessiehardening nog aandacht verdient. Beoordeel autorisatie expliciet bij nieuwe functies.

## 5. MySQL-databank

**Codex heeft via metadataquery's 11 bestaande lokale MySQL-tabellen vastgesteld:**

| Tabel | Model | Relevante velden/functie |
| --- | --- | --- |
| `scholen` | `School` | Schoolnaam, nummers, Smartschool-configuratie, afzenders, ontvangers |
| `klassen` | `Klas` | `schoolid`, `klasnaam`, `smartschool_code` |
| `leerlingen` | `Leerling` | `schoolid`, `klasid`, namen, gebruikersnaam, geboortedatum, `e-mailadres`, `active` |
| `users` | `User` | Personeel, `schoolid`, `groupId`, `allowed_login`, Smartschool-naam, geboortedatum |
| `groups` | geen eigen model | Groepsnamen voor personeelsgebruikers |
| `meldingen` | `Melding` | `leerlingId`, `meldingssoortId`, `comment`, `gebeurdop` |
| `meldingscategorien` | `Meldingscategorie` | Categorieën |
| `meldingssoorten` | `Meldingssoort` | `meldingscategorieId`, naam, omschrijving, `warn_after` |
| `sessions` | framework | Sessieopslag |
| `smartschool_koppeling` | geen eigen model | Namen en Smartschool-gebruikersnaam |
| `geboortedatum_koppeling` | geen eigen model | Gebruikersnaam en geboortedatum |

Relaties: school → leerlingen/users; klas → leerlingen; leerling → school/klas; melding → leerling/soort; soort → categorie; user → school. Groepsinformatie wordt via join opgehaald.

Volgens de lokale metadata-analyse:
- Unieke index op `leerlingen(schoolid, gebruikersnaam)`.
- Unieke indexen op `klassen(schoolid, smartschool_code)`, met een duplicaat.
- Geen databank-foreign-keys, triggers of events vastgesteld.
- De twee koppeltabellen worden niet door de onderzochte code gebruikt.
- De drie aanwezige migraties in GitHub beschrijven slechts standaard Laravel-tabellen; het echte zakelijke LVS-schema ontbreekt. Er zijn lokaal ook geen `migrations`, `jobs`, `cache` en `failed_jobs`-tabellen aangetroffen.

**Belangrijk:** gebruik niet zomaar `php artisan migrate` of een nieuw schema zonder eerst de concrete bestaande database en back-up te controleren. Een schone installatie op basis van GitHub-migraties reproduceert het volledige LVS momenteel niet. Documenteer bestaande kolommen en constraints wanneer databasewerk nodig is.

## 6. Smartschool SOAP

`SmartschoolSoap.php` bevat o.a.:

- `sendMessage()`: bericht met afzender, bijlagen, `copyToLVS`, co-account-index.
- `disableCoAccounts()`: probeert index 1–6 op `niet actief` te zetten.
- `enableCoAccount()`: één co-account activeren.
- `getClassListJson()`, `getAllAccountsExtended()`, `getAllGroupsAndClasses()`: klas-/gebruikersgegevens.
- `getUserDetails()` en foutcodehelpers.

Co-account 0 = hoofdaccount, index 1–6 = co-accounts.

**Multi-school is deels geïmplementeerd:**
- Sync gebruikt WSDL en accesscode van de geselecteerde school.
- Verjaardagen en 18-jaarflow gebruiken een algemene SOAP-client uit `config/services.php`, maar selecteren afzenders/ontvangers/templates per school.
- OAuth gebruikt globale configuratie, hoewel aanvullende schoolspecifieke OAuth-velden in de database aanwezig zijn.

Controleer daarom bij schooloverstijgende nieuwe functies niet enkel de `schoolid`-groep, maar ook het Smartschool-platform waarop een externe actie wordt uitgevoerd.

## 7. Leerlingensynchronisatie

`smartschool:sync-leerlingen`:
1. School en SOAP-instellingen ophalen.
2. Officiële klassen selecteren.
3. Klassen vinden of aanmaken per school + Smartschool-code.
4. Leerlingaccounts ophalen per klas.
5. Leerlingen aanmaken/updaten of opnieuw actief zetten.
6. Niet aangetroffen gebruikers aan het eind op `active=0` zetten.

Opties: `--schoolid=`, `--dry-run`, `--limit=`, `--only-class=`.

Naamconventie e-mail: `gebruikersnaam@atheneumsinttruiden.be`; datums worden uit verschillende formaten omgezet.

**Bekende aandachtspunten uit code-analyse:**
- Bij mislukte/incomplete SOAP-klasophaling kan einddeactivatie onterecht leerlingen uitschakelen.
- Bij combinatie `--limit` + `--only-class` is de deactivatiebescherming onvolledig.
- Geen transactie rond de volledige sync; gedeeltelijke wijzigingen kunnen blijven staan.
- Ontbrekende namen/geboortedatums kunnen botsen met verplichte DB-kolommen.
- Datum-parser controleert vooral formaat en niet alle kalendergeldigheid.
- Geen synchronisatie van personeelsgebruikers aangetroffen.

Voer een sync niet onvoorbereid uit tegen een echte database. `--dry-run` schrijft geen zakelijke data, maar benadert wel Smartschool en logt gegevens.

## 8. Scheduler en berichten

In `routes/console.php`, tijdzone `Europe/Brussels`, met `withoutOverlapping()` en eigen uitvoerlog:

| Tijd | Commando | Logbestand |
| --- | --- | --- |
| 01:00 | `leerlingen:check-18` | `storage/logs/leerlingen_check.log` |
| 02:00 | `verjaardagen:check` | `storage/logs/verjaardagen_check.log` |
| 03:00 | `smartschool:sync-leerlingen --schoolid=1` | `storage/logs/smartschool_sync_leerlingen.log` |

Berichten worden dus vóór de dagelijkse sync uitgevoerd. Alleen school 1 wordt automatisch gesynchroniseerd; berichtentaken kijken over scholen heen. De daadwerkelijke Windows-scheduler op productie is niet geverifieerd.

### 18-jaarsproces
- Exact 7 dagen vóór verjaardag: waarschuwing naar leerling en pogingen voor co-accounts 1–6.
- Op 18e verjaardag: leerlingbericht, co-accounts uitschakelen, secretariaatsbericht, Smartschool-overzicht en e-mailoverzicht per school.
- De selectie gebruikt momenteel geen `active`-filter.
- Als het eerste leerlingbericht faalt met SOAP-exceptie, kunnen volgende co-accountstappen voor die leerling uitblijven.
- `disableCoAccounts()` logt interne SOAP-fouten, maar de flow kan daarna toch succes registreren; overzichten zijn dus niet altijd een hard bewijs van effectieve wijziging.
- Geen blijvende 'al verzonden voor persoon en datum'-registratie; handmatig opnieuw uitvoeren kan dubbele berichten veroorzaken.

### Verjaardagen
- Felicitatie aan leerlingen via `gebruikersnaam`, personeel via `smartschool_gebruikersnaam`.
- Schoolafzender: beleid → algemene schoolafzender → fallback.
- 18-jarige leerlingen worden overgeslagen omdat het andere commando ze verwerkt.
- Ook verjaardagscommando filtert niet op `active` of `allowed_login`.
- `--date` selecteert jarigen voor gekozen datum maar berekent leeftijd deels tegen de werkelijke datum.
- Geen blijvende verzendregistratie.

### Testopties: werkelijke effecten

| Optie | Gevolg |
| --- | --- |
| `leerlingen:check-18 --test` | Geen bericht, mail of accountwijziging; wel initialisatie/logging |
| `leerlingen:check-18 --test-ss-overzicht` | **Verstuurt echte Smartschool-overzichten** |
| `--test` samen met `--test-ss-overzicht` | **Verstuurt nog steeds**, omdat de overzichtstak voorrang heeft |
| `verjaardagen:check --test` | Geen verzendaanroepen |
| `verjaardagen:check --dummy` of `--to` | Kan echte berichten versturen zonder `--test` |
| `smartschool:sync-leerlingen --dry-run` | Geen zakelijke DB-schrijfoperaties, wel externe reads/logging |

Ook sommige testmodi initialiseren een SOAP-client; beschouw ze niet als offline unit tests.

## 9. Berichten en frontend

- Er zijn zeven standaard Smartschool-berichttemplates en kopieën voor school 1 en 2.
- De bij de analyse gevonden schooltemplates waren inhoudelijk gelijk aan hun standaardvariant.
- School 2 heeft twee verjaardagtemplates met een dubbele extensie `*.blade.php.php`, waardoor de gewenste viewnaam niet matcht en de fallback wordt gebruikt.
- `public/js/lvs.js` wordt op alle pagina's geladen maar verwacht `LEERLINGEN_DATA` en zoekelementen die niet overal bestaan; dat kan JavaScript-fouten geven.
- Zoekresultaten gebruiken `innerHTML` met leerling-/klaswaarden; controleer escaping bij wijzigingen.
- De frontend gebruikt `localStorage` voor een geselecteerde leerling en wist dit bij uitloggen.

## 10. Kwaliteit, tests en open vragen

Codex controleerde lokaal de syntaxis van **41 PHP-bronbestanden** zonder syntaxisfouten. Er zijn alleen twee standaard Laravel-voorbeeldtests in `tests/` gevonden; geen zakelijke tests voor OAuth, SOAP, synchronisatie, meldingen of berichtverzending. Geen functionele HTTP-tests of echte Smartschool-verzendtests uitgevoerd.

Bij nieuwe functies prioriteit geven aan:
- Tests voor Smartschool-fouten en gedeeltelijke synchronisatie.
- Idempotentie van automatische berichten en correcte statusrapportage.
- Autorisatie per school en gebruikersgroep.
- Compleet versiebeheer van de echte DB-structuur.
- Stabiel laden van JavaScript per pagina en veilige HTML-output.
- Duidelijke scheiding tussen ontwikkel-, test- en productiegegevens.

Nog niet geverifieerd: actieve productiescheduler, daadwerkelijke bezorging van externe berichten, productie-back-ups, monitoring, deploymentstappen, volledige Git-geschiedenis op secrets en live gedragingen van de site.

## 11. Werkafspraken voor ChatGPT en Codex

1. Code in de actuele repository is de technische bron van waarheid; dit document is de kaart, niet de uitvoering.
2. Raadpleeg bij een taak de huidige relevante codebestanden. Neem geen verouderde bestandsinhoud aan.
3. Maak zo klein mogelijke, verklaarde wijzigingen met aandacht voor andere scholen en bestaande Smartschool-flows.
4. Gebruik bij voorkeur een aparte Git-branch en Pull Request. Geen ongevraagde push, merge, deploy of productieacties.
5. Geen echte leerlinggegevens, wachtwoorden of `.env`-inhoud in GitHub, documentatie of antwoorden opnemen.
6. Test waar mogelijk zonder externe effecten en maak expliciet onderscheid tussen syntaxcheck, unit test, integratietest en productiecontrole.
7. Onderscheid **bevestigd uit code**, **vastgesteld in lokale DB-metadata** en **nog niet geverifieerd**.
8. Update dit bestand als nieuwe functionaliteit, architectuur of datamodellen wezenlijk veranderen.
