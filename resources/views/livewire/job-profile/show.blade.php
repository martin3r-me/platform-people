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
            <div class="p-6 space-y-1">
                {{-- Status --}}
                <x-nx-property-row icon="heroicon-o-flag" label="Status">
                    <x-nx-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'neutral' : 'info') }}" dot>
                        {{ ucfirst($jobProfile->status) }}
                    </x-nx-badge>
                </x-nx-property-row>

                {{-- Einheit (Org-Wahrheit) --}}
                <div class="pb-3">
                    <x-nx-input-select name="unitId" label="Gehört zur Einheit" wire:model.live="unitId"
                        nullable nullLabel="— keine Einheit —"
                        :options="$unitOptions"
                        hint="Firma oder Abteilung; daraus ergibt sich der Stellenplan in der Organisation."
                        size="sm" />
                </div>

                {{-- Level --}}
                @if($jobProfile->level)
                    <x-nx-property-row icon="heroicon-o-chart-bar-square" label="Level">
                        <x-nx-badge variant="neutral">{{ ucfirst($jobProfile->level) }}</x-nx-badge>
                    </x-nx-property-row>
                @endif

                {{-- Job Family --}}
                @if($jobProfile->job_family)
                    <x-nx-property-row icon="heroicon-o-rectangle-group" label="Job Family">
                        <x-nx-badge variant="neutral">{{ $jobProfile->job_family }}</x-nx-badge>
                    </x-nx-property-row>
                @endif

                {{-- Gültigkeit --}}
                <x-nx-property-row icon="heroicon-o-calendar" label="Gültigkeit">
                    {{ $jobProfile->effective_from?->format('d.m.Y') ?? '—' }}
                    @if($jobProfile->effective_to)
                        – {{ $jobProfile->effective_to->format('d.m.Y') }}
                    @endif
                </x-nx-property-row>

                {{-- Kennzahlen --}}
                <div class="pt-4">
                    <x-nx-stat-grid cols="3">
                        <x-nx-stat label="Zuweisungen" value="{{ $this->assignments->count() }}" icon="heroicon-o-user-group" />
                        <x-nx-stat label="Skills" value="{{ $this->skillRecords->count() }}" icon="heroicon-o-academic-cap" />
                        <x-nx-stat label="Rollen" value="{{ $this->roles->count() }}" icon="heroicon-o-shield-check" />
                    </x-nx-stat-grid>
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
            <x-nx-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-semibold text-[color:var(--nx-text)]">{{ $jobProfile->name }}</h1>
                        <div class="flex items-center gap-2 mt-2">
                            @if($jobProfile->level)
                                <x-nx-badge variant="neutral">{{ ucfirst($jobProfile->level) }}</x-nx-badge>
                            @endif
                            @if($jobProfile->job_family)
                                <x-nx-badge variant="neutral">{{ $jobProfile->job_family }}</x-nx-badge>
                            @endif
                            <x-nx-badge variant="{{ $jobProfile->status === 'active' ? 'success' : ($jobProfile->status === 'archived' ? 'neutral' : 'info') }}" dot>
                                {{ ucfirst($jobProfile->status) }}
                            </x-nx-badge>
                        </div>
                    </div>
                </div>
            </x-nx-card>

            {{-- Übersicht --}}
            <x-nx-section icon="heroicon-o-document-text" title="Übersicht">
                <x-nx-card>
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Beschreibung</h3>
                            <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->description ?: '—' }}</p>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Zweck</h3>
                            <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->purpose ?: '—' }}</p>
                        </div>
                        @if($jobProfile->content)
                            <div>
                                <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-1">Inhalt</h3>
                                <p class="text-sm text-[color:var(--nx-text)] whitespace-pre-line">{{ $jobProfile->content }}</p>
                            </div>
                        @endif
                    </div>
                </x-nx-card>
            </x-nx-section>

            {{-- Anforderungen --}}
            <x-nx-section icon="heroicon-o-clipboard-document-list" title="Anforderungen">
                <x-nx-card>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Verantwortlichkeiten --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Verantwortlichkeiten</h3>
                            @php $responsibilities = is_array($jobProfile->responsibilities) ? $jobProfile->responsibilities : []; @endphp
                            @forelse($responsibilities as $item)
                                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 mt-0.5 text-[color:var(--nx-muted)] shrink-0')
                                    <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>

                        {{-- Requirements --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Anforderungen</h3>
                            @php $requirements = is_array($jobProfile->requirements) ? $jobProfile->requirements : []; @endphp
                            @forelse($requirements as $item)
                                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 mt-0.5 text-[color:var(--nx-muted)] shrink-0')
                                    <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>

                        {{-- Soft Skills --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Soft Skills</h3>
                            @php $softSkills = is_array($jobProfile->soft_skills) ? $jobProfile->soft_skills : []; @endphp
                            @forelse($softSkills as $item)
                                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-sparkles', 'w-4 h-4 mt-0.5 text-[color:var(--nx-muted)] shrink-0')
                                    <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>

                        {{-- KPIs --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">KPIs</h3>
                            @php $kpis = is_array($jobProfile->kpis) ? $jobProfile->kpis : []; @endphp
                            @forelse($kpis as $item)
                                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-chart-bar', 'w-4 h-4 mt-0.5 text-[color:var(--nx-muted)] shrink-0')
                                    <span>
                                        {{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}
                                        @if(is_array($item) && !empty($item['description']))
                                            <span class="text-xs text-[color:var(--nx-muted)]">— {{ $item['description'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>

                        {{-- Ausschlusskriterien --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Ausschlusskriterien</h3>
                            @php $exclusions = is_array($jobProfile->exclusion_criteria) ? $jobProfile->exclusion_criteria : []; @endphp
                            @forelse($exclusions as $item)
                                <div class="flex items-start gap-2 py-1 text-sm text-[color:var(--nx-text)]">
                                    @svg('heroicon-o-x-circle', 'w-4 h-4 mt-0.5 text-[color:var(--nx-danger)] shrink-0')
                                    <span>{{ is_array($item) ? ($item['name'] ?? implode(', ', $item)) : $item }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>

                        {{-- Arbeitsmodell & Reporting --}}
                        <div>
                            <h3 class="text-xs font-bold text-[color:var(--nx-muted)] uppercase tracking-wider mb-2">Arbeitsmodell &amp; Reporting</h3>
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
                                    <span class="text-[color:var(--nx-muted)]">{{ $row['label'] }}</span>
                                    <span class="text-[color:var(--nx-text)] font-medium">{{ $row['value'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-[color:var(--nx-muted)]">—</p>
                            @endforelse
                        </div>
                    </div>
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
                        <x-nx-empty icon="heroicon-o-academic-cap">Keine Skills zugeordnet.</x-nx-empty>
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
                        <x-nx-empty icon="heroicon-o-shield-check">Keine Default-Rollen hinterlegt.</x-nx-empty>
                    @endforelse
                </x-nx-card>
            </x-nx-section>

            {{-- Zuweisungen --}}
            <x-nx-section icon="heroicon-o-user-group" title="Zuweisungen">
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
                        <x-nx-empty icon="heroicon-o-user-group">Noch keine Zuweisungen.</x-nx-empty>
                    @endforelse
                </x-nx-card>
            </x-nx-section>
        </div>
    </x-ui-page-container>
</x-ui-page>
