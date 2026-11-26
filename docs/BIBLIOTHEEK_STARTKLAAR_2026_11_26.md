# Bibliotheek: startklaar maken voor ingebruikname

## Nieuwe functionaliteit
- Bestaande Open Library opzoeking blijft primaire bron.
- Bij **ISBN** worden Open Library én Google Books gecontroleerd. Als Open Library een boek vindt, worden uitsluitend ontbrekende velden aangevuld met Google Books, **alleen wanneer Google Books exact hetzelfde ISBN bevestigt**; reeds ingevulde Open Library-gegevens worden niet overschreven. Als Open Library niets vindt, wordt Google Books als fallback gebruikt. Bij zoeken op titel of auteur wordt Google Books geprobeerd als Open Library geen zoekresultaten teruggeeft. De bestaande fysieke exemplaarbarcode blijft onafhankelijk.
- Dezelfde ISBN-fallback geldt voor bulkimport via de wachtrij.
- De bron wordt bij ISBN-opzoeking vermeld; foutmeldingen voor foutieve invoer, ontbrekende data, geen rechten, rate limiting en serverfouten zijn duidelijker.
- **Meta4Books**: niet aangesloten; eigen Products API/Zoek API vereisen contract/API-documentatie en credentials. Geen onbevestigde API-adressen of scraping gebruiken.
- Nieuw berichttype 'overdue' met apart aanpasbaar sjabloon via **Bibliotheek → Smartschool-berichten**.
- Uitleeninstellingen: eerste herinnering na X dagen te laat, iedere Y dagen herhalen, na Z **succesvol aangeboden** te-laat-herinneringen een eenmalige opvolging voor beleid.
- **Bibliotheek → Opvolging niet-ingeleverde boeken**: alleen voor medewerkers met `bibliotheek.messages.manage`; openstaande escalaties, behandelende persoon, notitie, afsluiten en historiek. **Er worden geen automatische sancties of Smartschool-berichten aan beleid verzonden.**
- Alle nieuwe instellingen zijn standaard `0` = uit. De bestaande globale Smartschool-schakelaar moet bovendien aan staan voor enige herinneringsverzending.
- Herinneringen gebruiken de bestaande scheduler van 08:00; geen extra cronjob. Het bestaande `library_loan_notifications` unieke kenmerk voorkomt dubbel verzenden voor dezelfde lening/berichttype/dag; mislukte of onzekere calls worden niet automatisch herhaald.
- Uitgeleende boeken die inmiddels zijn ingeleverd krijgen geen nieuwe herinneringen. Auteurs en barcode van het boek wijzigen niet.

## Gerichte migratie
Na backup van de centrale MySQL-databank, op de gekozen testomgeving:

```powershell
cd C:\xampp\htdocs\LVS
C:\xampp\php\php.exe artisan migrate --path=database/migrations/2026_11_26_160000_add_library_overdue_escalations.php --force
C:\xampp\php\php.exe artisan optimize:clear
```

Gebruik **niet** onbeheerd `php artisan migrate` in dit legacy project wegens mogelijke openstaande andere migraties.

## Veilig testen vóór livegang
1. Verifieer dat de centrale databank op SRV-WEB bereikbaar is.
2. Controleer ISBN-lookup met een bekend Open Library ISBN, vervolgens een ISBN dat mogelijk alleen Google Books herkent. Zoek ook op titel en auteur; verifieer de juiste editie en metadata.
3. Controleer of importen geen dubbele boekrecords maken en altijd een unieke fysieke barcode toekennen.
4. Controleer de fouten uit het live systeem via browser Network-tab (status en JSON response) én `storage/logs/laravel.log`. Een onbereikbare Open Library en een onbekend ISBN moeten duidelijk onderscheiden worden.
5. Open **Bibliotheek → Uitleeninstellingen**: waarden 0,0,0 moeten nog steeds geen te-laat-herinneringen sturen.
6. Zet op een fictieve testomgeving de waarden bijv. 1, 2 en 2; test via:
   ```powershell
   C:\xampp\php\php.exe artisan bibliotheek:herinneringen --test
   ```
   Controleer dat dit **geen echte Smartschool-berichten verzendt**. De normale taak op productie verstuurt echte berichten indien ingeschakeld.
7. Pas na controle van ontvanger, tekst, afzender `bib` en realistische testleningen de automatische berichten in een gecontroleerde omgeving aanzetten.
8. Controleer dat 1e te-laat-herinnering na 1 dag, volgende na 3 dagen, derde na 5 dagen wordt ingepland; na de tweede succesvol aangeboden herinnering moet één beleidopvolging ontstaan. Controleer dat teruggebrachte boeken uit de planning verdwijnen.
9. Controleer rechten: onbevoegden krijgen 403 voor escalatiepagina én afsluitactie, niet alleen een verborgen menu-item.
10. Geen productie-uitrol vóór de bibliotheekmedewerkers een representatieve proef hebben gedaan.

## Extra bronnen en veilige AI-uitbreiding (voorlopig alleen onderzocht)
- **Cultuurconnect / bibliotheek.be** biedt een officiële catalogus-API (vrije en rijke variant). Voor onze schoolapp moeten we de toegangsvoorwaarden, eventuele sleutel en metadatarechten beoordelen voordat we koppelen. Geen HTML-scraping.
- **ISBNsearch.org** verwijst voor databank/API-toegang naar ISBNdb; een account/contract kan nodig zijn. Nog geen directe koppeling.
- **Meta4Books** biedt Products API (ONIX XML) en Zoek API (JSON), maar vraagt een eigen aansluiting.
- **AI** kan op termijn relevante titels/edities suggereren of bestaande resultaten vergelijken. AI mag geen onbevestigde ISBN, auteur, titel, omslag of uitgever aanmaken. Alleen gegevens met controleerbare bron en exact ISBN komen in aanmerking voor automatisch invullen; een medewerker blijft eindverantwoordelijk.
- De automatische samenvoeging is bewust conservatief: twee leveranciers kunnen verschillende metadata of beschrijvingen voor dezelfde editie voeren. Een bestaand veld van Open Library blijft leidend. Leg de gewenste datakwaliteit vast met de bib-medewerkers.

## Nog open / externe afhankelijkheden
- Meta4Books contract en API-sleutels/documentatie aanvragen.
- Google Books kan desgewenst met een API-sleutel geconfigureerd worden via `GOOGLE_BOOKS_API_KEY` in de .env op de server; de configuratie leest die via `config/services.php`. Zonder sleutel wordt een publieke lookup geprobeerd, maar die kan door Google op basis van hun voorwaarden of limieten geweigerd worden.
- Fout in de live boekopzoeking: de code is beter bestand tegen ontbrekende resultaten en heeft een tweede bron; **de exacte oorspronkelijke live fout is niet gereproduceerd** zonder een foutieve ISBN/HTTP-respons en serverlogs.
- Escalaties naar beleid worden voorlopig als interne opvolging genoteerd; direct automatisch mailen naar een verantwoordelijke is bewust niet geactiveerd zonder vastgelegde ontvangers en beleid.
