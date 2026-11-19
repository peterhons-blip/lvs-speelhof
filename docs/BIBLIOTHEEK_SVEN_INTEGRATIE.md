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
