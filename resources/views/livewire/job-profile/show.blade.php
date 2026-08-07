<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Jobprofile', 'href' => route('people.job-profiles.index')],
            ['label' => $jobProfile->name ?? 'Details'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <x-ui-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'muted' : 'info') }}" size="sm">
                        {{ ucfirst($jobProfile->status) }}
                    </x-ui-badge>
                </div>

                {{-- Level --}}
                @if($jobProfile->level)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Level</h3>
                        <x-ui-badge variant="secondary" size="sm">{{ ucfirst($jobProfile->level) }}</x-ui-badge>
                    </div>
                @endif

                {{-- Job Family --}}
                @if($jobProfile->job_family)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Job Family</h3>
                        <x-ui-badge variant="secondary" size="sm">{{ $jobProfile->job_family }}</x-ui-badge>
                    </div>
                @endif

                {{-- Gültigkeit --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Gültigkeit</h3>
                    <div class="text-sm text-[var(--ui-secondary)]">
                        {{ $jobProfile->effective_from?->format('d.m.Y') ?? '—' }}
                        @if($jobProfile->effective_to)
                            – {{ $jobProfile->effective_to->format('d.m.Y') }}
                        @endif
                    </div>
                </div>

                {{-- Kennzahlen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kennzahlen</h3>
                    <div class="space-y-2">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Zuweisungen</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $this->assignments->count() }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Skills</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $this->skillRecords->count() }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Rollen</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $this->roles->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Kopf --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">{{ $jobProfile->name }}</h1>
                        <div class="flex items-center gap-2 mt-2">
                            @if($jobProfile->level)
                                <x-ui-badge variant="secondary" size="sm">{{ ucfirst($jobProfile->level) }}</x-ui-badge>
                            @endif
                            @if($jobProfile->job_family)
                                <x-ui-badge variant="secondary" size="sm">{{ $jobProfile->job_family }}</x-ui-badge>
                            @endif
                            <x-ui-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'muted' : 'info') }}" size="sm">
                                {{ ucfirst($jobProfile->status) }}
                            </x-ui-badge>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Übersicht --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Übersicht</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Beschreibung</h3>
                        <p class="text-sm text-[var(--ui-secondary)] whitespace-pre-line">{{ $jobProfile->description ?: '—' }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Zweck</h3>
                        <p class="text-sm text-[var(--ui-secondary)] whitespace-pre-line">{{ $jobProfile->purpose ?: '—' }}</p>
                    </div>
                    @if($jobProfile->content)
                        <div>
                            <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-1">Inhalt</h3>
                            <p class="text-sm text-[var(--ui-secondary)] whitespace-pre-line">{{ $jobProfile->content }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Anforderungen --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Anforderungen</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Verantwortlichkeiten --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Verantwortlichkeiten</h3>
                        @php $responsibilities = is_array($jobProfile->responsibilities) ? $jobProfile->responsibilities : []; @endphp
                        @forelse($responsibilities as $item)
                            <div class="flex items-start gap-2 py-1 text-sm text-[var(--ui-secondary)]">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 mt-0.5 text-[var(--ui-muted)] shrink-0')
                                <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>

                    {{-- Requirements --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Anforderungen</h3>
                        @php $requirements = is_array($jobProfile->requirements) ? $jobProfile->requirements : []; @endphp
                        @forelse($requirements as $item)
                            <div class="flex items-start gap-2 py-1 text-sm text-[var(--ui-secondary)]">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 mt-0.5 text-[var(--ui-muted)] shrink-0')
                                <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>

                    {{-- Soft Skills --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Soft Skills</h3>
                        @php $softSkills = is_array($jobProfile->soft_skills) ? $jobProfile->soft_skills : []; @endphp
                        @forelse($softSkills as $item)
                            <div class="flex items-start gap-2 py-1 text-sm text-[var(--ui-secondary)]">
                                @svg('heroicon-o-sparkles', 'w-4 h-4 mt-0.5 text-[var(--ui-muted)] shrink-0')
                                <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>

                    {{-- KPIs --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">KPIs</h3>
                        @php $kpis = is_array($jobProfile->kpis) ? $jobProfile->kpis : []; @endphp
                        @forelse($kpis as $item)
                            <div class="flex items-start gap-2 py-1 text-sm text-[var(--ui-secondary)]">
                                @svg('heroicon-o-chart-bar', 'w-4 h-4 mt-0.5 text-[var(--ui-muted)] shrink-0')
                                <span>
                                    {{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}
                                    @if(is_array($item) && !empty($item['description']))
                                        <span class="text-xs text-[var(--ui-muted)]">— {{ $item['description'] }}</span>
                                    @endif
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>

                    {{-- Ausschlusskriterien --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Ausschlusskriterien</h3>
                        @php $exclusions = is_array($jobProfile->exclusion_criteria) ? $jobProfile->exclusion_criteria : []; @endphp
                        @forelse($exclusions as $item)
                            <div class="flex items-start gap-2 py-1 text-sm text-[var(--ui-secondary)]">
                                @svg('heroicon-o-x-circle', 'w-4 h-4 mt-0.5 text-red-400 shrink-0')
                                <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>

                    {{-- Arbeitsmodell & Reporting --}}
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider mb-2">Arbeitsmodell &amp; Reporting</h3>
                        @php
                            $workModel = is_array($jobProfile->work_model) ? $jobProfile->work_model : [];
                            $reporting = is_array($jobProfile->reporting) ? $jobProfile->reporting : [];
                            $kv = [];
                            foreach ($workModel as $k => $v) {
                                if ($v === '' || $v === null || $v === false) { continue; }
                                $kv['wm_' . $k] = ['label' => ucfirst(str_replace('_', ' ', (string) $k)), 'value' => is_bool($v) ? 'Ja' : (string) $v];
                            }
                            foreach ($reporting as $k => $v) {
                                if ($v === '' || $v === null || $v === false) { continue; }
                                $kv['rp_' . $k] = ['label' => ucfirst(str_replace('_', ' ', (string) $k)), 'value' => is_bool($v) ? 'Ja' : (string) $v];
                            }
                        @endphp
                        @forelse($kv as $row)
                            <div class="flex items-center justify-between py-1 text-sm">
                                <span class="text-[var(--ui-muted)]">{{ $row['label'] }}</span>
                                <span class="text-[var(--ui-secondary)] font-medium">{{ $row['value'] }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)]">—</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Skills --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Skills</h2>
                @forelse($this->skillRecords as $skill)
                    <div class="flex items-center justify-between py-2 border-b border-[var(--ui-border)]/40 last:border-0">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $skill->name }}</span>
                            @if($skill->category)
                                <x-ui-badge variant="secondary" size="sm">{{ ucfirst($skill->category) }}</x-ui-badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($skill->pivot->level)
                                <x-ui-badge variant="info" size="sm">{{ ucfirst($skill->pivot->level) }}</x-ui-badge>
                            @endif
                            @if($skill->pivot->is_required)
                                <x-ui-badge variant="danger" size="sm">Pflicht</x-ui-badge>
                            @else
                                <x-ui-badge variant="muted" size="sm">Optional</x-ui-badge>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[var(--ui-muted)]">Keine Skills zugeordnet.</p>
                @endforelse
            </div>

            {{-- Rollen (null-tolerant) --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Rollen</h2>
                @forelse($this->roles as $role)
                    <div class="flex items-center justify-between py-2 border-b border-[var(--ui-border)]/40 last:border-0">
                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $role->name ?? 'Rolle #' . $role->id }}</span>
                        @if($role->pivot && $role->pivot->percentage_share !== null)
                            <span class="text-xs text-[var(--ui-muted)]">{{ $role->pivot->percentage_share }}%</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-[var(--ui-muted)]">Keine Default-Rollen hinterlegt.</p>
                @endforelse
            </div>

            {{-- Zuweisungen --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zuweisungen</h2>
                @forelse($this->assignments as $assignment)
                    <div class="py-3 border-b border-[var(--ui-border)]/40 last:border-0">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $assignment->employee?->display_name ?? 'Mitarbeiter #' . $assignment->employee_id }}</span>
                            @if($assignment->is_primary)
                                <x-ui-badge variant="info" size="sm">Primär</x-ui-badge>
                            @endif
                            @if($assignment->percentage !== null)
                                <span class="text-xs text-[var(--ui-muted)]">{{ $assignment->percentage }}%</span>
                            @endif
                            @if($assignment->valid_from)
                                <span class="text-xs text-[var(--ui-muted)]">ab {{ $assignment->valid_from->format('d.m.Y') }}</span>
                            @endif
                            @if($assignment->valid_to)
                                <span class="text-xs text-[var(--ui-muted)]">bis {{ $assignment->valid_to->format('d.m.Y') }}</span>
                            @endif
                        </div>
                        @if($assignment->note)
                            <p class="text-xs text-[var(--ui-muted)] italic mt-1">{{ $assignment->note }}</p>
                        @endif
                        @php $effectiveRoles = $this->effectiveRolesByAssignment[$assignment->id] ?? collect(); @endphp
                        @if($effectiveRoles->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="text-xs text-[var(--ui-muted)] uppercase tracking-wider">Effektive Rollen:</span>
                                @foreach($effectiveRoles as $share)
                                    @php $role = $share['role'] ?? null; @endphp
                                    <x-ui-badge variant="secondary" size="sm">
                                        {{ $role->name ?? 'Rolle #' . ($share['role_id'] ?? '?') }}
                                        @if(($share['percentage_share'] ?? null) !== null)
                                            · {{ $share['percentage_share'] }}%
                                        @endif
                                    </x-ui-badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-[var(--ui-muted)]">Noch keine Zuweisungen.</p>
                @endforelse
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
