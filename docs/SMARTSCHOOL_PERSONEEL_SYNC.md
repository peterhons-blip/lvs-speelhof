# Smartschool-personeelssynchronisatie (featurebranch)

## Bronnen en standaardrollen

- `Leerkrachten Atheneum Sint-Truiden`: recursief ophalen; standaardrol **leerkracht**.
- `Administratie Campus Speelhof`: recursief ophalen; standaardrol **administratie**, krijgt voorrang als dezelfde gebruikersnaam in beide lijsten staat.
- De Tinker-controle van 19 november 2026 gaf respectievelijk 117 en 20 accounts, naar schatting 135 unieke accounts. Deze aantallen zijn veiligheidsindicaties, geen harde waarheid.

De koppelsleutel is de Smartschool-`gebruikersnaam` uit de API met `users.smartschool_gebruikersnaam` in LVS. `users.gebruikersnaam` is de Google-gebruikersnaam en wordt bij bestaande accounts nooit aangepast. Nieuwe accounts krijgen een interne unieke placeholder in het legacy veld `gebruikersnaam` en blijven `allowed_login=0` tot een beheerder toegang toelaat. Google is geen vereiste voor aanmelden; Smartschool OAuth gebruikt `smartschool_gebruikersnaam`. Nieuwe accounts krijgen standaardrollen; bestaande bijzondere rollen (ICT, directeur, secretariaat, TAC, leerlingbegeleider enz.) worden niet overschreven. Een bestaande handmatige blokkering (`allowed_login=0`) wordt door de sync niet automatisch teruggezet. De rol administratie heeft **geen impliciete ICT-beheerrechten**; toekenning van rechten verloopt via het bestaande rol- en rechtenbeheer.

## Fail-safe

- Eerst beide hoofdgroepen volledig recursief ophalen; bij SOAP-fout, onverwachte structuur, leerlingaccounts, missende gebruikersnaam of veel lagere aantallen stopt de sync zonder DB-wijziging.
- Technische accounts (`ict`, `lvs`, `bib`, `beleid`) worden niet als gewone `users` aangemaakt; ze blijven beschikbaar in Smartschool voor afzenders. De ontdubbeling gebeurt per Smartschool-gebruikersnaam. De sync schrijft alleen noodzakelijke accountgegevens weg: voornaam, familienaam, geboortedatum indien geldig, Smartschool-gebruikersnaam en de standaardrol. Bij nieuwe accounts wordt het Smartschool-e-mailadres als contact-e-mail gebruikt indien geldig; bestaande e-mailadressen worden niet overschreven. Lege geboortedatums wissen geen bestaande waarde. De bestaande Google-gebruikersnaam, bijzondere rollen en handmatige aanmeldinstellingen blijven behouden.
- Alle bestaande, aan school 1 gekoppelde accounts met `users.smartschool_gebruikersnaam` die niet meer in de volledig opgehaalde personeelsgroepen voorkomen worden bij de definitieve sync als inactief gemarkeerd (`smartschool_active=0`) en geblokkeerd (`allowed_login=0`), ook als ze een bijzondere rol hebben. Het account en die rol blijven bewaard. Technische afzenderaccounts `ict`, `lvs`, `bib`, `beleid` zijn uitgesloten. Aanwezige accounts krijgen `smartschool_active=1`; handmatige loginblokkeringen worden niet zomaar opgeheven.
- Bij meer dan 25 of meer dan een kwart van de lokale accounts voorgestelde blokkeringen, of een onverwacht grote terugval, stopt de gehele opdracht. De laatste actieve ICT-coördinator mag niet worden geblokkeerd. De eerste sync wordt alleen uitgevoerd na controle van de preview en een backup.
- Gebruikers met `smartschool_active=0` worden nooit getoond in Beheer → Gebruikersbeheer. Met 'Alle accounts' worden ook handmatig geblokkeerde accounts getoond die nog actief zijn in Smartschool. De lijst kan gefilterd worden op naam, Smartschool-account of groep.

## Lokaal installeren

Voer **uitsluitend** de nieuwe migratie uit; draai in de bestaande LVS-databank niet blind alle oude migraties opnieuw.

```powershell
cd C:\xampp\htdocs\LVS
git fetch origin
git switch feature/smartschool-personeel-sync
git pull origin feature/smartschool-personeel-sync
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_180000_create_staff_memberships.php
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_19_190000_add_smartschool_active_to_users.php
C:\xampp\php\php.exe artisan optimize:clear
```

**Veilige preview** (leest Smartschool, schrijft geen personeelsgegevens):

```powershell
C:\xampp\php\php.exe artisan smartschool:sync-personeel --schoolid=1
```

**Bewust uitvoeren** (pas na controle van preview en een databankbackup):

```powershell
C:\xampp\php\php.exe artisan smartschool:sync-personeel --schoolid=1 --apply
```

Handmatige webinterface: **Beheer → Personeel synchroniseren**. Deze gebruikt de al bestaande lange Smartschool-wachtrij. Als er geen worker draait, blijft de opdracht in wachtrij. Eenmalig verwerken met:

```powershell
C:\xampp\php\php.exe artisan queue:work smartschool_sync --queue=smartschool-sync --stop-when-empty --tries=1 --timeout=1800
```

De Laravel Scheduler voert de personeelssync **elke nacht om 04.00 uur met `--apply`** uit, met de bestaande veiligheidscontroles. Dit is expliciet toegestaan door de beheerder. De taak gebruikt rechtstreeks `monitor:scheduled staff_sync` en vereist geen queue-worker, wel een werkende Laravel Scheduler (`schedule:run`). Status en historiek zijn zichtbaar onder 'Geplande nachtelijke taken' op zowel de leerlingen- als personeelssynchronisatiepagina. Controleer de productieconfiguratie, scheduler en gegevensback-up vóór uitrol.

## Nog lokaal valideren

1. PHP-lint van gewijzigde bestanden, migratie en `artisan route:list`.
2. Previewresultaat vergelijken met de Smartschool-groepen (totaal, unieke gebruikers, rolvoorrang, afwezige accounts).
3. Bevestigen dat de werkelijke `users`-tabel in deze legacy database de gebruikte kolommen heeft en dat de nieuwe groep `administratie` de gewenste rechten krijgt.
4. Test met DB-backup: nieuw account, bestaande bijzondere rol, reeds geblokkeerd account, dubbele groepslidmaatschappen en verdwenen account.
5. Webinterface/wachtrij en weergave van geblokkeerde accounts controleren.
