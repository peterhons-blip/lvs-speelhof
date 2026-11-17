# Dashboard per rol — ontwerpafspraken (nog niet geïmplementeerd)

Het Atheneum Portaal groeit van één LVS-module naar een schoolbreed portaal met LVS, EMA's, Onboarding personeel en Nascholingen.

## Gewenst gedrag

Het dashboard en het navigatiemenu passen zich aan de bevoegdheden van de ingelogde gebruiker aan. Dezelfde applicatie wordt door meerdere scholen/campussen gebruikt.

| Rol (functioneel concept) | Dashboard en mogelijkheden (voorstel) |
| --- | --- |
| Leerkracht | Eigen aanvragen (EMA's, nascholingen), eigen status, toegestane LVS-acties en meldingen |
| Administratie / secretariaat | Binnengekomen aanvragen behandelen, personeelsonboarding opvolgen en relevante administratieve overzichten |
| Directie | Schoolbreed overzicht van alle **toegestane** modules, aanvragen, goedkeuringen en rapporten; finale goedkeuringen waar van toepassing |
| ICT/platformbeheerder | Technische instellingen, account- en rollenbeheer, controle op integraties; toegang tot inhoudelijke leerling- of personeelsgegevens alleen als expliciet toegestaan |

**Directie ziet in het bedoelde ontwerp alle relevante processen van de eigen school**, maar niet automatisch de gegevens van andere scholen. Platformbeheer geeft evenmin onbeperkte toegang tot vertrouwelijke dossierinhoud.

## Technische randvoorwaarden vóór implementatie

1. Inventariseer de echte waarden van `users.groupId` en `groups.groupname`. De huidige sessie bevat `groupId` en `groupname`, maar groepsnamen alleen zijn **geen** beveiligde autorisatie.
2. Definieer centrale rechten/capabilities en wijs groepen daaraan toe. Houd rekening met meerdere rollen per persoon indien nodig.
3. Autoriseer elke route, controlleractie en databankquery server-side, inclusief schoolscoping; alleen knoppen verbergen biedt geen bescherming.
4. Maak rolgerichte startwidgets en modulemenu's op basis van rechten, niet op basis van een hardgecodeerde `groupname` in Blade.
5. Voorzien auditvelden (aanvrager, beoordelaar, tijdstip), statusovergangen en notificaties bij de toekomstige goedkeuringsflows.
6. Test minstens leerkracht, administratie, directie en gebruikers van een andere school met fictieve data.

## Status van de ontwerpbranch

De branch `feature/atheneum-portaal-layout` bevat uitsluitend een **rolneutrale eerste versie** van het vernieuwde login- en dashboardontwerp. De naam en groep van de huidige gebruiker worden getoond; alle ingelogde gebruikers zien voorlopig dezelfde actieve LVS-kaart en dezelfde uitgeschakelde toekomstige modules.

Er worden nog **geen nieuwe rollen, rechten, tabellen of goedkeuringsprocessen** ingevoerd. Dit document is een ontwerpafspraak voor de volgende ontwikkelfase.
