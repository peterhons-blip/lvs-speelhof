# Bibliotheek: startklaar maken voor ingebruikname

## Nieuwe functionaliteit
- Bestaande Open Library opzoeking blijft primaire bron.
- Google Books wordt automatisch als **tweede bron** geprobeerd bij ISBN, titel en auteur wanneer Open Library geen resultaten geeft. ISBN-resultaten worden op het ISBN gecontroleerd. De bestaande fysieke exemplaarbarcode blijft onafhankelijk.
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

## Nog open / externe afhankelijkheden
- Meta4Books contract en API-sleutels/documentatie aanvragen.
- Fout in de live boekopzoeking: de code is beter bestand tegen ontbrekende resultaten en heeft een tweede bron; **de exacte oorspronkelijke live fout is niet gereproduceerd** zonder een foutieve ISBN/HTTP-respons en serverlogs.
- Escalaties naar beleid worden voorlopig als interne opvolging genoteerd; direct automatisch mailen naar een verantwoordelijke is bewust niet geactiveerd zonder vastgelegde ontvangers en beleid.
