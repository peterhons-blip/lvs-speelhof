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
            'lvs.view' => 'Leerlingenoverzicht bekijken',
            'lvs.meldingen.create' => 'Meldingen toevoegen',
            'lvs.settings.manage' => 'LVS-instellingen beheren (toekomstige module)',
        ],
    ],
    'bibliotheek' => [
        'label' => 'Bibliotheek',
        'permissions' => [
            'bibliotheek.view' => 'Bibliotheek openen',
            'bibliotheek.books.view' => 'Boekencatalogus bekijken',
            'bibliotheek.books.manage' => 'Boeken en exemplaren wijzigen/verwijderen',
            'bibliotheek.import.manage' => 'Boeken importeren',
            'bibliotheek.loans.view' => 'Uitleningen bekijken',
            'bibliotheek.loans.manage' => 'Uitlenen, inleveren, verlengen en individuele herinneringen',
            'bibliotheek.settings.manage' => 'Uitleentermijnen en herinneringsinstellingen wijzigen',
            'bibliotheek.messages.manage' => 'Smartschool-verzending in- of uitschakelen',
        ],
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
            'beheer.users.manage' => 'Gebruikersrollen toekennen en intrekken',
            'beheer.permissions.manage' => 'Rechten per rol en module beheren',
        ],
    ],
];
