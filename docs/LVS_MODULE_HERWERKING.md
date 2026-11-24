# LVS-module: overzicht, bevoegdheden en veilige doorontwikkeling

## Werking (november 2026)

De LVS-module maakt gebruik van de bestaande Laravel-applicatie en de bestaande tabellen:
`leerlingen`, `klassen`, `meldingen`, `meldingssoorten`, `meldingscategorien`.
De dagelijkse leerlingensynchronisatie uit Smartschool en de bestaande SOAP-koppeling blijven ongewijzigd.

### Nieuwe schermen

| URL | Route | Recht | Inhoud |
| --- | --- | --- | --- |
| `/lvs` | `lvs.dashboard` | `lvs.dashboard.view` | Actieve leerlingen, meldingen, meldingen per categorie en recent |
| `/leerlingen` | `leerlingen.index` | `lvs.view` | Zoek leerlingen op naam of klas |
| `/leerlingen/{id}` | `leerlingen.show` | `lvs.view` | Dossier; meldingen worden alleen geladen met `lvs.meldingen.view` |
| `/meldingen/nieuw` | `meldingen.select-student` | `lvs.meldingen.create` | Zoek actieve leerling en open direct het meldingsformulier |
| `/meldingen` | `meldingen.index` | `lvs.meldingen.view` | Alle meldingen: filters op naam, klas, categorie en datum |
| `/meldingen/add/{id}` | `meldingen.create` | `lvs.meldingen.create` | Melding met categorie, soort, gebeurtenisdatum en omschrijving |
| `/lvs/klassenraad` | `lvs.reports.index` | `lvs.reports.view` | On-demand statistiek voor klassenraden zonder automatische sancties |
| `/lvs/klassenraad/export` | `lvs.reports.export` | `lvs.reports.view` | CSV met klassen- en categorietellingen, zonder leerlingcommentaren |
| `/leerlingen/verjaardagen` | `leerlingen.verjaardagen` | `lvs.view` | Komende verjaardagen |

Alle routes hierboven blijven achter `EnsureSmartschoolAuthenticated`.
Elk nieuw/afgeschermd LVS-endpoint vereist bovendien rechten uit `portal_role_permissions` via `RequirePortalPermission`.

### Rollen: huidige personeelswerking

- ICT-coördinator: alle rechten via de bestaande centrale LibraryAccess-regel.
- Leerkracht, administratie, directie, leerlingbegeleiding, secretariaat, boekhouding en TAC:
  de gericht uitgevoerde migratie kent de eerder toegankelijke basisfuncties `lvs.view`,
  `lvs.dashboard.view`, `lvs.meldingen.view` en `lvs.meldingen.create` toe.
- Directie en leerlingbegeleiding: tevens `lvs.reports.view`.
- Nadien zijn de rechten per schoolrol instelbaar onder **Beheer > Rollen en rechten**.
  De migratie gebruikt `insertOrIgnore` en zal later gemaakte intrekkingen niet terugdraaien
  bij een herhaling.

**Let op:** deze personeelsrollen bepalen toegang tot de functie, nog NIET welke
individuele leerlingen of meldingen een leerkracht mag bekijken. Dat vereist
betrouwbare klas-/leskoppelingen en een expliciete beleidskeuze.

### Ouders en leerlingen: veilige voorbereiding, niet actief

`lvs_external_access` is een lege, standaard uitgeschakelde voorbereidingsstructuur.
`principal_type` ('student' of 'parent'), `principal_identifier`, `leerling_id`,
`verified_at` en `enabled=0` bieden ruimte voor latere, geverifieerde dossierkoppeling.
Er wordt geen account aangemaakt, geen login mogelijk gemaakt en geen toegang verleend.
Activatie vergt nog:
1. SSO-identiteit uit Smartschool betrouwbaar koppelen aan een leerling/co-account.
2. Vaststellen hoe gezag, toestemming en het bereiken van 18 jaar doorwerken.
3. Regels voor categorieën en gevoelige meldingen, plus tests tegen cross-student-toegang.
4. Een afzonderlijke portal-autorisatieflow voor externe accounts (geen hergebruik van personeelsrechten).

### Gedragsmeldingen

- Categorieën en soorten bestaan al; hun bestaande invoer blijft behouden.
- `gebeurdop` is reeds een apart verplicht datumveld. `created_at` is de ingavedatum.
- Meldingen zijn in het leerlingdossier gegroepeerd per categorie; het nieuwe overzicht
  biedt zoeken en filters.
- Na de tweede gerichte migratie krijgt elke **nieuwe** melding optioneel
  `auteur_gebruikersnaam` vanuit de vertrouwde Smartschool-sessie. Oude meldingen
  behouden een lege auteur.
- Klassenraadoverzicht toont tellingen per klas/categorie en leerlingen, maar schrijft
  niets terug naar leerlingen en verstuurt geen berichten.
- Historische Smartschool-diagnoseroutes `/smartschool/test-bericht`,
  `/smartschool/errorcodes` en `/smartschool/test-user` zijn verwijderd:
  ze waren zonder SSO bereikbaar. Gebruik de afgeschermde Smartschool-testtool onder Beheer.

### Automatische opvolging instellen (nieuwe module)

- **LVS → Instellingen** (`/lvs/instellingen`) is toegankelijk met `lvs.settings.manage`
  (standaard ICT en directie).
- De instellingen bestaan uit afzonderlijke, standaard **uitgeschakelde** regels.
  Een regel combineert het exacte aantal meldingen (1–500), de scope (alle categorieën,
  één categorie of specifieke soort), een telperiode in dagen (0 = onbeperkt) en een actie.
  Alle tellingen gebeuren volgens **registratiedatum**, op het moment dat een nieuwe melding
  in de bestaande `meldingen`-tabel wordt opgeslagen.
- Voor de vierde, zesde of achtste melding kunnen aparte regels worden gemaakt.
  De regel wordt alleen getriggerd bij het bereiken van dat aantal, niet opnieuw
  voor elke volgende melding. Er is geen achteraf-herberekening van oude meldingen.
- **Opvolging leerlingbegeleiding** of **sanctievoorstel**: er wordt uitsluitend een
  registratie `pending` gemaakt in `lvs_followup_events`. Het systeem legt nooit
  een sanctie op. Leerlingbegeleiding/directie kunnen onder **LVS → Opvolging**
  (`/lvs/opvolging`) de melding behandelen of gemotiveerd afsluiten. De persoon,
  datum en notitie worden vastgelegd.
- **Smartschool-bericht**: alleen wanneer een bevoegd beheerder de regel activeert en
  de automatische verzending expliciet bevestigt. Mogelijke ontvangers zijn de
  leerling, co-accounts 1–6, een vaste accountnaam voor leerlingbegeleiding en
  een vaste accountnaam voor de klastitularis. Automatisch bepalen van individuele
  klastitularissen is bewust nog niet gebouwd; een vaste accountnaam is voorlopig vereist.
  Onderwerp en tekst zijn instelbaar; placeholders `{voornaam}`, `{naam}`,
  `{aantal}`, `{categorie}`. De vrije meldingstekst wordt **nooit**
  automatisch in een Smartschool-bericht gekopieerd.
  Automatische ouderberichten worden vanaf 18 jaar geblokkeerd.
  **Let op:** een co-accountnummer is niet per definitie een bestaand, actief of
  gerechtigd ouderaccount. Activeer zulke verzending pas na controle van de lokale
  Smartschool-/privacyafspraken.
- Elke actie krijgt een log per melding, regel en ontvanger. Verzendingen worden
  *vooraf* geclaimd; mislukte/onzekere calls worden niet zonder controle herhaald.
  De bestaande centrale verzendhistoriek registreert bovendien Smartschool-calls.
- Het ontwerpen van regels is onafhankelijk van de cronjob: opvolging gebeurt bij
  het registreren van een nieuwe melding. Er is dus geen extra cronjob nodig.

**Gerichte derde migratie:**

```powershell
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_24_182000_create_lvs_followup_rules.php --force
```

**Test eerst met uitgeschakelde regels en fictieve data, daarna een ingeschakelde
niet-verzendende opvolgregel:** maak een regel bij de tweede melding voor
leerlingbegeleiding, voeg twee meldingen toe aan een testleerling, controleer
dat er exact één openstaande opvolging verschijnt en dat een derde melding
niet opnieuw dezelfde regel triggert. Controleer de beperkte toegang en
het gemotiveerd afhandelen. Smartschool-regels pas met expliciete
testafspraken en een gecontroleerd doelaccount activeren; dit verstuurt
echte Smartschool-berichten, **geen simulatie**.

### Bewust nog niet geactiveerd

- Leerlingenfoto's: toestemming, bron en opslaglocatie nog bepalen.
- Individuele leesrechten per leerkracht, klastitularis, directie of leerlingbegeleider:
  authenticatie + betrouwbare les-/klasrelaties en beleid nodig.
- Het configureren van drempels en automatische Smartschool-verzending is nu wel
  beschikbaar, maar staat standaard uit. Schoolbrede sanctiebeslissingen,
  oudermails, een klastitularis automatisch aan een leerling koppelen en een
  klassenraadcron zijn nog niet gebouwd.
- Ouder- en leerlinglogin: voorbereid maar uitgeschakeld.

### Installatie op Windows/XAMPP

Maak eerst een **backup** van de gedeelde centrale databank. Voer enkel deze
nieuwe migraties uit (niet het algemene `php artisan migrate` wegens oudere, pending standaardmigraties):

```powershell
cd C:\xampp\htdocs\LVS
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_24_180000_prepare_lvs_roles_and_external_access.php --force
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_24_181000_add_author_to_lvs_meldingen.php --force
C:\xampp\php\php.exe artisan optimize:clear
```

Controleer in de testomgeving met accounts van leerkracht, directie,
leerlingbegeleiding en een beperkt account:
- `/lvs` en de link vanuit het portaal.
- `/leerlingen`, zoeken op naam of klas.
- Een leerlingdossier met oude meldingen (auteur mag leeg zijn).
- Nieuwe melding: categorie/soort, datum, auteur en opslaan.
- `/meldingen`: alle filters en paginering.
- `/lvs/klassenraad`: alleen met `lvs.reports.view`.
- Wijzigen en intrekken van LVS-rechten onder Beheer, en vervolgens 403 op
  routes zonder rechten (niet alleen menu-items verbergen).
- Onaangemelde bezoeker en leerling-/ouderaccount mogen nooit dossierdetails zien.
- Check dat bestaande verjaardags-, Smartschool- en bibliotheektaken ongewijzigd blijven.

Dit is een feature-branch. Voer integratietests en PHP/Blade-checks uit vóór merge naar `main` of productie.
