# LVS Speelhof — technische projectcontext

> Actuele stand 27 november 2026, gebaseerd op de GitHub-wijzigingen, actuele routes en door ICT gerapporteerde productiecontroles. Geen nieuwe volledige code- of beveiligingsaudit. De eerdere analyse van 11 november is historisch en beschikbaar in Git-geschiedenis; oude bevindingen mogen niet zonder hercontrole als actuele bugs worden beschouwd.

## 1. Doel, omgeving en werkwijze

Intern schoolportaal voor GO! Atheneum Sint-Truiden, campus Speelhof, met voorbereiding op meerdere scholen. Omvat LVS, leerlingen- en personeelsbeheer, bibliotheek en technische/administratieve beheerfuncties.

- Repository: https://github.com/peterhons-blip/lvs-speelhof
- Standaard rechtstreeks naar main voor gevraagde wijzigingen; geen afzonderlijke mergebevestiging. Geen force-push. Productie-deploy blijft een afzonderlijke stap.
- Productie: https://lvs.atheneumsinttruidencampusspeelhof.be/
- SRV-WEB is de **lokale schoolserver**, niet een Azure-VM. Windows/XAMPP.
- Productiecheckout: C:\xampp\htdocs\lvs.atheneumsinttruidencampusspeelhof.be\public_html
- Lokale ontwikkelcheckout onder meer C:\xampp\htdocs\LVS; VS Code/Windows.
- Laravel 12, PHP 8.2, Blade, public/css/lvs.css en public/js/lvs.js. Eloquent/query builder.
- AGENTS.md bevat de ontwikkelafspraken. Controleer altijd de actuele relevante code en scheiding tussen test en productie.

## 2. Modules en architectuur

| Module | Belangrijkste aanknopingspunten | Status |
| --- | --- | --- |
| Dashboard | DashboardController, home, beheer.dashboard | Rolgebonden portaal en beheer |
| Aanmelding | Auth/SmartschoolController, EnsureSmartschoolAuthenticated | Smartschool OAuth; geen Google-login in gebruik |
| Rollen/rechten | LibraryAccess, RequirePortalPermission, portal_role_permissions, portal_user_permissions, portal_student_permissions | Bestaande rechten en individuele overrides; controleer per actie |
| Testen als gebruiker | PortalPreviewController, portal_preview | ICT kan medewerker/leerling/co-account simuleren; gecontroleerde opslag mogelijk; technische ICT-modules tijdens preview geblokkeerd |
| LVS | LeerlingenController, MeldingController, IncidentReleaseController | Dossiers, meldingen, vertrouwelijkheid, vrijgave en opvolging |
| Mijn klassen | class_staff_links, skore_student_teacher_links, class_staff_sync | Klaskoppelingen bestaan; functionele volledigheid nog toetsen |
| Leerlingenbeheer | StudentManagementController, StudentPhotoController | Filters, detail, bulkacties, portaalrechten, foto-import en upload, stamnummer/InternNr |
| Personeelsbeheer | SmartschoolStaffSyncController, beheer.users.* | Accounts, rollen, loginrechten en synchronisatie |
| Leerling-/ouderportaal | ExternalPortalController, ExternalLibraryWorkflowController, /mijn-portaal | Externe toegang, bibliotheekaanvragen, vrijgegeven LVS-informatie; rollen testen |
| Bibliotheek | LibraryBookController, LibraryLoanController, LibraryImportController, LibraryReportController, LibraryEscalationController | Boeken, exemplaren, ISBN/zoekdiensten, QR, import, uitleen, aanvragen, herinneringen, rapporten |
| Mijn bibliotheek | /mijn-bibliotheek | Persoonlijke uitleningen en aanvragen personeel |
| 18 jaar en secretariaat | adult_secretariat_visits, birthday_18_actions, birthday_18_privacy_audit | Privacy/co-accounts, uitnodigingen en bezoekopvolging |
| Verjaardagen | BirthdaySettingsController, birthday_deliveries | Instellingen en verzendregistratie |
| Schoolkalender | SchoolCalendarController, school_calendar_closures | Vakanties en sluitingen |
| Verzonden items | SentItemsController, portal_sent_items | Verzonden Smartschool-berichten/e-mail opvolgen |
| Geplande taken | ScheduledTasksController, scheduled_task_runs | Uitvoeringen en logs |
| Systeemprestaties | SystemPerformanceController, DatabaseIndexAudit, PerformanceLogReader, MeasureRequestPerformance, WindowsOperationsStatus | ICT-only diagnostiek |
| Back-ups | BackupDashboardController, WindowsOperationsStatus, ops/windows/Backup-LvsDatabase.ps1 | ICT-only status en historiek; onafhankelijke Windows-taak |

Routes in routes/web.php; geplande Artisan-taken in routes/console.php. Controleer autorisatie op serverniveau en schoolscoping; verborgen knoppen zijn geen toegangscontrole.

## 3. Databank en prestaties

- Productiedatabank speelhof_lvs is op 27 november van externe Azure MariaDB naar lokale XAMPP MariaDB 10.4.32 verhuisd. Verbinding via 127.0.0.1. Geen MariaDB-upgrade gewenst.
- Productiecontrole: 49 InnoDB-tabellen, 115 indexen na toevoeging van zeven kandidaat-indexen. De volledige zakelijke schemahistoriek is niet uitsluitend uit migraties reproduceerbaar.
- Kern: scholen, klassen, leerlingen, users, groups, meldingen, meldingscategorien, meldingssoorten; daarnaast bibliotheek, rechten, externe toegang, opvolgacties, klaskoppelingen, taak- en synchronisatiehistoriek.
- Toegevoegde indexen: idx_users_ss_login, idx_users_school_ss, idx_leerlingen_school_klas_active, idx_meldingen_leerling_datum, idx_meldingen_soort, idx_meldingen_created, idx_meldingssoorten_categorie. Aanwezigheid bevestigd; queryplan/snelheidswinst per index niet afzonderlijk bewezen. Handmatige productie-indexen zijn niet automatisch migraties. Dubbele unieke klassenindex niet verwijderd.
- SESSION_DRIVER=file, CACHE_STORE=file; queueconfiguratie database. OPcache actief. OAuth gebruikt config-cache-compatibele configuratie.
- Gemeten indicaties: DB-querylatentie van circa 19,56 ms naar 0,32 ms; dashboard circa 1039 naar 303 ms; leerlingfoto circa 400 naar 28 ms. Verschillende meetomstandigheden; geen gecontroleerde benchmark.
- Profiler configureerbaar via LVS_PERFORMANCE_ENABLED, LVS_PERFORMANCE_SLOW_MS en LVS_PERFORMANCE_QUERY_DETAILS. Geen SQL-tekst, bindings of persoonsgegevens in profilerlogs; optionele HMAC-fingerprints.
- Systeemprestaties (/beheer/systeemprestaties) toont routegemiddelden/P95, SQL, uurgrafiek, HTTP-status voor geregistreerde antwoorden, indexinventaris, tabelgrootte, disk, PHP, cache en Windows-status. Gelogde aanvragen zijn geen volledige verkeersstatistiek. Indexaudit is alleen-lezen.
- CPU/RAM: ops/windows/Measure-LvsServer.ps1, Windows-taak LVS - Systeemprestaties, iedere minuut, SYSTEM; statusbestand C:\LVS-Status\server-health.json. Taak getest. Beperk schrijfrechten op scripts/statusmap.
- Logrotatie: LOG_CHANNEL=daily en LOG_DAILY_DAYS=14 is de afgesproken configuratie; afzonderlijke taaklogs en oude laravel.log worden hierdoor niet automatisch verwijderd. Controleer actuele productieconfiguratie zonder secrets uit te lezen.

## 4. Smartschool, rollen en geplande taken

OAuth en SOAP zijn verschillende integraties. Controleer schoolid, schoolspecifieke afzenders/ontvangers, accountsoorten (hoofdaccount 0, co-accounts 1–6), rechten en externe bijwerkingen. Neem niet aan dat alle multi-schoolflows volledig gescheiden zijn zonder test. Bij incomplete SOAP-resultaten geen onterechte deactivatie. Controleer actuele commando-opties.

Actuele routes/console.php gebruikt monitor:scheduled, Europe/Brussels en withoutOverlapping:

| Tijd | Taaksleutel |
| --- | --- |
| 01:00 | becoming_18 |
| 02:00 | birthdays |
| 03:00 | student_sync |
| 04:00 | staff_sync |
| 05:00 | class_staff_sync |
| 08:00 | library_reminders |
| 08:15 | secretariat_reminders |

Bibliotheekverzending standaard uit totdat expliciet geactiveerd. Controleer daadwerkelijke instellingen en uitvoering bij praktijktests. Testopties zijn niet per definitie zonder externe effecten: leerlingen:check-18 --test-ss-overzicht verstuurt echte Smartschool-overzichten; geen live verzendtests zonder toestemming. Dry-run kan externe reads en logging veroorzaken.

## 5. Back-up en herstel

- Windows-taak LVS - Databank Backup draait ieder uur op lokale SRV-WEB, onafhankelijk van Laravel, onder het bestaande Windows-administratoraccount. Handmatige start via Taakplanner succesvol; LastTaskResult 0 en volgende uitvoering een uur later bevestigd.
- Script ops/windows/Backup-LvsDatabase.ps1 gebruikt een afzonderlijk lokaal MariaDB-back-upaccount, mysqldump --single-transaction, GnuPG en AzCopy. Geen schemawijzigingen tijdens export. Alle 49 tabellen gebruiken InnoDB.
- GnuPG: C:\Program Files\GnuPG\bin\gpg.exe. Publieke sleutel in C:\LVS-Backup\private\gnupg. Private decryptiesleutel buiten webserver bij ICT. Sleutel verloopt 27 november 2029.
- Lokale bestanden: C:\LVS-Backup\encrypted, formaat speelhof_lvs-<UTC-tijdstempel>.sql.gpg. Retentie 14 dagen, opruiming alleen na succesvolle externe upload. Private map en archive zijn afgeschermd voor Administrators/SYSTEM; webapplicatie krijgt geen toegang tot dumps.
- Azure: atheneumstlvsbackup, private container lvs-backups; lifecycle-regel 30 dagen, versie-/snapshotretentie ingesteld, soft delete voorzien. Geen aparte Azure-VM nodig voor back-up.
- Entra-app LVS-Backup-Service, containergebonden Storage Blob Data Contributor, certificaatauthenticatie SPN (geen Managed Identity op lokale schoolserver). PFX en DPAPI-wachtwoordbestand in beveiligde private map; DPAPI werkt alleen onder hetzelfde Windows-account op dezelfde computer. Certificaat tijdig vernieuwen. ICT heeft Blob Data Reader voor downloaden. Contributor kan ook verwijderen; verdere hardening optioneel.
- C:\LVS-Status\backup-status.json en backup-run-*.json bevatten uitsluitend metadata. Historiek 31 dagen, maximaal 800 records getoond. Waarschuwing na 90 minuten of mislukte status. /beheer/back-ups is ICT-only, alleen-lezen en geen live inventaris van blobbestanden.
- Eerste externe upload, volgende geplande uitvoering en download uit Azure + decryptie in Kleopatra + openen SQL bevestigd door ICT. **Volledige import in geïsoleerde testdatabank nog niet uitgevoerd**.
- Herstel: Azure Portal > Storage accounts > atheneumstlvsbackup > Containers > lvs-backups > Download. Ontsleutel met Kleopatra/private sleutel op vertrouwd toestel, importeer uitsluitend in geïsoleerde testdatabase voor test, schakel externe acties uit en verwijder onversleutelde testkopie veilig.
- Foto's, documenten en .env worden afzonderlijk bewaard door ICT. Geen extra automatische bestandsback-ups gevraagd. Houd .env inclusief APP_KEY actueel. Geen secrets, dumps, PFX, DPAPI of private sleutels in Git.
- Zie docs/WINDOWS_BACKUP_RECOVERY.md voor algemene instructies; actuele operationele stand in deze sectie heeft voorrang op oudere voorbereidingsinstructies.

## 6. Deploy en controles

Gebruik PowerShell op de correcte server/checkout. Git pull origin main. Composer install --no-dev --optimize-autoloader indien afhankelijkheden wijzigen. Bij routes: php artisan route:clear, php artisan route:cache, php artisan route:list --name=<route>. Bij config: php artisan config:cache; bij views: php artisan view:cache. Alleen migreren na expliciete toestemming en schema/back-upcontrole. Geen database-reset of productie-integratietests met echte bijwerkingen. Bij routefout na deploy eerst cache en actuele route controleren. Documentatiewijzigingen vereisen geen cacheverversing.

## 7. TODO per module

Status: [x] = afgewerkt/bevestigd binnen beschreven scope; [ ] = nog testen, verfijnen of ontwikkelen. De lijst is de werkplanning op 27 november; controleer eerst bestaande code. Nieuwe voorstellen zijn geen vastgelegde functionele eisen.

### Bibliotheek — hoge prioriteit, ingebruikname
- [x] Boeken/exemplaren, ISBN en zoeken op titel/naam, QR-labels, import, uitleen/inlevering, kalender, rapporten en aanvragen in code aanwezig.
- [ ] Praktijktest bibliotheekmedewerkers en feedback verwerken.
- [ ] Reserveringen versus verlengingsaanvragen verder verfijnen/onderscheiden; bestaande workflows eerst toetsen.
- [ ] Rechten voor personeel, leerlingen en co-accounts consistent controleren; Mijn bibliotheek en Mijn portaal testen.
- [ ] Herinneringen, herhalingsinterval, pauzeren en escalaties naar beleid in praktijk controleren; geen ongewenste echte berichten.
- [ ] ISBN-bronnen (Open Library, Google Books; aanvullende bronnen indien nodig), foutafhandeling, dubbel ISBN als extra exemplaar en QR-scan/wissen testen.
- [ ] Uitleen-/retourproces, snelle filters, rapporten en vakantie-inleverdata testen.
- Voorraadcontrole niet opnieuw als tegel toevoegen zonder vraag; eerdere verwijdering behouden.

### LVS en zichtbaarheid — hoge prioriteit
- [x] Meldingsvrijgave, vertrouwelijk-vink, externe portaalrechten en previewfunctionaliteit in code aanwezig.
- [ ] Volledige rechtenmatrix testen per rol, school en relatie met leerling (titularis/vakleerkracht/andere medewerker).
- [ ] Leerkracht kan altijd vertrouwelijke melding aanmaken; zichtbaarheid voor leerling/co-account uitsluitend volgens expliciete instelling. Controleer formuliertekst en server-side autorisatie.
- [ ] Vrijgegeven LVS-meldingen in leerling-/ouderportaal verder testen/verfijnen.
- [ ] Automatische opvolgacties en escalatie bij meldingen controleren; rapportering en nuttige dossieroverzichten verder uitwerken.
- [ ] Login als andere rol: correcte opslag van toegestane acties, duidelijke testbalk en uitsluiting van ICT-only technische functies.

### Mijn klassen / titularissen / vakleerkrachten — hoge prioriteit
- [x] Tabellen class_staff_links en skore_student_teacher_links en geplande class_staff_sync bestaan.
- [ ] Smartschool-klaskoppelingen, titularissen en vakleerkrachten valideren, schooljaar en schoolscoping controleren.
- [ ] Mijn klassen en relevante klas-/leerlingenoverzichten voor leerkrachten verder afwerken.
- [ ] LVS-informatie per klas met passende zichtbaarheid, filters en toegang tot dossiers.

### Leerlingenbeheer — normale prioriteit
- [x] Detail, foto-upload/ZIP, stamnummer en InformatInternNr, actief/klasfilter, bulkacties en individuele portaalrechten aanwezig.
- [ ] Uitgeschreven leerlingen en ontbrekende gegevens: procedure en opvolging verder uitwerken.
- [ ] Historiek klaswijzigingen onderzoeken als mogelijke uitbreiding.
- [ ] Zoeken/filters verfijnen op basis van praktijkfeedback; rechten en fototoegang testen.
- [ ] Sync testen bij ontbrekende klassen, onvolledige data en heractivering, zonder echte onbedoelde updates.

### Personeelsbeheer en rechten — normale prioriteit
- [x] Personeelssync, filters, loginrechten, rollen en beheerpagina's aanwezig.
- [ ] Synchronisatie, technische accounts en inactieve medewerkers opvolgen; toegangsrechten bij uitdiensttreding testen.
- [ ] Groeps- en individuele rechten, bulkacties, schoolgebonden toegang en beheerpagina's controleren.

### Leerling- en ouderportaal — hoge/normale prioriteit
- [ ] Smartschool-login leerling/co-account, accountkoppeling en toegangsbeperkingen volledig testen.
- [ ] Persoonlijke bibliotheek, reserveren/verlengen, status en vrijgegeven LVS-meldingen controleren; geen inzage in andere leerlingen.
- [ ] Gebruiksvriendelijkheid op smartphone beoordelen.

### 18 jaar, secretariaatsbezoeken en verjaardagen
- [x] Instellingen, bezoekopvolging, privacyacties en verzendhistoriek aanwezig.
- [ ] Uitnodiging, langs geweest, herinneringsinterval, weekend en vakanties in praktijk evalueren.
- [ ] Idempotentie, foutafhandeling, juiste afzenders/ontvangers en co-accountstatus controleren; geen dubbele berichten.

### Schoolkalender, geplande taken en verzonden items
- [x] Beheer schoolkalender, taakoverzicht en verzonden items aanwezig.
- [ ] Kalenderwerking in bibliotheek en secretariaatsherinneringen controleren; schooljaarwissel opvolgen.
- [ ] Werkelijke nachtelijke taakuitvoering, logstatus en foutmeldingen periodiek controleren.

### Rapportering en algemene portaalfuncties — toekomstige voorstellen
- [ ] LVS-rapportering en exports verder uitwerken na afstemming met beleid.
- [ ] Persoonlijk roldashboard, intern meldingscentrum, globale zoekfunctie, aanvullende auditlog, centrale instellingen, tegelvolgorde en mobiele UX onderzoeken. Eerst prioriteren; bestaande functies niet dupliceren.

### Technisch beheer — afgewerkt / lage prioriteit
- [x] Lokale DB, sessies/cache, OAuth-cachefix, foto-optimalisatie, indexen, Systeemprestaties, CPU/RAM, versleutelde lokale/externe databankback-up, back-updashboard en download/decryptie.
- [x] PROJECT_CONTEXT.md en AGENTS.md actualiseren (27 november 2026).
- [ ] Optioneel: AzCopy-logopschoning, apart Windows-serviceaccount, Azure-deletebescherming, volledige import-/hersteltest in geïsoleerde databank. Geen dringende nieuwe bouwopdracht; volledige hersteltest blijft aanbevolen.
- Geen MariaDB-upgrade; foto's/documenten/.env geen extra automatische back-up nodig volgens ICT.

## 8. Voorgestelde volgende werksessie

1. Bibliotheek: praktijkfeedback en operationele controles.
2. LVS-rechten/vertrouwelijkheid en zichtbaarheid.
3. Mijn klassen, titularissen en vakleerkrachten vanuit perspectief leerkracht.
4. Leerling-/ouderportaal en rapportering.
5. Overige uitbreidingen na prioritering.

Bewaar bestaande werkende flows. Actualiseer deze planning na wezenlijke wijzigingen en onderscheid code-aanwezigheid, functionele test, productiebevestiging en open voorstellen.
