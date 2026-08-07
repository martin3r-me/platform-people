<div>
    {{-- Modul-Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        People
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('people.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Verwaltung --}}
    <x-ui-sidebar-list label="Verwaltung">
        <x-ui-sidebar-item :href="route('people.employees.index')">
            @svg('heroicon-o-users', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Mitarbeiter</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('people.job-profiles.index')">
            @svg('heroicon-o-identification', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Jobprofile</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('people.skills.index')">
            @svg('heroicon-o-academic-cap', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Skills</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('people.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('people.employees.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-users', 'w-5 h-5')
            </a>
            <a href="{{ route('people.job-profiles.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-identification', 'w-5 h-5')
            </a>
            <a href="{{ route('people.skills.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-academic-cap', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
