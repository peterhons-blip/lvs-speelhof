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
            'lvs.view' => 'Leerlingenoverzicht en leerlingdossiers openen',
            'lvs.dashboard.view' => 'LVS-dashboard en statistieken bekijken',
            'lvs.meldingen.view' => 'Alle meldingen en details bekijken',
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
