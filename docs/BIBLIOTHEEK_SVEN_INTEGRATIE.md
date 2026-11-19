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

## Centrale gebruikersrollen en rechten (geen .env)

De bibliotheek maakt gebruik van **centrale portaalrechten**, geen hardcoded .env-gebruikerslijsten.

- `portal_staff`: personeelsleden gekoppeld aan een Smartschool-gebruikersnaam en een rol.
- `portal_role_permissions`: toegestane rechten per rol én per module/instelling.
- Rollen: **ICT-coördinator**, **directie**, **boekhouding**, **secretariaat**, **leerlingbegeleiding**, **TAC**.
- Alleen ICT-coördinatoren hebben automatisch alle rechten en mogen gebruikers en de rechtenmatrix aanpassen.
- Alle andere rollen starten zonder toegekende modulebevoegdheden (*deny by default*).
- Beheerinterface: **Beheer → Gebruikersbeheer** en **Beheer → Rollen en rechten**.
- Registreer de **eerste ICT-coördinator** éénmalig op de lokale ontwikkelmachine of vertrouwde serverconsole:
  `C:\xampp\php\php.exe artisan bibliotheek:eerste-beheerder peter.hons`
  (controleer vooraf de exacte Smartschool-gebruikersnaam; de command vraagt interactief bevestiging).
- Daarna kun je andere personeelsleden via de interface toevoegen en aanpassen.
- Aanbevolen test: directie zonder bibliotheekrechten krijgt HTTP 403; na verlenen van `bibliotheek.loans.view` kan die uitleningen bekijken maar niet wijzigen.
- De modules EMA, Onboarding en Nascholingen staan in de rechtenmatrix als **voorbereide toekomstige rechten**, niet als bestaande functionaliteit.
- LVS-rollen worden **niet zonder afzonderlijke migratie en autorisatie afgedwongen** op de oude LVS-routes: die blijven momenteel voor bestaande gebruikers werken zoals voordien. Hiermee vermijden we dat een bibliotheekdeploy het bestaande LVS blokkeert.
- Enkel een bestaande Smartschool-sessie volstaat niet om bibliotheek- of beheermutaties uit te voeren; de server controleert rolrechten.

## Lokaal installeren en veilig testen

Op de **ontwikkelpc** (alleen op een gescheiden testdatabank na back-up):

```powershell
cd C:\xampp\htdocs\LVS
git fetch origin
git switch --track origin/feature/bibliotheek-sven-integratie
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_110000_create_library_loans_and_settings.php
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_120000_create_library_staff_table.php
C:\xampp\php\php.exe artisan bibliotheek:eerste-beheerder peter.hons
C:\xampp\php\php.exe artisan optimize:clear
C:\xampp\php\php.exe artisan bibliotheek:herinneringen --test
```

Test met een fictieve actieve leerling in de lokale databank (of een geautoriseerde testaccount) en een testexemplaar: uitlenen, herhaalde uitlening blokkeren, per-leningherinneringen instellen, verlengen en scannen bij inlevering. Verifieer dat geen Smartschoolbericht verzonden wordt zolang `smartschool_enabled=0`.

Om herinneringen op productie te activeren moet eerst het Smartschool-account `bib` bestaan, een gecontroleerde testzending en autorisatie uitgevoerd zijn, en de bestaande Laravel-scheduler regelmatig draaien. Het instellen op 'Ingeschakeld' verstuurt vanaf dat moment echte berichten bij nieuwe uitleningen en op de geplande momenten.

**Niet** zomaar `php artisan migrate` gebruiken: de repository bevat niet alle bestaande legacy-databankmigraties. Voer alleen de aangegeven migratie uit.

## Open voor formele livegang

- Lokale syntax-, route-, functionele en integratietests met de werkelijke Laravel/MariaDB-versie
- Controle welke Smartschool-gebruikers onder de rollen vallen; voeg hen toe in **Beheer → Gebruikersbeheer** en vink alleen noodzakelijke module-/instellingenrechten aan
- Check of `bib` als afzender op de juiste Smartschool-school geautoriseerd is
- Controleer of de scheduler dagelijks de nieuwe opdracht uitvoert
- Privacy-/gegevensbewaarbeleid voor uitleenhistoriek en notificatielogs
- QR-labelprint en verdere administratieve wensen apart testen en toevoegen

De branch is nog niet naar `main` gemerged.
