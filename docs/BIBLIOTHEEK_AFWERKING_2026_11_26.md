# Bibliotheek afwerking - overleg 26 november 2026

Status: feature branch `feature/bibliotheek-afwerking-nov2026` (nog niet uitgerold).

## 1. Boekgegevens opzoeken

**Reeds aanwezig:** Open Library op basis van ISBN, titel/auteur en edities.

**Nieuw:** als Open Library geen resultaat geeft, gebruikt de server ook Google Books
voor ISBN, titel/auteur en de ISBN-importwachtrij. Bij ISBN wordt het Google-resultaat
alleen overgenomen als de API een exact overeenkomstig ISBN teruggeeft. De
bronnaam verschijnt in de gebruikersinterface. Van Google Books wordt geen
externe omslag-URL opgeslagen, omdat het catalogusmodel uitsluitend omslagen van
Open Library toestaat. Daardoor behouden we de bestaande veiligheidsregels.

Google Books API-key: optioneel in server-`.env` als `GOOGLE_BOOKS_API_KEY=...`.
De server gebruikt de key uitsluitend bij de server-side API-aanroep. De API heeft
quota; controleer de toegang op de live server. Zonder sleutel zijn openbare
zoekacties afhankelijk van Google's toegangsbeleid.

**Live foutopsporing:** bij *Boek toevoegen* staat een knop
**Controleer opzoekdiensten**. Deze controleert op de actieve webserver of
Open Library en Google Books bereikbaar zijn. Hij toont enkel een
succes-/HTTP-status. Details van DNS, TLS, firewall en timeouts komen in
`storage/logs/laravel.log`; persoonlijke gegevens worden niet doorgestuurd.
Bij serverfouten blijft handmatig invoeren mogelijk. Een specifieke bestaande
live-fout is nog niet gereproduceerd: de diagnoseknop helpt de precieze oorzaak
vaststellen.

**Meta4books:** de officiële Products API (ONIX 3 XML) en Zoek API (JSON)
vereisen een formele aansluiting en API-documentatie/toegang. We bouwen **geen**
ongeautoriseerde scraping of gefingeerde connector. Dit deel blijft open tot
de school een API-contract en toegangsgegevens heeft.

## 2. Herinneringen na de vervaldatum

Onder **Bibliotheek -> Uitleeninstellingen** staan nieuwe waarden,
die standaard geen extra berichten veroorzaken:

| Sleutel | Standaard | Betekenis |
| --- | --- | --- |
| `overdue_reminders_enabled` | `0` | Aan/uit |
| `overdue_first_days` | `3` | Eerste extra herinnering na drie volle dagen |
| `overdue_repeat_days` | `7` | Vervolgens elke zeven dagen |
| `escalation_enabled` | `0` | Escalatiebericht aan beleid uit |
| `escalation_after_reminders` | `3` | Na drie succesvol verzonden herinneringen |
| `escalation_recipient` | leeg | In te stellen actief personeelsaccount |

Het reeds bestaande hoofdschakelaar `smartschool_enabled` blijft leidend.
Bij uitschakelen daarvan worden er geen Smartschool-herinneringen verstuurd.

De bestaande geplande taak `bibliotheek:herinneringen` (08:00) krijgt
nu ook openstaande en vervallen uitleningen. Een extra type
`overdue_repeat` gebruikt een apart bewerkbaar sjabloon in
**Bibliotheek -> Smartschool-berichten**. Herhalingen stoppen automatisch
zodra een boek als ingeleverd is geregistreerd.

Een combinatie van `loan_id`, `type` en `scheduled_for` is uniek.
Mislukte/onzekere uitgaande berichten worden niet blind opnieuw verstuurd;
de bestaande verzendhistoriek en `library_loan_notifications` bewaren de status.

## 3. Escalatie

Een beleidssignaal is optioneel en staat standaard uit. Er wordt
alleen geëscaleerd wanneer de uitlening **nog open** en **te laat** is,
de ontvanger is ingesteld en minstens `escalation_after_reminders`
berichten voor die uitlening als `sent` staan geregistreerd. Zowel
automatische (vooraf/vandaag/na termijn) als handmatige herinneringen
tellen mee. Bevestigingen bij uitleen/inleveren tellen niet mee.

Het beleid ontvangt maximaal één Smartschool-bericht per uitlening,
van afzender `bib`, met boek, ontlener, barcode en inleverdatum.
Een mislukte of onzekere verzending krijgt een zichtbare status en
wordt niet zomaar opnieuw uitgevoerd. Het LVS legt geen sancties op.

De nieuwe tabel `library_escalations` is nodig voor idempotentie en
de historiek op de instellingenpagina.

## 4. Installatie / testen

1. Maak een **backup** van de gedeelde schooldatabank.
2. Haal de nieuwe feature branch binnen.
3. Voer **alleen** de gerichte nieuwe migratie uit:

```powershell
cd C:\xampp\htdocs\LVS
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_26_150000_create_library_escalations.php --force
C:\xampp\php\php.exe artisan optimize:clear
```

Gebruik niet het algemene `artisan migrate` vanwege de historische
standaardmigraties van deze databank.

**Veilige test (zonder verzending):**

```powershell
C:\xampp\php\php.exe artisan bibliotheek:herinneringen --test
```

Test minimaal:
- Een bekend ISBN via Open Library; een minder bekend ISBN via Google Books;
  een onbestaand ISBN en een ongeldig ISBN.
- Titel/auteur en de selectie van meerdere edities; ISBN-batchimport.
- Klik **Controleer opzoekdiensten** vanaf de *live* webserver en inspecteer
  de Laravel-log wanneer een externe dienst onbereikbaar is.
- Bewerk en bewaar het extra `overdue_repeat`-berichtsjabloon.
- Simuleer met een testuitlening 3 dagen te laat, 10 dagen te laat en
  17 dagen te laat; controleer dat `--test` de juiste datums toont.
- Controleer dat een ingeleverd boek niet meer in het testresultaat staat.
- Laat escalatie eerst uit; test de drempel met bestaande `sent`-logrecords
  en bevestig dat `--test` geen Smartschool-berichten verstuurt.
- Activeer een echte verzending pas in een afgesproken test met een
  expliciet testaccount en bekijk **Beheer -> Verzonden items**.
- Controleer rechten: boekzoekdiagnose uitsluitend
  `bibliotheek.books.manage`; settings uitsluitend
  `bibliotheek.settings.manage`; templates uitsluitend
  `bibliotheek.messages.manage`.

**Nog niet:** ouder-/leerlinglogin en daarbij behorende rollen/rechten,
Meta4books-aansluiting en bevestiging dat de eerdere fout op de live
server effectief is opgelost. De module moet lokaal en met de
bibliotheekmedewerkers getest worden voordat ze in productie wordt gebruikt.
