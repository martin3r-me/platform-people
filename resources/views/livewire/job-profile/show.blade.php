<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :crumbs="[
            ['label' => 'Stellen', 'href' => route('people.job-profiles.index')],
            ['label' => $jobProfile->name ?? 'Details'],
        ]" />
    </x-slot>


    <x-people::body>
        <div class="space-y-6">
            {{-- Kopf: Stelle, Einheit (Org-Wahrheit), Gültigkeit, Kennzahlen --}}
            <header class="flex flex-col lg:flex-row lg:items-start gap-4">
                <div class="flex-1 min-w-0 space-y-2">
                    <h1 class="text-xl font-semibold text-[color:var(--nx-text)]">{{ $jobProfile->name }}</h1>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($jobProfile->level)
                            <x-nx-badge variant="neutral">{{ ucfirst($jobProfile->level) }}</x-nx-badge>
                        @endif
                        @if($jobProfile->job_family)
                            <x-nx-badge variant="neutral">{{ $jobProfile->job_family }}</x-nx-badge>
                        @endif
                        <x-nx-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'neutral' : 'info') }}" dot>
                            {{ ['active' => 'Aktiv', 'draft' => 'Entwurf', 'archived' => 'Archiviert'][$jobProfile->status] ?? $jobProfile->status }}
                        </x-nx-badge>
                        @if($jobProfile->effective_from || $jobProfile->effective_to)
                            <span class="text-xs text-[color:var(--nx-muted)] tabular-nums">
                                gültig {{ $jobProfile->effective_from ? 'ab ' . $jobProfile->effective_from->format('d.m.Y') : '' }}{{ $jobProfile->effective_to ? ' bis ' . $jobProfile->effective_to->format('d.m.Y') : '' }}
                            </span>
                        @endif
                    </div>
                    @php
                        $holders = $this->assignments->filter(fn ($a) => ! $a->valid_to || $a->valid_to->gte(now()->startOfDay()));
                    @endphp
                    <div class="text-sm text-[color:var(--nx-text)]">
                        @if($holders->isEmpty())
                            <span class="text-[color:var(--nx-warning)]">Offen — noch niemand auf dieser Stelle</span>
                        @else
                            <span class="text-[color:var(--nx-muted)]">Besetzt durch</span>
                            @foreach($holders as $h)
                                <a href="{{ route('people.employees.show', $h->employee_id) }}" wire:navigate class="font-medium hover:text-[color:var(--nx-accent)] hover:underline">{{ $h->employee?->display_name ?? '#' . $h->employee_id }}</a>@if($h->percentage !== null && $h->percentage < 100)<span class="text-xs text-[color:var(--nx-muted)] tabular-nums"> ({{ $h->percentage }} %)</span>@endif{{ $loop->last ? '' : ',' }}
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="w-full lg:w-72 shrink-0">
                    <x-nx-input-select name="unitId" label="Bereich" wire:model.live="unitId"
                        nullable nullLabel="— kein Bereich —"
                        :options="collect($unitOptions)->map(fn ($name, $id) => ['value' => $id, 'label' => $name])->values()->all()"
                        size="sm" />
                </div>
            </header>

            {{-- Übersicht --}}
            <x-nx-section icon="heroicon-o-document-text" title="Auftrag">
                <x-nx-card>
                    <div class="space-y-4">
                        @if($jobProfile->purpose)
                            <div>
                                <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Zweck</h3>
                                <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->purpose }}</p>
                            </div>
                        @endif
                        @if($jobProfile->description)
                            <div>
                                <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Beschreibung</h3>
                                <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->description }}</p>
                            </div>
                        @endif
                        @if(! $jobProfile->purpose && ! $jobProfile->description && ! $jobProfile->content)
                            <p class="text-sm text-[color:var(--nx-muted)]">Noch kein Zweck beschrieben.</p>
                        @endif
                        @if($jobProfile->content)
                            <div>
                                <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Inhalt</h3>
                                <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->content }}</p>
                            </div>
                        @endif
                    </div>
                </x-nx-card>
            </x-nx-section>

            {{-- Profil: nur, was gefüllt ist --}}
            @php
                $label = fn ($k) => ['autonomy_level' => 'Autonomie', 'remote' => 'Remote', 'location' => 'Ort', 'hours' => 'Arbeitszeit'][$k] ?? ucfirst(str_replace('_', ' ', (string) $k));
                $text = fn ($item) => is_array($item) ? ($item['name'] ?? implode(', ', array_filter($item, 'is_scalar'))) : (string) $item;
                $list = fn ($v) => is_array($v) ? array_values(array_filter($v, fn ($i) => $i !== null && $i !== '' && $i !== [])) : [];
                $kv = [];
                foreach ([is_array($jobProfile->work_model) ? $jobProfile->work_model : [], is_array($jobProfile->reporting) ? $jobProfile->reporting : []] as $src) {
                    foreach ($src as $k => $v) {
                        // Berichtslinie kommt aus der Organisation, nicht aus dem Stellentext.
                        if ($k === 'reports_to' || $v === '' || $v === null || $v === false || is_array($v)) { continue; }
                        $kv[] = ['label' => $label($k), 'value' => is_bool($v) ? 'Ja' : (string) $v];
                    }
                }
                $blocks = array_filter([
                    ['title' => 'Verantwortlichkeiten', 'icon' => 'heroicon-o-check-circle', 'tone' => 'muted', 'items' => $list($jobProfile->responsibilities)],
                    ['title' => 'Kennzahlen', 'icon' => 'heroicon-o-chart-bar', 'tone' => 'muted', 'items' => $list($jobProfile->kpis)],
                    ['title' => 'Anforderungen', 'icon' => 'heroicon-o-check-circle', 'tone' => 'muted', 'items' => $list($jobProfile->requirements)],
                    ['title' => 'Soft Skills', 'icon' => 'heroicon-o-sparkles', 'tone' => 'muted', 'items' => $list($jobProfile->soft_skills)],
                    ['title' => 'Ausschlusskriterien', 'icon' => 'heroicon-o-x-circle', 'tone' => 'danger', 'items' => $list($jobProfile->exclusion_criteria)],
                ], fn ($b) => $b['items'] !== []);
            @endphp
            <x-nx-section icon="heroicon-o-clipboard-document-list" title="Profil">
                <x-nx-card>
                    @if($blocks === [] && $kv === [])
                        <p class="m-0 text-sm text-[color:var(--nx-muted)]">Noch keine Verantwortlichkeiten oder Kennzahlen beschrieben.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($blocks as $b)
                                <div>
                                    <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">{{ $b['title'] }}</h3>
                                    @foreach($b['items'] as $item)
                                        <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                            @svg($b['icon'], 'w-4 h-4 mt-0.5 shrink-0 ' . ($b['tone'] === 'danger' ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]'))
                                            <span>
                                                {{ $text($item) }}
                                                @if(is_array($item) && ! empty($item['description']))<span class="text-xs text-[color:var(--nx-muted)]">— {{ $item['description'] }}</span>@endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                            @if($kv !== [])
                                <div>
                                    <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Arbeitsmodell</h3>
                                    @foreach($kv as $row)
                                        <div class="flex items-center justify-between py-1 text-sm">
                                            <span class="text-[color:var(--nx-muted)]">{{ $row['label'] }}</span>
                                            <span class="text-[color:var(--nx-text)] font-medium">{{ $row['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </x-nx-card>
            </x-nx-section>

            {{-- Skills --}}
            <x-nx-section icon="heroicon-o-academic-cap" title="Skills">
                <x-nx-card flush>
                    @forelse($this->skillRecords as $skill)
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-[color:var(--nx-line)] last:border-0">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-medium text-[color:var(--nx-text)]">{{ $skill->name }}</span>
                                @if($skill->category)
                                    <x-nx-badge variant="neutral">{{ ucfirst($skill->category) }}</x-nx-badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if($skill->pivot->level)
                                    <x-nx-badge variant="info">{{ ucfirst($skill->pivot->level) }}</x-nx-badge>
                                @endif
                                @if($skill->pivot->is_required)
                                    <x-nx-badge variant="danger">Pflicht</x-nx-badge>
                                @else
                                    <x-nx-badge variant="neutral">Optional</x-nx-badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="m-0 px-4 py-3 text-sm text-[color:var(--nx-muted)]">Keine Skills zugeordnet.</p>
                    @endforelse
                </x-nx-card>
            </x-nx-section>

            {{-- Rollen (null-tolerant) --}}
            <x-nx-section icon="heroicon-o-shield-check" title="Rollen">
                <x-nx-card flush>
                    @forelse($this->roles as $role)
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-[color:var(--nx-line)] last:border-0">
                            <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $role->name ?? 'Rolle #' . $role->id }}</span>
                            @if($role->pivot && $role->pivot->percentage_share !== null)
                                <span class="text-xs text-[color:var(--nx-muted)]">{{ $role->pivot->percentage_share }}%</span>
                            @endif
                        </div>
                    @empty
                        <p class="m-0 px-4 py-3 text-sm text-[color:var(--nx-muted)]">Keine Rollen hinterlegt.</p>
                    @endforelse
                </x-nx-card>
            </x-nx-section>

            {{-- Zuweisungen --}}
            <x-nx-section icon="heroicon-o-user-group" title="Besetzung">
                <x-nx-card flush>
                    @forelse($this->assignments as $assignment)
                        <div class="px-4 py-3 border-b border-[color:var(--nx-line)] last:border-0">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="font-medium text-[color:var(--nx-text)]">{{ $assignment->employee?->display_name ?? 'Mitarbeiter #' . $assignment->employee_id }}</span>
                                @if($assignment->is_primary)
                                    <x-nx-badge variant="info">Primär</x-nx-badge>
                                @endif
                                @if($assignment->percentage !== null)
                                    <span class="text-xs text-[color:var(--nx-muted)]">{{ $assignment->percentage }}%</span>
                                @endif
                                @if($assignment->valid_from)
                                    <span class="text-xs text-[color:var(--nx-muted)]">ab {{ $assignment->valid_from->format('d.m.Y') }}</span>
                                @endif
                                @if($assignment->valid_to)
                                    <span class="text-xs text-[color:var(--nx-muted)]">bis {{ $assignment->valid_to->format('d.m.Y') }}</span>
                                @endif
                            </div>
                            @if($assignment->note)
                                <p class="text-xs text-[color:var(--nx-muted)] italic mt-1">{{ $assignment->note }}</p>
                            @endif
                            @php $effectiveRoles = $this->effectiveRolesByAssignment[$assignment->id] ?? collect(); @endphp
                            @if($effectiveRoles->isNotEmpty())
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <span class="text-xs text-[color:var(--nx-muted)] uppercase tracking-wider">Effektive Rollen:</span>
                                    @foreach($effectiveRoles as $share)
                                        @php $role = $share['role'] ?? null; @endphp
                                        <x-nx-badge variant="neutral">
                                            {{ $role->name ?? 'Rolle #' . ($share['role_id'] ?? '?') }}
                                            @if(($share['percentage_share'] ?? null) !== null)
                                                · {{ $share['percentage_share'] }}%
                                            @endif
                                        </x-nx-badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="m-0 px-4 py-3 text-sm text-[color:var(--nx-muted)]">Noch niemand auf dieser Stelle.</p>
                    @endforelse
                </x-nx-card>
            </x-nx-section>
        </div>
    </x-people::body>
</x-ui-page>
