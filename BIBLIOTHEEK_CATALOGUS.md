# Bibliotheek - fase 1: Boekencatalogus

Branch: `feature/bibliotheek-catalogus`.

## In deze fase

- Aparte boekencatalogus voor boeken en fysieke exemplaren.
- ISBN-10 en ISBN-13 met controlecijfer; handmatige registratie zonder ISBN is mogelijk.
- ISBN-gegevens ophalen via Open Library met een HTTP-timeout. De resultaten zijn bewerkbaar voor het opslaan; geen leerlinggegevens gaan naar Open Library.
- Boekgegevens: titel, ondertitel, auteur(s), uitgever, uitgave, categorie, omschrijving en externe omslag-URL.
- Unieke barcode/inventarisnummer per fysiek exemplaar, met locatie, staat en opmerkingen.
- Zoeken op titel, ISBN, auteur of exemplaarbarcode; detailpagina met alle exemplaren.

## Installatie op een lokale testomgeving

1. Zorg dat je op de featurebranch staat en de correcte test-`.env` gebruikt.
2. Maak eerst een back-up van je testdatabase.
3. Voer `C:\xampp\php\php.exe artisan migrate` uit; twee nieuwe tabellen worden aangemaakt:
   - `library_books`
   - `library_copies`
4. Voer `C:\xampp\php\php.exe artisan optimize:clear` uit.
5. Meld aan via Smartschool en open **Bibliotheek → Boekencatalogus**.
6. Test eerst met een bestaand ISBN, een handmatig boek en twee exemplaren met verschillende barcodes.

## Belangrijke beperkingen

- Nog geen uitleningen, reserveringen, terugbrengacties of Smartschool-berichten. De status van nieuwe exemplaren is standaard `beschikbaar`.
- Alle personen die door het bestaande Smartschool-middleware toegelaten worden, kunnen voorlopig ook boeken/exemplaren registreren. **Vóór productie moet bibliotheekbeheer beperkt worden via rollen/rechten**, inclusief server-side controles. Geen gevoelige leerlinggegevens zitten in deze fase.
- Nog geen ISBN-zoekfallback voor bronnen buiten Open Library. Niet alle Vlaamse of Nederlandse boeken hebben volledige metadata en omslagen.
- ISBN is uniek per bibliografische titel/uitgave. Meerdere fysieke exemplaren voeg je op de detailpagina toe.
- Geen delete-/editfunctie voor bestaande boeken of exemplaren; dit komt in een vervolgstap met audittrail.
- Database bij voorkeur niet rechtstreeks handmatig creëren: laat de Laravel-migratie dit doen. In geval van latere uitleningen moet `library_copies` een vaste verwijzing behouden; nooit exemplaren verwijderen die al uitleenhistoriek hebben.

## Volgende fase

- Rechten bibliotheekbeheerder/medewerker.
- Bewerken boekinformatie, exemplaarstatus, afvoer en logboek.
- Uitlenen/terugbrengen en verlengen met transacties en controle op beschikbaarheid.
- Aparte Smartschool-afzender/configuratie voor Bibliotheek, los van LVS; eerst testmodus zonder echte verzending.
- Automatische herinneringen met idempotente geplande taken.
