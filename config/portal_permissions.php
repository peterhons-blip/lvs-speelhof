<?php

// Central permission registry. Add capabilities here when a new module is built.
// Permissions are persisted per role in portal_role_permissions; unassigned means denied.
return [
    'dashboard' => [
        'label' => 'Dashboard',
        'permissions' => [
            'dashboard.view' => 'Dashboard bekijken',
        ],
    ],
    'lvs' => [
        'label' => 'Leerlingenvolgsysteem',
        'permissions' => [
            'lvs.secretariat_visits.manage' => 'Secretariaatsbezoeken 18-jarigen opvolgen en afvinken',
            'lvs.view' => 'Leerlingenoverzicht en leerlingdossiers openen',
            'lvs.dashboard.view' => 'LVS-dashboard en statistieken bekijken',
            'lvs.meldingen.view' => 'Gewone meldingen en details bekijken',
            'lvs.meldingen.confidential.view' => 'Vertrouwelijke meldingen bekijken (ook in rapportages)',
            'lvs.meldingen.confidential.create' => 'Vertrouwelijke meldingen registreren',
            'lvs.meldingen.create' => 'Gedragsmeldingen registreren',
            'lvs.reports.view' => 'Klassenraadoverzicht en tellingen bekijken',
            'lvs.followups.view' => 'Opvolging en sanctievoorstellen beoordelen en afhandelen',
            'lvs.settings.manage' => 'LVS-opvolgregels, meldingsdrempels en Smartschool-acties beheren',
        ],
    ],
    'bibliotheek' => [
        'label' => 'Bibliotheek',
        'permissions' => [
            'bibliotheek.view' => 'Bibliotheek openen',
            'bibliotheek.books.view' => 'Boekencatalogus en QR-codes bekijken',
            'bibliotheek.books.manage' => 'Boeken en exemplaren beheren, QR-labels bevestigen',
            'bibliotheek.import.manage' => 'Boeken importeren',
            'bibliotheek.loans.view' => 'Uitleningen en rapportages bekijken, CSV exporteren',
            'bibliotheek.loans.manage' => 'Uitlenen, inleveren, verlengen en individuele herinneringen',
            'bibliotheek.settings.manage' => 'Uitleentermijnen en herinneringsinstellingen wijzigen',
            'bibliotheek.messages.manage' => 'Smartschool-berichtsjablonen en verzending beheren',
        ],
    ],
    'bestellingen' => [
        'label' => 'Aanvraag bestelling (toekomstig)',
        'permissions' => ['bestellingen.view' => 'Bestelaanvragen bekijken (pas werkzaam na implementatie)'],
    ],
    'edbi' => [
        'label' => 'EDBI-instrument (toekomstig)',
        'permissions' => ['edbi.view' => 'EDBI-instrument bekijken (pas werkzaam na implementatie)'],
    ],
    'ema' => [
        'label' => 'EMA (toekomstig)',
        'permissions' => ['ema.view' => 'EMA-module bekijken (pas werkzaam na implementatie)'],
    ],
    'onboarding' => [
        'label' => 'Onboarding personeel (toekomstig)',
        'permissions' => ['onboarding.view' => 'Onboarding bekijken (pas werkzaam na implementatie)'],
    ],
    'nascholingen' => [
        'label' => 'Nascholingen (toekomstig)',
        'permissions' => ['nascholingen.view' => 'Nascholingen bekijken (pas werkzaam na implementatie)'],
    ],
    // External permissions are configuration only until verified identity and scoped routes exist.
    'leerling' => [
        'label' => 'Leerlingenportaal (voorbereiding)',
        'permissions' => [
            'leerling.dashboard.view' => 'Algemeen · Persoonlijk dashboard openen',
            'leerling.profile.view' => 'Algemeen · Eigen profiel bekijken',
            'leerling.lvs.view' => 'LVS · Eigen dossier openen',
            'leerling.incidents.view' => 'LVS · Vrijgegeven gewone meldingen bekijken',
            'leerling.followups.view' => 'LVS · Vrijgegeven opvolging bekijken',
            'leerling.incidents.reply' => 'LVS · Reageren op vrijgegeven meldingen',
            'leerling.library.catalog.view' => 'Bibliotheek · Catalogus bekijken',
            'leerling.library.availability.view' => 'Bibliotheek · Beschikbaarheid bekijken',
            'leerling.library.view' => 'Bibliotheek · Eigen actuele uitleningen bekijken',
            'leerling.library.history.view' => 'Bibliotheek · Eigen uitleenhistoriek bekijken',
            'leerling.library.reserve' => 'Bibliotheek · Boeken reserveren',
            'leerling.library.renew.request' => 'Bibliotheek · Verlenging aanvragen',
            'leerling.library.help' => 'Bibliotheekmedewerker · Werkruimte openen (individueel toe te kennen)',
            'leerling.library.help.books.create' => 'Bibliotheekmedewerker · Boeken en exemplaren registreren',
            'leerling.library.help.isbn.lookup' => 'Bibliotheekmedewerker · ISBN opzoeken en scannen',
            'leerling.library.help.labels.print' => 'Bibliotheekmedewerker · QR-labels genereren en afdrukken',
            'leerling.library.help.loans.create' => 'Bibliotheekmedewerker · Boeken uitlenen onder toezicht',
            'leerling.library.help.loans.return' => 'Bibliotheekmedewerker · Boeken innemen onder toezicht',
        ],
    ],
    'coaccount' => [
        'label' => 'Ouderportaal (voorbereiding)',
        'permissions' => [
            'coaccount.dashboard.view' => 'Algemeen · Persoonlijk ouderportaal openen',
            'coaccount.profile.view' => 'Algemeen · Eigen profiel bekijken',
            'coaccount.lvs.view' => 'LVS · Dossier van gekoppelde leerling openen',
            'coaccount.incidents.view' => 'LVS · Vrijgegeven gewone meldingen van gekoppelde leerling bekijken',
            'coaccount.followups.view' => 'LVS · Vrijgegeven opvolging bekijken',
            'coaccount.incidents.reply' => 'LVS · Reageren op vrijgegeven meldingen',
            'coaccount.library.catalog.view' => 'Bibliotheek · Catalogus bekijken',
            'coaccount.library.availability.view' => 'Bibliotheek · Beschikbaarheid bekijken',
            'coaccount.library.view' => 'Bibliotheek · Actuele uitleningen van gekoppelde leerling bekijken',
            'coaccount.library.history.view' => 'Bibliotheek · Uitleenhistoriek van gekoppelde leerling bekijken',
            'coaccount.library.reserve' => 'Bibliotheek · Boeken reserveren voor gekoppelde leerling',
            'coaccount.library.renew.request' => 'Bibliotheek · Verlenging aanvragen voor gekoppelde leerling',
        ],
    ],
    'beheer' => [
        'label' => 'Portaalbeheer',
        'permissions' => [
            'beheer.permissions.manage' => 'Rechten per rol en module beheren',
            'beheer.staff.manage' => 'Personeelsbeheer: medewerkers en schoolrollen beheren',
            'beheer.students.manage' => 'Leerlingenbeheer: leerlinggegevens en status beheren',
            'beheer.smartschool_test.send' => 'Smartschool-testberichten versturen',
            'beheer.student_sync.manage' => 'Leerlingensynchronisatie starten',
            'beheer.staff_sync.manage' => 'Personeelssynchronisatie starten',
            'beheer.scheduled_tasks.view' => 'Geplande taken en logs bekijken',
            'beheer.sent_items.view' => 'Verzonden Smartschool-berichten en e-mails met ontvangers bekijken',
            'beheer.birthdays.manage' => 'Verjaardagsberichten en 18-jarigen: ontvangers, kanalen, teksten, waarschuwingen en actiehistoriek'
        ],
    ],
];
