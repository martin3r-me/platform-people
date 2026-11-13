{{--
    People-Übersicht — Mitarbeiter (Menschen und Agenten), ihre Stellen und Fähigkeiten.
    Struktur, Berichtslinie und Rechte bleiben in Organization.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="People" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar />
    </x-slot>

    <x-people::body>
        <x-nx-stat-grid cols="3">
            <x-nx-stat label="Mitarbeiter" value="{{ $employeeCount }}" icon="heroicon-o-users"
                       :href="route('people.employees.index')" wire:navigate />
            <x-nx-stat label="Aktiv" value="{{ $activeCount }}" icon="heroicon-o-check-badge" />
            <x-nx-stat label="Skills" value="{{ $skillCount }}" icon="heroicon-o-academic-cap"
                       :href="route('people.skills.index')" wire:navigate />
        </x-nx-stat-grid>
    </x-people::body>
</x-ui-page>
