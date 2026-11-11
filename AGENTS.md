# AGENTS.md — LVS Atheneum Sint-Truiden

Deze instructies gelden voor AI-codeassistenten die in deze repository werken. Communiceer standaard in het Nederlands met de beheerder.

## Project en context
- Lees bij de start van een nieuwe taak eerst `PROJECT_CONTEXT.md`, en controleer daarna de **actuele broncode**. Documentatie kan achterlopen.
- Dit is een Laravel 12/PHP 8.2+ leerlingvolgsysteem, met MySQL, Blade, eigen CSS/JavaScript, Smartschool OAuth en SOAP.
- Ontwikkelomgeving: VS Code op Windows met XAMPP. GitHub is broncodebeheer; productie wordt afzonderlijk bijgewerkt vanuit GitHub.
- De productieomgeving is beschikbaar via `https://lvs.atheneumsinttruidencampusspeelhof.be/`. Een publieke URL is geen toestemming om productieacties uit te voeren.
- Bestaande functies: leerlingen, klassen, scholen, meldingen, verjaardagen, 18-jaarsproces, Smartschool-synchronisatie en Smartschool-berichten.
- Respecteer de bestaande Nederlandstalige naamgeving van velden, routes en gebruikersinterface.

## Werkafspraken
1. Vraag bij onduidelijke functionele eisen kort door, maar begin bij duidelijke opdrachten met de relevante bestanden te inspecteren.
2. Identificeer vóór een wijziging alle betrokken routes, controllers, modellen, Blade-views, JavaScript, services, configuratie en taken.
3. Hergebruik bestaande code. Maak kleine, gerichte wijzigingen; voer geen onverwachte herstructurering of upgrades uit.
4. Werk bij voorkeur op een aparte `feature/`, `fix/` of `docs/` Git-branch. Push, merge en deploy niet zonder expliciete opdracht.
5. Licht achteraf kort toe wat is gewijzigd, welke bestanden zijn geraakt, welke tests zijn uitgevoerd en welke risico's overblijven.
6. Houd `PROJECT_CONTEXT.md` bij wanneer belangrijke architectuur- of werkafspraken veranderen, maar wijzig die documentatie niet louter om een kleine bugfix te registreren.
7. Beschouw productiecode en databank als bestaand systeem: behoud compatibiliteit, namen, bestaande gebruikersflows en gegevens.

## Smartschool, gebruikers en meerdere scholen
- Smartschool OAuth-login en SOAP-Webservices zijn verschillende integraties. Controleer de juiste configuratie en het juiste platform vóór het aanpassen van een flow.
- Houd rekening met `schoolid`, schoolspecifieke afzenders, ontvangers en Blade-templates, inclusief fallbacktemplates.
- Laat berichten, co-accountwijzigingen en leerlingensynchronisatie niet onbedoeld plaatsvinden bij tests.
- Een optie met `test` in de naam is niet noodzakelijk zonder externe effecten. Lees de concrete code: `leerlingen:check-18 --test-ss-overzicht` **verstuurt** bijvoorbeeld echte Smartschool-overzichten.
- De geplande taken in `routes/console.php` draaien volgens `Europe/Brussels`: 01:00 18-jaarscontrole, 02:00 verjaardagen, 03:00 Smartschool-leerlingensynchronisatie school 1.
- Bij synchronisatie: voorkom onbedoelde deactivatie bij gedeeltelijke SOAP-fouten, beperkte datasets of ontbrekende klassen. Wijzig dit gedrag alleen doelbewust en met passende tests.
- Respecteer toegang per school en gebruikersrechten bij nieuwe functies; ga er niet van uit dat het tonen van een groepsnaam al autorisatie afdwingt.

## Databank
- Controleer bestaande modellen **en** het werkelijke schema alvorens SQL of migraties voor te stellen.
- De huidige repository bevat niet alle migraties van de bestaande zakelijke tabellen. Ga dus niet uit van een volledig reproduceerbaar schema via `php artisan migrate`.
- Maak geen schemawijzigingen, `migrate`, `migrate:fresh`, `db:wipe`, `db:seed`, imports of bulkupdates zonder uitdrukkelijke toestemming en een gecontroleerde back-up/rollbackaanpak.
- Gebruik bij tests fictieve leerlinggegevens. Plaats nooit echte persoonsgegevens, databankdumps, geheime sleutels of tokens in Git.

## Veiligheid en uitvoering
- **Standaard eerst lezen en analyseren.** Bij nieuwe taken met mogelijke neveneffecten vraag je vooraf goedkeuring voor databasewrites, Smartschool-oproepen die gegevens wijzigen of berichten versturen, e-mails, productieaanpassingen en destructieve commando's.
- Lees `.env` of andere secretbestanden niet onnodig; toon of kopieer hun inhoud niet naar chat, logs, commits of documentatie.
- Gebruik geen productiegegevens om tests uit te voeren. Beperk diagnostische output met leerlingidentificatoren en persoonlijke informatie.
- Controleer bij nieuwe invoervelden validatie, autorisatie, escaping en correcte foutafhandeling.
- Onderzoek Git-status en huidige branch voordat je bestanden aanpast; respecteer niet-gecommitte wijzigingen van de beheerder. Overschrijf of reset deze nooit zonder toestemming.

## Tests en oplevering
- Begin met de meest gerichte, niet-destructieve checks: PHP-syntaxis, bestaande PHPUnit-tests en waar relevant `php artisan route:list`.
- Run alleen veilige tests op een gescheiden testomgeving. Ga niet uit van een veilige testdatabase zonder dat dit is vastgesteld.
- Als een test niet kan worden uitgevoerd, zeg dat expliciet; claim nooit dat de functie op productie getest is.
- Toon voor grotere wijzigingen een samenvatting van de diff en geef concrete handmatige teststappen.
- Code kan lokaal aangepast worden wanneer de gebruiker dat vraagt; GitHub-pushes, merges, deploys en externe berichten vereisen afzonderlijke toestemming.

## Communicatiestijl
- Werk praktisch en stapsgewijs, met bruikbare commando's voor Windows PowerShell / XAMPP.
- Leg technische keuzes helder uit zonder onnodige jargon.
- Onderscheid altijd bevestigde feiten uit code van hypotheses en adviezen.
