# Windows LVS: monitoring, logrotatie en databankback-up

> Operationele stand 27 november 2026: zie PROJECT_CONTEXT.md sectie 5. SRV-WEB is lokaal; certificaatauthenticatie SPN en DPAPI worden gebruikt, niet Managed Identity. De onderstaande voorbereiding is een algemene checklist, geen lijst van nog uit te voeren stappen.

## Voorbereiding (ICT)
- Maak C:\LVS-Status, C:\LVS-Backup\private en C:\LVS-Backup\encrypted buiten alle publieke webroots en Git. Beperk NTFS-rechten: back-upaccount schrijft; ICT beheert; Apache leest alleen C:\LVS-Status, niet de back-upmap. Controleer ook Apache DocumentRoot en toegang tot .env.
- Maak een afzonderlijk lokaal MariaDB-back-upaccount met uitsluitend benodigde leesrechten op speelhof_lvs (SELECT, SHOW VIEW, TRIGGER, EVENT; routines kunnen aanvullende metadatarechten vereisen). Controleer tabel-engines; single-transaction is alleen consistent voor transactionele tabellen. Voor niet-transactionele tabellen of DDL plan een onderhoudsmoment. Geen accountwachtwoord in Git.
- Maak C:\LVS-Backup\private\mysql-backup.cnf, alleen leesbaar voor het back-upaccount: [client], host=127.0.0.1, port=3306, user=..., password=... (elk op afzonderlijke regel).
- Installeer GnuPG en AzCopy uit officiële bronnen. Maak een aparte encryptiesleutel op een vertrouwd toestel. Bewaar private sleutel en herstelwachtwoord offline en buiten de webserver; importeer uitsluitend publieke sleutel onder het geplande Windows-account. Gebruik de volledige gecontroleerde fingerprint als Recipient.
- Richt een private Azure Blob-container in met passende EU-regio, toegangscontrole, versiebeheer/soft delete, bij voorkeur onveranderlijke retentie. Configureer AzCopy-authenticatie onder het taakaccount (bijvoorbeeld service principal met certificaat); geen SAS in script of logs. Beperk rechten, en voorkom dat het uploadaccount historische back-ups kan wissen.
- Test eerst zonder BlobContainer, daarna met private container-URL zonder querystring. Controleer exitcode, bestand en status. Verifieer externe aanwezigheid onafhankelijk.
- Maak een Windows Taakplanner-taak onder een afzonderlijk account; elk uur, geen parallelle instanties, bij gemiste start alsnog uitvoeren, log exitcode en bewaak mislukking extern. Gebruik powershell.exe -NoProfile -File met het volledige scriptpad, -Recipient en -BlobContainer; geen geheimen als argument. Health-script elke minuut. Beide scripts moeten kunnen draaien wanneer Laravel niet werkt.
- De databaseback-up omvat geen leerlingfoto's, bijlagen, .env, Apache, certificaten of geplande taken; daarvoor is een afzonderlijke versleutelde back-up nodig. Behoud ook de APP_KEY. Leg retentie vast met schoolbeleid. Houd rekening met resterende plaintext op schijf bij stroomuitval; gebruik versleuteld volume en beveiligde staging.
- Lokale encrypted retentie: 14 dagen, pas opruimen na geslaagde externe upload. Azure-retentie afzonderlijk instellen en controleren. Geen automatische verwijdering van externe bestanden door dit script.

## Logrotatie
Gebruik in .env LOG_CHANNEL=daily en LOG_DAILY_DAYS=14, daarna php artisan config:cache. Dit roteert de standaard Laravel-daglogs; losse taaklogs en oude laravel.log worden niet automatisch verwijderd. Bewaar oude logs veilig en pas retentie pas toe na controle. Systeemprestaties leest het vaste log en daglogs van vandaag/gisteren, geen volledige historie.

## Hersteltest
Download een specifieke externe .sql.gpg naar een afgeschermde herstelomgeving. Controleer de decryptie met de offline private sleutel. Importeer uitsluitend in een nieuwe, geïsoleerde databank, nooit over productie heen. Schakel scheduler, queue, Smartschool-berichten en mail uit in de herstelomgeving. Controleer tabellen, aantallen, indexen, enkele fictieve/afgeschermde functionele controles en documenteer hersteltijd. Herstel de private bestanden en APP_KEY vanuit de afzonderlijke back-up. Bij echte uitval: stop schrijvers, kies gecontroleerde herstelversie, herstel naar nieuwe server, verifieer rechten en gegevens, schakel verkeer en taken pas daarna over. Herhaal regelmatig.

## Beperkingen
Monitoring, beveiligde lokale export, externe upload, Windows-taak en download/decryptie zijn door ICT getest. De taak gebruikt voorlopig het bestaande administratoraccount. Volledige import in geïsoleerde testdatabase is nog niet getest. Foto's, documenten en .env zijn volgens ICT afzonderlijk bewaard; geen nieuwe automatische bestandsback-up gevraagd. Status is een momentopname, geen bewijs van externe duurzaamheid. Geen MariaDB-upgrade of schemawijzigingen.
