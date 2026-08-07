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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="{{ route('people.employees.index') }}" wire:navigate
               class="block bg-white rounded-lg border border-[var(--ui-border)] p-5 hover:border-[var(--ui-primary)] transition-colors">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-md bg-[var(--ui-muted-5)]">@svg('heroicon-o-users', 'w-5 h-5 text-[var(--ui-secondary)]')</div>
                    <div>
                        <div class="text-2xl font-semibold text-[var(--ui-secondary)]">{{ $employeeCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Mitarbeiter</div>
                    </div>
                </div>
            </a>
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-md bg-[var(--ui-muted-5)]">@svg('heroicon-o-check-badge', 'w-5 h-5 text-[var(--ui-secondary)]')</div>
                    <div>
                        <div class="text-2xl font-semibold text-[var(--ui-secondary)]">{{ $activeCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Aktiv</div>
                    </div>
                </div>
            </div>
            <a href="{{ route('people.skills.index') }}" wire:navigate
               class="block bg-white rounded-lg border border-[var(--ui-border)] p-5 hover:border-[var(--ui-primary)] transition-colors">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-md bg-[var(--ui-muted-5)]">@svg('heroicon-o-academic-cap', 'w-5 h-5 text-[var(--ui-secondary)]')</div>
                    <div>
                        <div class="text-2xl font-semibold text-[var(--ui-secondary)]">{{ $skillCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] uppercase tracking-wide">Skills</div>
                    </div>
                </div>
            </a>
        </div>

        <x-nx-card>
            <div class="text-sm text-[color:var(--nx-muted)]">
                <strong>People</strong> — System of Record für den Menschen als Angestellten.
                Struktur & Rechte bleiben in Organization; hier lebt der Mensch und sein
                Fähigkeits-Bestand.
            </div>
        </x-nx-card>
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
</x-ui-page>
