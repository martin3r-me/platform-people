{{-- Entwicklungs-Karte eines Mitarbeiters (Services\DevelopmentCard): Reifestufe, Alter, Erfahrung, Handlungsbedarf.
     :card = eine Karte aus DevelopmentCard. --}}
@props(['card'])

@php
    $stages = \Platform\People\Services\DevelopmentCard::STAGES;
    $stageIndex = $card['stage'] ? array_search($card['stage'], array_keys($stages), true) : null;
    $x = $card['experience'];
    $severity = [
        'critical' => ['bg-[color:var(--nx-danger)]', 'dringend'],
        'warning' => ['bg-[color:var(--nx-warning)]', 'prüfen'],
        'info' => ['bg-[color:var(--nx-info)]', 'Hinweis'],
    ];
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.');
@endphp

<x-nx-card>
    <div class="space-y-4">
        {{-- Reife --}}
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div class="text-sm font-semibold text-[color:var(--nx-text)]">Entwicklung</div>
            <div class="text-xs text-[color:var(--nx-muted)] tabular-nums">
                dabei seit {{ $card['age_label'] }}@if($card['started_on']) · {{ \Illuminate\Support\Carbon::parse($card['started_on'])->format('d.m.Y') }}@endif
            </div>
        </div>

        @if($stageIndex !== null)
            <div>
                <div class="grid grid-cols-4 gap-1" role="img" aria-label="Reifestufe {{ $card['stage_label'] }}">
                    @foreach($stages as $key => $label)
                        <div class="h-1.5 rounded-full {{ $loop->index <= $stageIndex ? 'bg-[color:var(--nx-accent)]' : 'bg-[color:var(--nx-line)]' }}"></div>
                    @endforeach
                </div>
                <div class="mt-1.5 grid grid-cols-4 gap-1 text-[11px]">
                    @foreach($stages as $key => $label)
                        <span class="{{ $loop->index === $stageIndex ? 'font-semibold text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        @elseif($card['kind'] === 'human')
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Für Menschen gibt es noch keine Leistungsdaten — die Karte zeigt vorerst die Stammdaten.</p>
        @else
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Leistungsdaten sind gerade nicht verfügbar.</p>
        @endif

        {{-- Erfahrung --}}
        @if($x)
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 m-0">
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">Ergebnisse gesamt</dt>
                    <dd class="m-0 text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $fmt($x['results_total']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">Letzte 30 Tage</dt>
                    <dd class="m-0 text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">
                        {{ $fmt($x['results_30d']) }}
                        @if($x['results_30d'] !== null && $x['results_prev_30d'] !== null)
                            @php $d = $x['results_30d'] - $x['results_prev_30d']; @endphp
                            <span class="text-xs font-normal {{ $d > 0 ? 'text-[color:var(--nx-success)]' : ($d < 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-muted)]') }}">{{ $d > 0 ? '+' : '' }}{{ $d }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">Qualität 30 Tage</dt>
                    <dd class="m-0 text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $x['quality_30d'] === null ? '—' : round($x['quality_30d'] * 100) . ' %' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">Arbeitsweise</dt>
                    <dd class="m-0 text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">
                        {{ $x['rules_live'] }} <span class="text-xs font-normal text-[color:var(--nx-muted)]">Regeln{{ $x['rules_probation'] ? ', ' . $x['rules_probation'] . ' auf Probe' : '' }}</span>
                    </dd>
                </div>
            </dl>
            <div class="text-xs text-[color:var(--nx-muted)] tabular-nums">
                {{ $fmt($x['hours_total']) }} Std. verbucht
                · {{ $fmt($x['corrections_30d']) }} Korrekturen (30 Tage)
                @if($x['tokens_per_result']) · {{ $fmt($x['tokens_per_result']) }} Token je Ergebnis @endif
            </div>
        @endif

        {{-- Handlungsbedarf --}}
        <div>
            <div class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Handlungsbedarf</div>
            @forelse($card['needs'] as $need)
                @php [$dot, $label] = $severity[$need['severity']]; @endphp
                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                    <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $dot }}" title="{{ $label }}"></span>
                    <span>{{ $need['text'] }}</span>
                </div>
            @empty
                <div class="flex items-center gap-2 text-sm text-[color:var(--nx-muted)]">
                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[color:var(--nx-success)]') Nichts offen.
                </div>
            @endforelse
        </div>
    </div>
</x-nx-card>
