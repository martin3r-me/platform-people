{{-- Wirkungs-Karte eines Mitarbeiters (Services\ImpactScore): Score, vier Teile, Teilwerte zum Gegensteuern, Handlungsbedarf.
     :impact = ein Ergebnis aus ImpactScore. --}}
@props(['impact'])

@php
    $p = $impact['parts'];
    $ind = $impact['indicators'];
    $severity = [
        'critical' => ['bg-[color:var(--nx-danger)]', 'dringend'],
        'warning' => ['bg-[color:var(--nx-warning)]', 'prüfen'],
        'info' => ['bg-[color:var(--nx-info)]', 'Hinweis'],
    ];
    $num = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : round($v * 100) . ' %';
    $parts = [
        ['Leisten', $num($p['deliver']), 'abgenommene, geschätzte und dokumentierte Story Points'],
        ['Einzahlen', $num($p['contribute']), 'auf Key Results und erreichte Meilensteine'],
        ['Querdenken', $num($p['lateral']), 'von der Organisation aufgegriffen'],
        ['Zielgüte', $p['goal_quality'] === null ? '—' : $pct($p['goal_quality']), 'eigene Ziele ambitioniert (Optimum ~80 % Erreichung)'],
        ['Transparenz', $pct($p['transparency'] ?? null), 'verbindliche Vorgänge an Arbeitsobjekten statt freier Nachrichten'],
    ];
    $indicators = [
        ['geschätzt', $ind['estimated']],
        ['dokumentiert', $ind['documented']],
        ['ausgerichtet', $ind['aligned']],
        ['Schätzung trifft', $ind['calibration']],
        ['Zielerreichung', $ind['goal_attainment']],
        ['in Vorgängen', $ind['structured'] ?? null],
        ['an Objekten', $ind['situated'] ?? null],
    ];
@endphp

<x-nx-card>
    <div class="space-y-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div class="text-sm font-semibold text-[color:var(--nx-text)]">Wirkung</div>
            <div class="text-xs text-[color:var(--nx-muted)] tabular-nums">
                {{ \Illuminate\Support\Carbon::parse($impact['window']['from'])->format('d.m.') }}–{{ \Illuminate\Support\Carbon::parse($impact['window']['to'])->format('d.m.Y') }}
            </div>
        </div>

        <div class="flex items-baseline gap-3">
            <span class="text-3xl font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $num($impact['score']) }}</span>
            @php $t = $impact['trend']; @endphp
            <span class="text-sm tabular-nums {{ $t > 0 ? 'text-[color:var(--nx-success)]' : ($t < 0 ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-muted)]') }}">
                {{ $t > 0 ? '+' : '' }}{{ $num($t) }} gegenüber dem Quartal davor
            </span>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-3 m-0">
            @foreach($parts as [$label, $value, $hint])
                <div title="{{ $hint }}">
                    <dt class="text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $label }}</dt>
                    <dd class="m-0 text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[color:var(--nx-muted)] tabular-nums">
            @foreach($indicators as [$label, $value])
                <span>{{ $label }} <span class="text-[color:var(--nx-text)]">{{ $pct($value) }}</span></span>
            @endforeach
            <span>{{ $impact['counts']['done'] }} erledigt · {{ $impact['counts']['reopened'] }} wieder geöffnet</span>
        </div>

        @if(! empty($impact['adopted']))
            <div>
                <div class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Aufgegriffen</div>
                @foreach($impact['adopted'] as $a)
                    <div class="py-0.5 text-sm text-[color:var(--nx-text)]">{{ $a['title'] ?? $a['ref'] }} <span class="text-[color:var(--nx-muted)]">→ {{ $a['by_title'] ?? $a['by'] }}</span></div>
                @endforeach
            </div>
        @endif

        <div>
            <div class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Gegensteuern</div>
            @forelse($impact['needs'] as $need)
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

        @if(empty($impact['sources']))
            <p class="m-0 text-xs text-[color:var(--nx-muted)]">Noch meldet kein Modul Wirkung (Planner, Dev, Helpdesk, OKR, Management Book, Organisation).</p>
        @endif
    </div>
</x-nx-card>
