<div>
    {{-- Modul-Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[color:var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        People
    </div>

    {{-- Bereiche stehen in der Actionbar; hier nur der Einstieg und die Mitarbeiter selbst --}}
    <x-ui-sidebar-list label="People">
        <x-ui-sidebar-item :href="route('people.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[color:var(--nx-text)]')
            <span class="ml-2 text-sm">Übersicht</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    @foreach(['Agenten' => $agents, 'Menschen' => $humans] as $label => $list)
        @if($list->isNotEmpty())
            <div x-show="!collapsed">
                <x-ui-sidebar-list :label="$label">
                    @foreach($list as $e)
                        <x-ui-sidebar-item :href="route('people.employees.show', $e->id)">
                            @svg($e->kind === 'agent' ? 'heroicon-o-cpu-chip' : 'heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-muted)] shrink-0')
                            <span class="ml-2 text-sm truncate">{{ $e->display_name }}</span>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            </div>
        @endif
    @endforeach

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('people.dashboard') }}" wire:navigate title="Übersicht" class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('people.employees.index') }}" wire:navigate title="Mitarbeiter" class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-users', 'w-5 h-5')
            </a>
            <a href="{{ route('people.job-profiles.index') }}" wire:navigate title="Stellen" class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-identification', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
