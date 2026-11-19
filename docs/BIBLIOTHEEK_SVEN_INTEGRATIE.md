# Bibliotheek: integratie van Svens systeem in het Atheneum Portaal

## Ontwerpkeuzes

Bronanalyse: ZIP met PHP-MVC, Google-login, afzonderlijke boeken-/gebruikersdatabase, QR-code, scannen, uitlenen, retourneren, verlengen, achterstallige boeken, e-mailherinneringen, instellingen en auditlog; SQL-dump met onder meer `books`, `users`, `loans`, `notifications` en `settings`.

Onze Laravel-code blijft de bron van waarheid:
- `library_books`: catalogus, ISBN-lookup, import en metadata blijven ongewijzigd.
- `library_copies`: unieke barcodes per fysiek exemplaar blijven behouden.
- `leerlingen`: actieve leerlingen en Smartschool-gebruikersnamen uit het bestaande LVS, geen tweede gebruikers-/leerlingentabel.
- `library_loans`: **nieuwe** uitleningen verwijzen naar **copy_id**, niet rechtstreeks naar book_id. Hiermee kunnen verschillende exemplaren van hetzelfde boek apart uitgeleend worden.
- `library_settings`: looptijden, grenzen en herinneringen.
- `library_loan_notifications`: verzending/dubbelpreventie per lening, type en datum.
- `library_loan_events`: auditlog bij uitlenen, inleveren, verlengen en per-leninginstellingen.

De originele SQL-dump bevat demonstratie-/persoonsgegevens en wordt **niet** ingeladen. De originele .env en vendor uit de ZIP worden niet overgenomen.

## Beschikbaar op deze branch

- Uitleenregistratie met scanner of handmatige barcode en bestaande actieve leerling
- Maximaal aantal openstaande uitleningen, dubbele uitlening voorkomen
- Barcode-gebaseerd snel inleveren, verlengen met maximum
- Actieve, historische en te late uitleningen, te laat in rood
- Globale uitleentermijn en aantal toegestane uitleningen/verlengingen; per uitlening afwijkende duur of inleverdatum
- Smartschool-uitleningsbevestiging, herinnering vooraf (standaard 3 dagen) en op vervaldag
- Herinnering per individuele uitlening overschrijven, ook achteraf
- Smartschool-afzender standaard `bib`, uitsluitend hoofdaccount
- Unieke notificatielog, geen automatische retry bij onzekere verzending
- Scheduler: `bibliotheek:herinneringen` om 08:00 Europe/Brussels; verzending standaard **UIT**
- Dashboard toont actieve en achterstallige uitleningen als nieuwe tabellen aanwezig zijn

## Bewust niet automatisch overgenomen

- Google-login en Svens eigen userbeheer: ons portaal gebruikt Smartschool SSO en bestaande LVS-leerlingen.
- Svens losse `books`- en `users`-tabellen: dit zou dubbele gegevens creëren.
- QR-code-PNG-opslag en een massaprintfunctie: daarvoor moet een compatibele QR-bibliotheek voor onze Laravel-versie eerst gekozen, geïnstalleerd en getest worden. Gewone barcode-invoer werkt reeds.
- Mailherinneringen aan secretariaat/semesterperiodes: vervangen door de gevraagde Smartschool-reminders; afzonderlijke opvolgmeldingen vergen bijkomende afspraken.
- Import van Svens historische gegevens: pas na expliciete controle van mapping en toestemming.

## Integratie met bestaande users en groups

**Er komt geen tweede gebruikersdatabase.** Het bestaande `users.groupId` verwijst naar `groups.id`. Authenticatie blijft via Smartschool OAuth, autorisatie via de actuele database-rol en `allowed_login=1`.

Bestaande groepen worden **niet gewijzigd**:
- 1: `leerkracht` → rol leerkracht
- 2: `directeur` → rol directie
- 3: `leerlingbegeleider` → rol leerlingbegeleiding
- 4: `ICT-coördinator` → rol ICT-coördinator (altijd volledige beheerrechten)

De aanvullende migratie voegt, alleen wanneer ze ontbreken, groepen `boekhouding`, `secretariaat` en `TAC` toe. Nieuwe ID's worden door de database bepaald, niet hardcoded.

De **bestaande** `portal_role_permissions`-tabel bewaart per schoolrol de rechten op modules, acties en instellingen. Nieuwe rollen starten zonder rechten (*deny by default*). Je beheert deze via **Beheer → Rollen en rechten**. **Beheer → Gebruikersbeheer** past direct `users.groupId` aan; het maakt geen nieuwe `users`-records aan. ICT-beheer blijft alleen voor leden van de bestaande groep `ICT-coördinator`.

De eerdere tweede tabel `portal_staff` is niet meer nodig. Omdat de vorige migratie mogelijk al werd uitgevoerd, is er een **nieuwe herstelmigratie**. Bij een lege `portal_staff` wordt de tabel verwijderd; bij inhoud wordt ze uit voorzorg hernoemd naar `portal_staff_legacy` en blijven haar rijen behouden. Bestaande gebruikers en rollen uit `users` en `groups` worden nooit verwijderd. Een eventueel `portal_staff_legacy`-archief kan pas na controle apart worden weggehaald. **Gebruik geen migrate:rollback**: dat zou ook de nodige rechtentabel kunnen verwijderen.

**De eerste ICT-coördinator hoeft niet te worden aangemaakt.** Controleer dat jouw bestaande `users.groupId` naar `ICT-coördinator` verwijst en `allowed_login=1`. Het oude consolecommando `bibliotheek:eerste-beheerder` is verwijderd.

De LVS-routebeveiliging blijft voorlopig zoals ze was, zodat bestaande LVS-workflows niet onbedoeld veranderen. De nieuwe rechten worden nu op bibliotheek- en beheerroutes toegepast; toekomstige modules kunnen hetzelfde rechtenbeheer gebruiken.

## Lokaal installeren en veilig testen

Op de **ontwikkelpc** (alleen op een gescheiden testdatabank na back-up):

```powershell
cd C:\xampp\htdocs\LVS
git fetch origin
git switch --track origin/feature/bibliotheek-sven-integratie
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_110000_create_library_loans_and_settings.php
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_120000_create_library_staff_table.php
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_130000_align_portal_roles_with_existing_groups.php
C:\xampp\php\php.exe artisan optimize:clear
C:\xampp\php\php.exe artisan bibliotheek:herinneringen --test
```

Test met een fictieve actieve leerling in de lokale databank (of een geautoriseerde testaccount) en een testexemplaar: uitlenen, herhaalde uitlening blokkeren, per-leningherinneringen instellen, verlengen en scannen bij inlevering. Verifieer dat geen Smartschoolbericht verzonden wordt zolang `smartschool_enabled=0`.

Om herinneringen op productie te activeren moet eerst het Smartschool-account `bib` bestaan, een gecontroleerde testzending en autorisatie uitgevoerd zijn, en de bestaande Laravel-scheduler regelmatig draaien. Het instellen op 'Ingeschakeld' verstuurt vanaf dat moment echte berichten bij nieuwe uitleningen en op de geplande momenten.

**Niet** zomaar `php artisan migrate` gebruiken: de repository bevat niet alle bestaande legacy-databankmigraties. Voer alleen de aangegeven migratie uit.

## Open voor formele livegang

- Lokale syntax-, route-, functionele en integratietests met de werkelijke Laravel/MariaDB-versie
- Controleer de bestaande users/groups en ken de gewenste rollen toe via **Beheer → Gebruikersbeheer**. Vink alleen noodzakelijke module-/instellingenrechten aan.
- Check of `bib` als afzender op de juiste Smartschool-school geautoriseerd is
- Controleer of de scheduler dagelijks de nieuwe opdracht uitvoert
- Privacy-/gegevensbewaarbeleid voor uitleenhistoriek en notificatielogs
- QR-labelprint en verdere administratieve wensen apart testen en toevoegen

De branch is nog niet naar `main` gemerged.


## Nieuwe uitleenflow en QR-scancodes
De uitleenflow begint bij de leerling: kies een actieve leerling of scan de QR-code, voeg meerdere boeken toe en controleer de standaard ingevulde uitleentermijn. Herinneringen blijven globaal ingesteld.

De nieuwe nullable en unieke kolom leerlingen.scannable_code wordt bij de bestaande nachtelijke Smartschool-leerlingensynchronisatie om 03:00 (Europe/Brussels) gesynchroniseerd. De scan in de bibliotheek raadpleegt uitsluitend de lokale database.

De QR-code wordt eerst uit getAllAccountsExtended gehaald als het veld daar aanwezig is. Anders probeert de sync getUserDetails, maar alleen voor leerlingen zonder opgeslagen code of bij expliciete --refresh-scancodes. Of dit werkt hangt af van de respons en de rechten van het Smartschool-webserviceprofiel. Controleer de QR-dekkingsgraad na de sync.

Bij een onvolledige respons (lege of mislukte klassen) deactiveert de sync geen leerlingen. De nachtelijke en handmatige sync delen een lock.

Beheer -> Leerlingensynchronisatie laat ICT-coordinatoren handmatig een leerlingensync aanvragen, de QR-code-dekkingsgraad bekijken en recente runs controleren. Handmatige syncs werken via een aparte databasewachtrij.

Gerichte migraties (alleen op een gecontroleerde testdatabase):
  C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_150000_add_scannable_code_to_leerlingen.php
  C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_160000_create_smartschool_sync_runs_table.php
  C:\xampp\php\php.exe artisan optimize:clear

Klik op Synchronisatie starten, en verwerk de wachtende opdracht op de ontwikkelpc met:
  C:\xampp\php\php.exe artisan queue:work smartschool_sync --queue=smartschool-sync --stop-when-empty --tries=1 --timeout=1800

Voor continue verwerking op de server: gebruik Windows Taakplanner die dit workercommando iedere minuut start en geen nieuwe instantie toelaat terwijl de vorige actief is. De queue-connection smartschool_sync heeft retry_after 2100. De nachtelijke sync blijft via de bestaande Laravel Scheduler werken.

Na de eerste sync moet Leerlingen met QR-code stijgen. Geen QR-codes? Dan eerst de Smartschool-webservice respons/permissions controleren; de bibliotheekfunctie gebruikt nooit een live SOAP-call tijdens scannen.

Alle wijzigingen staan op de aparte featurebranch, niet op main of productie.

## Monitoring van nachtelijke taken (featurebranch)

Het beheerscherm Leerlingensynchronisatie toont voortaan ook het werkelijk geregistreerde resultaat van deze vier geplande taken:
- 01.00: 18-jarigen, co-accounts en waarschuwingen
- 02.00: verjaardagsberichten leerlingen en personeel
- 03.00: Smartschool-leerlingensync met QR-codes
- 08.00: bibliotheekherinneringen (geen berichten zolang uitgeschakeld).

De bestaande Laravel scheduler blijft deze taken dagelijks uitvoeren. De commandowrapper monitor:scheduled registreert status, starttijd, eindtijd en een privacyveilige samenvatting in scheduled_task_runs. De oude console-logbestanden blijven behouden. Bij ontbrekende monitortabel loopt de onderliggende taak veilig door, zonder monitoring. Oude uitvoeringen worden NIET als succesvol ingevuld.

Gerichte extra migratie (uitsluitend gescheiden testdatabase):
  C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_170000_create_scheduled_task_runs_table.php
  C:\xampp\php\php.exe artisan optimize:clear

Lege Smartschoolklassen zonder fout bij ophalen leiden tot status 'Deels voltooid', niet volledig 'Mislukt'. De deactivering van leerlingen blijft bij een onvolledige klassensnapshot geblokkeerd. Opgeslagen QR-codes en bijwerkingen blijven behouden.

Let op: de pagina toont enkel uitvoeringen die sinds deze uitbreiding gemonitord werden. Een scheduler die op de server niet loopt, veroorzaakt geen nieuwe registraties. Controleer Laravel Taakplanner/schedule:run en de serverlogs als er na een geplande nacht geen resultaten verschijnen. De bestaande CLI-functies kunnen gedeeltelijke Smartschool-afleverfouten intern opvangen; een succesvolle exitcode bewijst niet altijd een volledige aflevering.


## Volgende werksessie: bibliotheek — openstaande uitbreidingen (19-11-2026)

De gebruiker heeft de huidige bibliotheekfunctionaliteit zelf functioneel getest en bevestigd dat ze goed werkt. De onderstaande uitbreidingen zijn **nog niet afgetest of afgerond** en mogen niet als gerealiseerd worden beschouwd.

### Smartschool-berichten bij bibliotheekuitleningen

- Bericht bij het uitlenen van een boek.
- Herinnering **3 dagen vóór** de uiterste inleverdatum.
- Herinnering **op de uiterste inleverdatum**.
- Bericht **bij de effectieve terugbrenging/inlevering** van een boek.
- Rekening houden met het reeds aanwezige bibliotheek-berichtensysteem, het Smartschool-account `bib`, globale instellingen en notificatielogging; test eerst met een testontvanger, vermijd dubbele berichten.
- Controleer bestaande implementaties en stem de exacte tekst, ontvanger en timing af vóór activeren op productie. De bestaande berichtenverzending kan standaard uitgeschakeld zijn.

### QR-codes voor fysieke boeken / exemplaren

- QR-codes kunnen genereren voor boeken, bij voorkeur **per fysiek exemplaar** met unieke `library_copies.barcode`.
- **Bijhouden van welke exemplaren al een QR-code is aangemaakt en welke nog niet** (bijvoorbeeld status, aanmaakmoment en desgewenst printstatus). Eerst datamodel en verschil tussen *gegenereerd* en *geprint* afspreken.
- Losse QR-codegeneratie mogelijk maken vanuit boekencatalogus/een boekdetail.
- QR-code automatisch of met een keuze kunnen genereren **tijdens het aanmaken van één boek**.
- QR-codegeneratie ondersteunen **bij bulktoevoeging/import van meerdere boeken**.
- Een apart scherm voorzien om alle nog niet voorziene exemplaren te selecteren en QR-codes in bulk te genereren en af te drukken als etiketten.
- Bestaande barcodes en uitleengeschiedenis respecteren: geen nieuwe exemplaaridentiteiten aanmaken bij het genereren van QR-labels.

### Rapportages bibliotheek (nieuwe aparte knop)

- Voeg een duidelijke menuoptie **Bibliotheek → Rapportages** toe, met een overzichtelijk dashboard en afzonderlijke rapportpagina's.
- **Uitleningen:** aantal uitleningen per dag/week/maand/schooljaar, actieve, afgewerkte en achterstallige uitleningen, gemiddelde uitleenduur en verlengingen.
- **Leerlingen:** wie leent het meest, wie heeft momenteel boeken, uitleningen per leerling en per klas, openstaande/te late boeken, individuele historiek.
- **Boeken en exemplaren:** populairste en minst uitgeleende titels, nooit uitgeleende boeken, beschikbaarheid, totale voorraad, uitgeleende exemplaren en eventuele verloren/beschadigde exemplaren indien geregistreerd.
- **Achterstallige uitleningen:** hoeveel te laat, aantal dagen te laat, per klas/leerling en mogelijkheid om gericht op te volgen.
- **QR-code- en catalogusstatus:** aantal exemplaren mét en zonder gegenereerde/afgedrukte QR-code, en voortgang van bulkimporten.
- **Smartschool-berichten:** aantallen geplande/verzonden/mislukte bibliotheekberichten, voor zover correct geregistreerd, zonder onnodige persoonsgegevens.
- Gebruik filters voor periode, klas, leerling, boek, status en eventueel school; toon kerncijfers, eenvoudige grafieken en sorteerbare tabellen.
- Voorzie export naar **Excel/CSV** en eventueel **PDF** of afdrukbare rapporten; eerst vastleggen welke exports het nuttigst zijn.
- Rapportages baseren op bestaande tabellen en uitleenhistoriek, met correcte rechten en aandacht voor privacy. Geen rapportdata verzinnen; expliciet tonen wanneer gegevens ontbreken.
- Dit is een **toekomstige uitbreiding**, nog niet geïmplementeerd.

### Werkwijze voor de volgende sessie

Eerst actuele code op `main` nalopen en een nieuwe featurebranch maken. Implementeren en lokaal testen zonder Smartschool-berichten automatisch live te versturen. Pas na expliciete goedkeuring samenvoegen en op productie uitrollen.

### Geplande personeels-/gebruikerssynchronisatie vanuit Smartschool (19-11-2026)

- In Tinker bevestigd: `getAllAccountsExtended('Leerkrachten Atheneum Sint-Truiden', true)` levert 117 accounts; `getAllAccountsExtended('Administratie Campus Speelhof', true)` levert 20 accounts; ongeveer 135 unieke gebruikers (nog exact verifiëren). De tweede parameter `true` is noodzakelijk om subgroepen recursief op te halen.
- De webservice levert onder andere `gebruikersnaam`, `voornaam`, `naam`, `emailadres`, `geboortedatum`, `basisrol`, `status` en `scannableCode`. Synchroniseer alleen noodzakelijke velden, geen privacygevoelige extra velden.
- Accounts uit uitsluitend de leerkrachtengroep krijgen standaardrol **Leerkracht**; uitsluitend administratiegroep **Administratie**; als een account in beide groepen voorkomt krijgt **Administratie voorrang**.
- Deduplicatie op Smartschool-gebruikersnaam; nieuwe gebruikers éénmaal in `users` aanmaken. Bestaande speciale handmatig toegekende rollen (ICT, directie, TAC, leerlingbegeleiding enz.) bewaren; de standaardrollen Leerkracht/Administratie mogen bij gewijzigde groepslidmaatschappen worden aangepast.
- Gebruikers in de lokale `users`-tabel die **niet meer voorkomen in de actuele, volledig opgehaalde Smartschool-personeelsgroepen**: niet wissen, wel `allowed_login=false` en uit standaardoverzicht van actieve gebruikers filteren, historiek behouden. Alleen accounts uitschakelen die aantoonbaar tot de door Smartschool beheerde populatie behoren; nooit handmatig gemaakte systeem-, service- of externe accounts door afwezigheid uit deze twee groepen uitschakelen.
- **Fail-safe verplicht:** geen uitschakelingen bij SOAP-fouten, onvolledige respons, onverwacht sterke terugval in accountaantal, ontbrekende groepsdata of andere twijfel over volledigheid. Dry-run/preview tonen welke bestaande accounts zouden worden uitgeschakeld. Voor bestaande accounts `allowed_login` niet onbedoeld overschrijven, behalve bij expliciete uitschakeling wegens bevestigde afwezigheid.
- Voorzie handmatige sync via Beheer en een afzonderlijke nachtelijke taak met monitoring; eerst testen op aparte featurebranch, nooit direct productie activeren.
