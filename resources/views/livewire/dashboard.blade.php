{{--
    People-Cockpit — alle aktiven Mitarbeiter (Menschen und Agenten) mit Reifestufe und Handlungsbedarf,
    dringendster zuerst. Struktur, Berichtslinie und Rechte bleiben in Organization.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="People" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :filters-active="$show !== 'all' ? 1 : 0">
            <x-slot:filters>
                <x-people::filter-group label="Zeigen" stack>
                    @foreach(['all' => 'Alle', 'needs' => 'Mit Handlungsbedarf', 'agent' => 'Agenten', 'human' => 'Menschen'] as $value => $label)
                        <x-people::chip row :active="$show === $value" :count="$counts[$value]" wire:click="setShow('{{ $value }}')">{{ $label }}</x-people::chip>
                    @endforeach
                </x-people::filter-group>
            </x-slot:filters>
        </x-people::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col">
    <x-people::statusbar>
        <span class="tabular-nums"><span class="font-semibold text-[color:var(--nx-text)]">{{ $counts['all'] }}</span> aktive Mitarbeiter</span>
        <span aria-hidden="true">·</span>
        <span class="tabular-nums">{{ $counts['agent'] }} Agenten, {{ $counts['human'] }} Menschen</span>
        <span aria-hidden="true">·</span>
        <button type="button" wire:click="setShow('needs')" class="tabular-nums hover:text-[color:var(--nx-text)] {{ $counts['needs'] > 0 ? 'text-[color:var(--nx-warning)] font-medium' : '' }}">
            {{ $counts['needs'] }} mit Handlungsbedarf
        </button>
    </x-people::statusbar>

    <x-people::body>
        @php
            $stages = \Platform\People\Services\DevelopmentCard::STAGES;
            $dot = ['critical' => 'bg-[color:var(--nx-danger)]', 'warning' => 'bg-[color:var(--nx-warning)]', 'info' => 'bg-[color:var(--nx-info)]'];
        @endphp

        <x-nx-card flush>
            <div class="hidden md:grid grid-cols-[minmax(0,1.4fr)_9rem_7rem_6rem_6rem_minmax(0,2fr)] gap-4 px-4 py-2 border-b border-[color:var(--nx-line)] text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">
                <span>Mitarbeiter</span><span>Reife</span><span>Dabei seit</span><span class="text-right">30 Tage</span><span class="text-right">Qualität</span><span>Handlungsbedarf</span>
            </div>
            @forelse($cards as $card)
                @php
                    $x = $card['experience'];
                    $top = $card['needs'][0] ?? null;
                    $idx = $card['stage'] ? array_search($card['stage'], array_keys($stages), true) : null;
                @endphp
                <a href="{{ route('people.employees.show', $card['employee_id']) }}" wire:navigate wire:key="card-{{ $card['employee_id'] }}"
                   class="grid grid-cols-2 md:grid-cols-[minmax(0,1.4fr)_9rem_7rem_6rem_6rem_minmax(0,2fr)] gap-x-4 gap-y-1.5 items-center px-4 py-3 border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[color:var(--nx-accent)]">
                    <span class="col-span-2 md:col-span-1 min-w-0 flex items-center gap-2">
                        <span class="truncate font-medium text-[color:var(--nx-text)]">{{ $card['name'] }}</span>
                        @if($card['kind'] === 'agent')<x-nx-badge variant="info">Agent</x-nx-badge>@endif
                        @if($card['position'])<span class="hidden lg:inline truncate text-xs text-[color:var(--nx-muted)]">{{ $card['position'] }}</span>@endif
                    </span>
                    <span class="flex items-center gap-2">
                        @if($idx !== null)
                            <span class="flex gap-0.5" aria-hidden="true">
                                @for($i = 0; $i < 4; $i++)<span class="w-2.5 h-1.5 rounded-full {{ $i <= $idx ? 'bg-[color:var(--nx-accent)]' : 'bg-[color:var(--nx-line)]' }}"></span>@endfor
                            </span>
                            <span class="text-xs text-[color:var(--nx-text)]">{{ $card['stage_label'] }}</span>
                        @else
                            <span class="text-xs text-[color:var(--nx-faint)]">—</span>
                        @endif
                    </span>
                    <span class="text-xs text-[color:var(--nx-muted)] tabular-nums">{{ $card['age_label'] }}</span>
                    <span class="text-xs md:text-right tabular-nums text-[color:var(--nx-text)]">
                        @if($x && $x['results_30d'] !== null)
                            {{ $x['results_30d'] }}
                            @if($x['results_prev_30d'] !== null)
                                @php $d = $x['results_30d'] - $x['results_prev_30d']; @endphp
                                <span class="{{ $d > 0 ? 'text-[color:var(--nx-success)]' : ($d < 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-faint)]') }}">{{ $d > 0 ? '↑' : ($d < 0 ? '↓' : '→') }}</span>
                            @endif
                        @else — @endif
                    </span>
                    <span class="text-xs md:text-right tabular-nums text-[color:var(--nx-text)]">{{ $x && $x['quality_30d'] !== null ? round($x['quality_30d'] * 100) . ' %' : '—' }}</span>
                    <span class="col-span-2 md:col-span-1 min-w-0 flex items-center gap-2 text-xs">
                        @if($top)
                            <span class="w-2 h-2 rounded-full shrink-0 {{ $dot[$top['severity']] }}"></span>
                            <span class="truncate text-[color:var(--nx-text)]">{{ $top['text'] }}</span>
                            @if(count($card['needs']) > 1)<span class="shrink-0 text-[color:var(--nx-faint)]">+{{ count($card['needs']) - 1 }}</span>@endif
                        @else
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[color:var(--nx-success)] shrink-0')
                            <span class="text-[color:var(--nx-muted)]">nichts offen</span>
                        @endif
                    </span>
                </a>
            @empty
                <x-nx-empty icon="heroicon-o-users">Keine Mitarbeiter in dieser Ansicht.</x-nx-empty>
            @endforelse
        </x-nx-card>
    </x-people::body>
    </div>
</x-ui-page>
