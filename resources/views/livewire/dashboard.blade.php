{{--
    People Dashboard — der Mensch als Angestellter: Employee, Employment, Skills.
--}}

<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="People" />
    </x-slot>

    {{-- Actionbar = Seitenkopf mit Breadcrumb --}}
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'icon' => 'users'],
        ]" />
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container width="contained" spacing="space-y-6">
        {{-- Kennzahlen --}}
        <x-nx-stat-grid cols="3">
            <x-nx-stat label="Mitarbeiter" value="{{ $employeeCount }}" icon="heroicon-o-users"
                       :href="route('people.employees.index')" wire:navigate />
            <x-nx-stat label="Aktiv" value="{{ $activeCount }}" icon="heroicon-o-check-badge" />
            <x-nx-stat label="Skills" value="{{ $skillCount }}" icon="heroicon-o-academic-cap"
                       :href="route('people.skills.index')" wire:navigate />
        </x-nx-stat-grid>

        <x-nx-callout variant="info" icon="heroicon-o-information-circle" title="People">
            System of Record für den Menschen als Angestellten.
            Struktur & Rechte bleiben in Organization; hier lebt der Mensch und sein
            Fähigkeits-Bestand.
        </x-nx-callout>
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-4 space-y-1">
                <a href="{{ route('people.employees.index') }}" wire:navigate
                   class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                    @svg('heroicon-o-users', 'w-4 h-4') Mitarbeiter
                </a>
                <a href="{{ route('people.skills.index') }}" wire:navigate
                   class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                    @svg('heroicon-o-academic-cap', 'w-4 h-4') Skills
                </a>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
