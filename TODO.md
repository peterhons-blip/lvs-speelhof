# Atheneum Portaal — TODO en roadmap

Bijgewerkt: 27 november 2026.
Een afgevinkt ontwikkelpunt betekent dat de code op main staat, niet noodzakelijk dat de functie al op productie is gevalideerd.

## P0 — Eerst testen en in gebruik nemen
### Bibliotheek
- [ ] Volledige uitleencyclus, QR-etiketten, herinneringen, reserveringen/wachtlijst en verlengingen testen.
- [ ] Rechten per leerling, co-account, personeelslid en bibliotheekmedewerker testen.
- [ ] Feedback bibliotheekmedewerkers verzamelen; voorlopig alleen noodzakelijke fixes.

### LVS en klaskoppelingen
- [ ] Nieuwste main binnenhalen en expliciete migraties controleren (090000 Smartschool-klas-ID, 100000 individuele Skore-relaties; 080000 vakken indien nog niet uitgevoerd).
- [ ] Leerlingensync uitvoeren en numerieke Smartschool-klas-ID's controleren.
- [ ] Klaskoppelingen in preview controleren: referentie 1037 leerkracht-klaskoppelingen en 119 titulariskoppelingen voor 87 klassen vóór de ID-wijziging.
- [ ] Aantal individuele Skore-lesrelaties controleren en steekproeven doen bij gesplitste klassen, keuzevakken, gezamenlijke lesgroepen en 6 APD.
- [ ] Na geslaagde preview --apply uitvoeren; opgeslagen vakken, titularissen en individuele lesrelaties controleren.
- [ ] Klassenbeheer, Mijn klassen, AJAX-fotolijsten, sessieherstel en dashboardzoekfunctie testen.
- [ ] Met verschillende personeelsaccounts testen: titularis, vakleerkracht, geen lesopdracht, directie en ICT; directe AJAX-URL van onbevoegde klas moet geweigerd worden.
- [ ] Nachtelijke taak 05.00 uur op productie controleren, inclusief status/log en gedrag bij onvolledige respons. Geen globale toegangsbeperking activeren vóór validatie.
- [ ] ZIP-foto-import, historische rechten, leerlingensync en externe portaalrechten op productie valideren.

## P1 — Klassen en leerlinggegevens
### Gebouwd
- [x] Leerlingen en personeel synchroniseren; stamnummer en Informat-internnummer bewaren.
- [x] Individuele foto-upload, ZIP-import en historische dossiers met afzonderlijk recht.
- [x] StudentSyncSafetyTest lokaal geslaagd (3 tests, 9 assertions).
- [x] Leerkrachten en vakken via Skore; titularissen via getClassTeachers, meerdere titularissen mogelijk.
- [x] Preview 1037 leerkracht-klas- en 119 titulariskoppelingen over 87 klassen succesvol vóór ID-wijziging.
- [x] Numerieke Smartschool-klas-ID naast groepscode opslaan; titularissen op ID koppelen, naam enkel controle.
- [x] Klaskoppelingen en individuele lesrelaties per schooljaar opslaan.
- [x] Nachtelijke klaskoppeling 05.00 uur en manuele preview/bevestiging; Excel-upload uitgeschakeld.
- [x] Klassenbeheer met volledige personeelsnamen, vakken en uitklapbare fotoklaslijsten.
- [x] Mijn klassen: titularisklassen bovenaan, eigen lesklassen met vakken, AJAX, foto's, zoeken, sessieherstel en dossier/nieuwe melding.
- [x] Dashboard: leerlingen zoeken vanaf twee tekens, foto's en links zonder reload; zoeken en Mijn klassen bovenaan navigatie.

### Geparkeerde uitbreidingen klasbeheer
- [ ] Personeelsgerichte beheerweergave (per leerkracht alle klassen, vakken en titularisklassen; zoeken en filteren).
- [ ] Synchronisatiekwaliteit per klas bewaken: lege/onvolledige respons, sterke daling van relaties, individuele afwijkingen; bron en laatste succesvolle sync zichtbaar maken.
- [ ] Schooljaarovergang en vervangingen testen; gecontroleerde handmatige uitzonderingen met einddatum en audit.
- [ ] Automatische Smartschool-fotosync onderzoeken.
- [ ] Skore-klasnaamfallback bij ontbrekende leerlinglijsten beoordelen; nooit onbevestigde lesrelaties gebruiken om dossierrechten toe te kennen.

## LVS — ontwikkeling voorlopig afgerond na leerlingendossier
### Leerlingendossier (laatste actieve uitbreiding)
- [x] Functionele tabbladen Algemeen, Leerkrachten en vakken, Meldingen, Opvolging, Sanctievoorstellen en Administratie 18+, afhankelijk van bestaande rechten.
- [x] Actuele titularissen en vakleerkrachten zichtbaar; snelactie nieuwe melding.
- [ ] Dossier testen met actieve/inactieve leerlingen en verschillende rechten; vertrouwelijke meldingen controleren.

### Parkeerplaats — geen actieve LVS-ontwikkeling zonder nieuwe prioritering
- Gesprekken, begeleiding en afzonderlijke historiek pas bij concrete behoefte.
- [ ] Opvolging vanuit Mijn klassen rechtstreeks openen zodra een geschikte leerlinggebonden route bestaat.
- [ ] Later bibliotheek, ICT-materiaal en afwezigheden integreren indien bevoegd.

### Klasgebonden toegangsrechten (geparkeerd; huidige rechten blijven gelden)
- [ ] Vakleerkracht: enkel effectieve eigen lesleerlingen uit Skore.
- [ ] Titularis: eigen volledige titularisklassen.
- [ ] Leerlingbegeleiding: toegewezen leerlingen of expliciet recht op alle leerlingen.
- [ ] Secretariaat, directie en ICT: toegang volgens expliciete bevoegdheden; vervangers via tijdelijke koppeling.
- [ ] Centrale server-side toegangscontrole op alle lijsten, zoekresultaten, foto's, dossiers, meldingen, opvolging, statistieken, exports en directe URL's.
- [ ] Vertrouwelijke meldingen blijven afzonderlijk afgeschermd; klaslidmaatschap geeft geen extra vertrouwelijke rechten.
- [ ] Testmatrix voor rollen, individuele uitzonderingen, inactieve leerlingen, andere scholen, directe URL's en verlopen relaties.
- [ ] Eerst proefmodus/controle, daarna pas strikte beperking activeren. Bestaande algemene LVS-rechten zijn nu nog ongewijzigd.

### Automatische opvolging en sancties (geparkeerd)
- [ ] Automatische melding naar de werkelijke klastitularis(sen) via de gevalideerde koppelingen, zonder dubbele berichten.
- [ ] Opvolgingsregels uitbreiden: drempel, periode, graad, finaliteit, categorie/soort, verantwoordelijke en menselijke goedkeuring.
- [ ] Meldingscategorieën, soorten en vrijgave-instellingen functioneel testen.
- [ ] Taken met verantwoordelijke, deadline, status, herinnering en historiek.
- [ ] Sanctiebeheer en uitvoering opvolgen; geen automatische sancties zonder menselijke beoordeling.
- [ ] Secretariaatsbezoeken 18+ en herinneringen testen.

### Begeleiding en klassenraad (geparkeerd)
- [ ] Gespreksregistratie, afspraken en beperkte zichtbaarheid.
- [ ] Huiswerkklas en taalklas: aanwezigheid, reden, opdrachten en opvolging.
- [ ] Klassenraaddashboard per klas, leerlingoverzicht, afdruk/export en commentaren.
- [ ] Leerlingen-/co-accountportaal en vrijgave/rechten verder testen.

## P2 — Andere portaalmodules
- [ ] EMA-aanvragen met goedkeuring door bevoegde personen en boekhouding.
- [ ] ICT-helpdesk; laptop-/materiaalbeheer voorlopig uitgesteld tot herprioritering.
- [ ] Bestelaanvragen.
- [ ] Onboarding en offboarding personeel.
- [ ] Nascholingsaanvragen.
- [ ] EDBI-instrument.
- [ ] Later evalueren: lokaalreservaties, interne formulieren, kennisbank.

## Uitgangspunten
Smartschool blijft communicatie- en leerplatform; Atheneum Portaal ondersteunt interne processen.
Gebruik waar passend AJAX zonder volledige page reload, uniforme foto's en volledige namen.
Gebruik stabiele ID's voor koppelingen, nooit alleen weergavenamen voor toegangsrechten.
Bewaar schooljaarhistoriek; respecteer schoolgrenzen, rechten en vertrouwelijkheid.
Bij onbetrouwbare synchronisatie bestaande koppelingen behouden en afwijkingen melden.
Voer bij legacy-databank uitsluitend de benodigde migraties expliciet uit; geen migrate:fresh/reset/refresh.
