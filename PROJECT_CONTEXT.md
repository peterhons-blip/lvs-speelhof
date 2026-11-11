# LVS Speelhof — technische projectcontext

> Status: eerste inventarisatie op 11 november 2026. Gebaseerd op een gerichte inspectie van de `main`-branch; geen volledige code-audit. Werk dit document bij wanneer architectuur, configuratie of deployment veranderen.

## Doel en repository

Leerlingvolgsysteem (LVS) voor GO! Atheneum Sint-Truiden, campus Speelhof, met Smartschool-integraties.

- Repository: https://github.com/peterhons-blip/lvs-speelhof
- Standaardbranch: `main`
- Framework: Laravel 12, PHP >= 8.2 (zie `composer.json`)
- Ontwikkeling: Visual Studio Code, GitHub als versiebeheer; productie haalt wijzigingen uit GitHub op.
- Productieomgeving die door de beheerder wordt gebruikt: Windows/XAMPP. Controleer de concrete configuratie per deployment; sla geen secrets op in dit document.

## Gecontroleerde codeonderdelen

| Onderdeel | Bestanden | Werking |
| --- | --- | --- |
| Webroutes | `routes/web.php` | Publieke startpagina, Smartschool OAuth-login/callback/logout, afgeschermde leerlingen- en meldingsroutes, plus enkele test-/debugroutes |
| OAuth-login | `app/Http/Controllers/Auth/SmartschoolController.php` | Wisselt OAuth-code om voor token, haalt userinfo op, controleert lokaal `User`-account en `allowed_login`, zet `ss_user` in sessie |
| Autorisatie | `app/Http/Middleware/EnsureSmartschoolAuthenticated.php` | Middleware genoemd in webroutegroep; werking bij wijzigingen afzonderlijk verifiëren |
| Leerlingen | `app/Models/Leerling.php`, `app/Http/Controllers/LeerlingenController.php` | Leerlinggegevens, relatie met klas/school, lijst, detail, verjaardagen |
| Meldingen | `app/Http/Controllers/MeldingController.php` | Meldingen met categorie/soort registreren en per leerling bekijken |
| Smartschool SOAP | `app/Services/SmartschoolSoap.php` | Berichten sturen, co-accounts activeren/deactiveren, gebruikers-/klasgegevens lezen |
| Leeftijd 18 | `app/Console/Commands/CheckLeeftijdLeerlingen.php` | Waarschuwing 7 dagen vooraf, acties op 18e verjaardag, berichten en schooloverzichten |
| Synchronisatie | `app/Console/Commands/SmartschoolSyncLeerlingen.php` | Leerlingen uit Smartschool synchroniseren; details nog verder te documenteren |
| Verjaardagen | `app/Console/Commands/CheckVerjaardagen.php` | Verjaardagscontrole; details nog verder te documenteren |

## Geplande taken

In `routes/console.php`, met tijdzone `Europe/Brussels` en `withoutOverlapping()`:

- 01:00 — `php artisan leerlingen:check-18`; log `storage/logs/leerlingen_check.log`
- 02:00 — `php artisan verjaardagen:check`; log `storage/logs/verjaardagen_check.log`
- 03:00 — `php artisan smartschool:sync-leerlingen --schoolid=1`; log `storage/logs/smartschool_sync_leerlingen.log`

Controleer op de server dat Laravel's scheduler daadwerkelijk elke minuut wordt aangeroepen. Vermijd dubbele schedulers.

## Geverifieerde gegevensrelaties en naamgeving

- `Leerling` gebruikt tabel `leerlingen` en velden waaronder `gebruikersnaam`, `naam`, `voornaam`, `klasid`, `schoolid`, `geboortedatum`.
- `Leerling::klas()` is een `belongsTo` naar `Klas` via `klasid`; `Leerling::school()` is een `belongsTo` naar `School` via `schoolid`.
- `Leerling::wordtVandaag18()` en `wordtBinnen7Dagen18()` bepalen belangrijke automatiseringsmomenten.
- Meldingen verwijzen naar leerling via `leerlingId` en naar meldingssoort via `meldingssoortId` (`MeldingController`).
- OAuth-login vereist een lokaal gebruikersrecord op `gebruikersnaam` met `allowed_login = 1`.
- Dit is **geen** volledige databankcatalogus. Verifieer bestaande migraties en modellen voordat velden of tabellen worden gewijzigd.

## Smartschool-afspraken

- OAuth voor interactieve login; SOAP Webservices V3 voor berichten, co-accounts en synchronisatie.
- `SmartschoolSoap::sendMessage()` heeft parameters voor ontvanger, onderwerp, inhoud, afzender, LVS-kopie en co-accountnummer (`0` = hoofdaccount, `1..6` = co-accounts).
- `disableCoAccounts()` doorloopt co-accounts 1 tot 6.
- Leeftijd-18-commando ondersteunt `--test`, `--test-ss-overzicht` en `--dummy`. Lees vóór gebruik de precieze implementatie: niet alle testopties zijn equivalent.
- Voor leeftijd-18-berichten bestaan schoolspecifieke Blade-templates met fallback naar standaardtemplates.
- Verschillende afzenders en overzichtsontvangers worden per school geconfigureerd.

## Veiligheid en aandachtspunten

1. **Urgent:** in `routes/web.php` staat een publiek bereikbare `/fake-login`-route die direct `ss_user` in de sessie zet. Verwijderen of strikt tot lokaal testen beperken vóór productiegebruik.
2. In datzelfde bestand staan Smartschool-test-/debugroutes buiten de Smartschool-authenticatiemiddleware. Sommige versturen berichten of tonen gebruikersgegevens. Beveilig ze en verwijder ze uit productie.
3. Controleer OAuth-flow en sessiebeveiliging, met name `state`/CSRF-bescherming en sessieregeneratie na login.
4. Leg nooit `.env`, tokens, SOAP-accesscodes, wachtwoorden, echte leerlingdata of productie-databankdumps vast in GitHub of deze documentatie.
5. Beperk diagnostische logging met leerlingidentificatoren en persoonlijke gegevens.
6. Controleer wijzigingen in schooloverstijgende functies altijd op correcte scheiding per school.

Deze punten zijn **controlepunten**, geen verklaring dat alle overige onderdelen veilig zijn.

## Ontwikkel- en wijzigingsworkflow

1. Start een wijziging met een korte omschrijving van gewenst gedrag en betrokken gebruikers.
2. Lees vanuit GitHub eerst de **actuele** bestanden op `main`; vertrouw niet op een verouderde momentopname of eerder gesprek.
3. Inventariseer routes, modellen, migraties, controllers, services, views en tests die geraakt worden.
4. Werk bij voorkeur op een aparte `feature/`- of `fix/`-branch; wijzig `main` niet rechtstreeks zonder expliciete afspraak.
5. Behoud bestaande compatibiliteit en multi-schoolgedrag; vermijd onbedoelde wijzigingen in Smartschool-berichten of co-accounts.
6. Test lokaal met fictieve persoonsgegevens en zonder echte Smartschool-berichten. Beschrijf exact welke testopties geen externe acties uitvoeren.
7. Controleer minimaal `php artisan test`, relevante artisan-commando's in veilige testmodus en, indien van toepassing, `php artisan route:list`.
8. Review de diff en merge pas daarna. Productie-update, migraties, cache-opschoning en schedulercontrole zijn afzonderlijke handelingen.
9. Pas deze projectcontext aan bij nieuwe functies, datamodelveranderingen of deploymentaanpassingen.

## Nog te onderzoeken

- Volledige lijst migraties, modellen en tabellen, inclusief rechten per gebruikersgroep.
- `EnsureSmartschoolAuthenticated` en eventuele andere middleware.
- Mechanisme waarmee Laravel-scheduler op Windows-productie wordt gestart.
- Deploymentmethode, rollback, back-ups en foutmeldingsmonitoring.
- Geautomatiseerde tests en testdatabankconfiguratie.
- Betrouwbaarheid/idempotentie van herhaalbare Smartschool-acties en synchronisatie.
- Mogelijke secrets in de volledige Git-geschiedenis (niet enkel huidige bestanden).

## Werkafspraak met ChatGPT

Deze repository is de bron van waarheid voor code. Dit document dient als oriëntatie; het vervangt nooit inspectie van de actuele code. Bij voorstellen altijd expliciet onderscheiden: **bevestigd uit code**, **veronderstelling** en **aanbevolen wijziging**. Externe berichten, accountaanpassingen, wijzigingen aan GitHub en productiehandelingen worden alleen uitgevoerd na passende opdracht en controle.
