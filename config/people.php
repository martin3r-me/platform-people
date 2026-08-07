<?php

/*
 * People — der Mensch als Angestellter.
 *
 * System of Record fuer das Beschaeftigungsverhaeltnis und den Faehigkeits-Bestand:
 * Employee (Stammsatz), Employment (Anstellung), Skill + EmployeeSkill (Kompetenz).
 *
 * Grenzlinie zu Organization: Organization traegt die STRUKTUR (Entities, Relations,
 * Rollen) und leitet daraus die Rechte ab. People traegt den MENSCHEN und seinen
 * Faehigkeits-Bestand. Abhaengigkeit fliesst nur People -> Organization, nie umgekehrt.
 */

return [
    'name' => 'People',
    'description' => 'People — der Mensch als Angestellter (Employee, Employment, Skills)',
    'version' => '1.0.0',

    'routing' => [
        'prefix' => 'people',
        'middleware' => ['web', 'auth'],
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'people.dashboard',
        'icon'  => 'heroicon-o-users',
        'order' => 45,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'people.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
            ],
        ],
    ],
];
