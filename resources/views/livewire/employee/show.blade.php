<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :crumbs="[
            ['label' => 'Mitarbeiter', 'href' => route('people.employees.index')],
            ['label' => $employee->display_name],
        ]">
            <x-slot:create>
                <x-people::menu-item icon="heroicon-o-briefcase" wire:click="openEmpCreate">Arbeitsvertrag</x-people::menu-item>
            </x-slot:create>
            @if($employee->org_entity_id && $directoryAvailable)
                <x-slot:more>
                    <x-people::menu-item icon="heroicon-o-link" wire:click="openCrmLink">Mit CRM verknüpfen</x-people::menu-item>
                </x-slot:more>
            @endif
        </x-people::actionbar>
    </x-slot>


    <x-people::body>
        {{-- Kopf: wer, Status, Stammdaten, Perspektive aus der Organisation --}}
        @php
            $sc = ['active' => ['success','Aktiv'], 'inactive' => ['neutral','Inaktiv'], 'left' => ['danger','Ausgeschieden']][$employee->status] ?? ['neutral', $employee->status];
        @endphp
        <header class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold text-[color:var(--nx-text)]">{{ $employee->display_name }}</h1>
                <x-nx-badge variant="{{ $sc[0] }}" dot>{{ $sc[1] }}</x-nx-badge>
                @if($employee->isAgent())<x-nx-badge variant="info">Agent</x-nx-badge>@endif
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                <span class="inline-flex items-center gap-1">@svg('heroicon-o-identification', 'w-3.5 h-3.5') Personalnr. {{ $employee->employee_number ?? '—' }}</span>
                <span class="inline-flex items-center gap-1">@svg('heroicon-o-share', 'w-3.5 h-3.5') {{ $employee->org_entity_id ? ('Knoten #' . $employee->org_entity_id) : 'kein Personen-Knoten' }}</span>
                @if($orgContext['carrier'])
                    <span class="inline-flex items-center gap-1">@svg('heroicon-o-building-office', 'w-3.5 h-3.5') {{ $orgContext['carrier']['name'] }}</span>
                @endif
                @if($orgContext['department'])
                    <span class="inline-flex items-center gap-1" title="abgeleitet aus dem Org-Graphen{{ !empty($orgContext['source']) ? ' (' . $orgContext['source'] . ')' : '' }}">@svg('heroicon-o-rectangle-group', 'w-3.5 h-3.5') {{ $orgContext['department']['name'] }}</span>
                @endif
                <span class="tabular-nums">{{ $employments->count() }} {{ $employments->count() === 1 ? 'Anstellung' : 'Anstellungen' }} · {{ $employee->skills_count }} Skills</span>
            </div>
        </header>

        @if($employee->isAgent() && $employee->org_entity_id)
            <x-nx-tabs class="!mb-0">
                <x-nx-tab :active="$tab === 'uebersicht'" wire:click="setTab('uebersicht')">Übersicht</x-nx-tab>
                <x-nx-tab :active="$tab === 'gedaechtnis'" wire:click="setTab('gedaechtnis')">Gedächtnis</x-nx-tab>
            </x-nx-tabs>
        @endif

        @if($tab === 'gedaechtnis')
            @livewire('people.employee.memory', ['employee' => $employee], key('employee-memory-' . $employee->id))
        @else
        @if($developmentCard)
            <x-people::development-card :card="$developmentCard" />
        @endif

        {{-- Anstellung (Arbeitsvertrag) --}}
        <x-nx-section icon="heroicon-o-briefcase" title="Anstellung" hint="{{ $employments->count() }}">
            <x-slot name="action">
                <x-nx-button variant="primary" wire:click="openEmpCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4') <span>Vertrag</span>
                </x-nx-button>
            </x-slot>

            @forelse($employments as $emp)
                <x-nx-card class="mb-3 {{ $emp->status !== 'active' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-[color:var(--nx-text)]">{{ \Platform\People\Models\Employment::TYPE_LABELS[$emp->employment_type] ?? $emp->employment_type }}</span>
                                @if($emp->employer)<span class="text-xs text-[color:var(--nx-muted)]">· {{ $emp->employer->name }}</span>@endif
                                @if($emp->is_fixed_term)<x-nx-badge variant="warning">befristet</x-nx-badge>@endif
                                <x-nx-badge variant="{{ $emp->status === 'active' ? 'success' : 'neutral' }}" dot>{{ $emp->status === 'active' ? 'Aktiv' : 'Beendet' }}</x-nx-badge>
                            </div>
                            <div class="text-xs text-[color:var(--nx-muted)] flex flex-wrap gap-x-4 gap-y-1">
                                <span>{{ optional($emp->started_on)->format('d.m.Y') ?? '—' }} – {{ optional($emp->ended_on)->format('d.m.Y') ?? ($emp->is_fixed_term ? optional($emp->fixed_term_end_date)->format('d.m.Y') : 'unbefristet') }}</span>
                                @if($emp->weekly_hours)<span>{{ rtrim(rtrim((string)$emp->weekly_hours,'0'),'.') }} Std/Wo</span>@endif
                                @if($emp->fte)<span>{{ rtrim(rtrim((string)$emp->fte,'0'),'.') }} FTE</span>@endif
                                @if($emp->annual_vacation_days)<span>{{ $emp->annual_vacation_days }} Urlaubstage</span>@endif
                                @if($emp->gross_amount)<span>{{ number_format((float)$emp->gross_amount, 2, ',', '.') }} € {{ $emp->wage_type === 'hourly' ? '/ Std' : '/ Monat' }}</span>@endif
                                @if($emp->work_location)<span>{{ $emp->work_location }}</span>@endif
                                @if($emp->probation_end_date)<span>Probezeit bis {{ optional($emp->probation_end_date)->format('d.m.Y') }}</span>@endif
                            </div>
                            @if($emp->note)<div class="text-xs text-[color:var(--nx-muted)] italic">{{ $emp->note }}</div>@endif
                        </div>
                        <div class="flex items-center gap-1">
                            <x-nx-button variant="ghost" icon wire:click="openEmpEdit({{ $emp->id }})">@svg('heroicon-o-pencil', 'w-4 h-4')</x-nx-button>
                            <x-nx-button variant="ghost" icon wire:click="deleteEmployment({{ $emp->id }})" wire:confirm="Anstellung wirklich entfernen?">@svg('heroicon-o-trash', 'w-4 h-4')</x-nx-button>
                        </div>
                    </div>
                </x-nx-card>
            @empty
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-briefcase">Noch kein Arbeitsvertrag hinterlegt.</x-nx-empty>
                </x-nx-card>
            @endforelse
        </x-nx-section>

        {{-- Skills --}}
        <x-nx-section icon="heroicon-o-academic-cap" title="Skills">
            <x-nx-card>
                @forelse($skills as $es)
                    @php
                        $lc = ['basic' => ['neutral','Basic'], 'advanced' => ['info','Advanced'], 'expert' => ['success','Expert']][$es->level] ?? ['neutral', $es->level];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 mr-2 mb-2 px-2 py-1 rounded-md border border-[color:var(--nx-line)] text-sm text-[color:var(--nx-text)]">
                        {{ $es->skill?->name ?? '—' }}
                        <x-nx-badge variant="{{ $lc[0] }}">{{ $lc[1] }}</x-nx-badge>
                    </span>
                @empty
                    <x-nx-empty icon="heroicon-o-academic-cap">Keine Skills zugeordnet.</x-nx-empty>
                @endforelse
            </x-nx-card>
        </x-nx-section>

        {{-- Jobprofile --}}
        <x-nx-section icon="heroicon-o-identification" title="Jobprofile">
            <x-nx-card flush>
                @forelse($assignments as $a)
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-[color:var(--nx-line)] last:border-0">
                        <a href="{{ $a->jobProfile ? route('people.job-profiles.show', $a->jobProfile->id) : '#' }}" wire:navigate class="text-sm text-[color:var(--nx-text)] hover:text-[color:var(--nx-accent)]">
                            {{ $a->jobProfile?->name ?? '—' }}
                        </a>
                        <div class="flex items-center gap-2 text-xs text-[color:var(--nx-muted)]">
                            @if($a->is_primary)<x-nx-badge variant="info">primär</x-nx-badge>@endif
                            <span>{{ $a->percentage }}%</span>
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-identification">Keine Jobprofile zugewiesen.</x-nx-empty>
                @endforelse
            </x-nx-card>
        </x-nx-section>

        {{-- CRM-Kontakt (graph-nativ am Personen-Knoten) --}}
        <x-nx-section icon="heroicon-o-user-circle" title="CRM-Kontakt">

            <x-nx-card>
                @if(!$employee->org_entity_id)
                    <x-nx-empty icon="heroicon-o-user-circle">Kein Personen-Knoten verknüpft — im Mitarbeiter setzen, dann ist CRM-Anreicherung möglich.</x-nx-empty>
                @elseif($crmProfile)
                    <div class="space-y-1">
                        @isset($crmProfile['name'])<x-nx-property-row icon="heroicon-o-user" label="Name">{{ $crmProfile['name'] }}</x-nx-property-row>@endisset
                        @isset($crmProfile['status'])<x-nx-property-row icon="heroicon-o-flag" label="Status">{{ $crmProfile['status'] }}</x-nx-property-row>@endisset
                        @isset($crmProfile['birth_date'])<x-nx-property-row icon="heroicon-o-cake" label="Geburtsdatum">{{ \Illuminate\Support\Carbon::parse($crmProfile['birth_date'])->format('d.m.Y') }}</x-nx-property-row>@endisset
                        @isset($crmProfile['phone'])<x-nx-property-row icon="heroicon-o-phone" label="Telefon">{{ $crmProfile['phone'] }}</x-nx-property-row>@endisset
                        @isset($crmProfile['email'])<x-nx-property-row icon="heroicon-o-envelope" label="E-Mail">{{ $crmProfile['email'] }}</x-nx-property-row>@endisset
                        @isset($crmProfile['address'])<x-nx-property-row icon="heroicon-o-map-pin" label="Anschrift">{{ $crmProfile['address'] }}</x-nx-property-row>@endisset
                    </div>
                @else
                    <x-nx-empty icon="heroicon-o-user-circle">Kein CRM-Kontakt verknüpft.</x-nx-empty>
                @endif
            </x-nx-card>
        </x-nx-section>
        @endif
    </x-people::body>

    {{-- Anstellungs-Modal (Arbeitsvertrag) --}}
    <x-nx-modal size="lg" wire:model="showEmpModal">
        <x-slot name="header">{{ $editingEmpId ? 'Arbeitsvertrag bearbeiten' : 'Neuer Arbeitsvertrag' }}</x-slot>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <x-nx-input-select name="empForm_employer_id" label="Arbeitgeber" wire:model.live="empForm.employer_id"
                    nullable nullLabel="— kein —"
                    :options="$employers->map(fn($er) => ['value' => $er->id, 'label' => $er->name])->all()" />
                @if($employers->isEmpty())
                    <p class="text-xs text-[color:var(--nx-muted)] mt-1">Noch keine Arbeitgeber angelegt — unter „Arbeitgeber" pflegen (belegt Vertragsfelder vor).</p>
                @endif
            </div>
            <x-nx-input-select name="empForm_employment_type" label="Art" wire:model="empForm.employment_type" :options="[
                ['value' => 'regular', 'label' => 'Vollzeit'],
                ['value' => 'part_time', 'label' => 'Teilzeit'],
                ['value' => 'temporary', 'label' => 'Befristet'],
                ['value' => 'marginal', 'label' => 'Minijob'],
                ['value' => 'freelance', 'label' => 'Freelance'],
                ['value' => 'agent', 'label' => 'Agent'],
            ]" />
            <x-nx-input-select name="empForm_status" label="Status" wire:model="empForm.status" :options="[
                ['value' => 'active', 'label' => 'Aktiv'],
                ['value' => 'ended', 'label' => 'Beendet'],
            ]" />
            <x-nx-input-date name="empForm_started_on" label="Eintritt" wire:model="empForm.started_on" />
            <x-nx-input-date name="empForm_ended_on" label="Austritt" wire:model="empForm.ended_on" />
            <div class="flex items-center pt-6">
                <x-nx-input-checkbox name="fixedterm" label="Befristet" wire:model.live="empForm.is_fixed_term" />
            </div>
            <x-nx-input-date name="empForm_fixed_term_end_date" label="Befristung bis" wire:model="empForm.fixed_term_end_date" @disabled(!$empForm['is_fixed_term']) />
            <x-nx-input-date name="empForm_probation_end_date" label="Probezeit bis" wire:model="empForm.probation_end_date" />
            <x-nx-input-number name="empForm_annual_vacation_days" label="Urlaubstage / Jahr" wire:model="empForm.annual_vacation_days" :min="0" :max="365" placeholder="z.B. 30" />
            <x-nx-input-number name="empForm_weekly_hours" label="Wochenstunden" wire:model="empForm.weekly_hours" step="0.5" :min="0" placeholder="z.B. 40" />
            <x-nx-input-number name="empForm_fte" label="FTE" wire:model="empForm.fte" step="0.01" :min="0" :max="1" placeholder="0.00–1.00" />
            <x-nx-input-number name="empForm_weekly_days" label="Arbeitstage / Woche" wire:model="empForm.weekly_days" step="0.5" :min="0" :max="7" placeholder="z.B. 5" />
            <x-nx-input-select name="empForm_wage_type" label="Vergütungsart" wire:model="empForm.wage_type" nullable nullLabel="—" :options="[
                ['value' => 'salary', 'label' => 'Monatsgehalt'],
                ['value' => 'hourly', 'label' => 'Stundenlohn'],
            ]" />
            <x-nx-input-number name="empForm_gross_amount" label="Brutto (€)" wire:model="empForm.gross_amount" step="0.01" :min="0" placeholder="Betrag" />
            <div class="sm:col-span-2">
                <x-nx-input-text name="empForm_work_location" label="Arbeitsort" wire:model="empForm.work_location" placeholder="Stadt" />
            </div>
            <div class="sm:col-span-2">
                <x-nx-input-textarea name="empForm_note" label="Notiz" wire:model="empForm.note" :rows="2" placeholder="Optional" />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="$set('showEmpModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveEmployment">
                    @svg('heroicon-o-check', 'w-4 h-4') <span>{{ $editingEmpId ? 'Speichern' : 'Anlegen' }}</span>
                </x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Mit CRM verknüpfen --}}
    <x-nx-modal size="md" wire:model="showCrmModal">
        <x-slot name="header">Mit CRM verknüpfen</x-slot>

        <div class="mb-5">
            <label class="block text-xs font-medium text-[color:var(--nx-text)] mb-1">Neuen Kontakt anlegen</label>
            <div class="flex gap-2">
                <x-nx-input-text name="newContactName" wire:model="newContactName" placeholder="Vor- und Nachname" class="flex-1" />
                <x-nx-button variant="primary" wire:click="createAndLinkCrm">Anlegen &amp; verknüpfen</x-nx-button>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-[color:var(--nx-text)] mb-1">Bestehenden Kontakt suchen</label>
            <div class="flex gap-2 mb-3">
                <x-nx-input-text name="crmSearch" wire:model="crmSearch" wire:keydown.enter="searchCrm" placeholder="Name..." class="flex-1" />
                <x-nx-button variant="secondary" wire:click="searchCrm">Suchen</x-nx-button>
            </div>
            <div class="divide-y divide-[color:var(--nx-line)] max-h-64 overflow-y-auto">
                @forelse($crmResults as $r)
                    <button type="button" wire:click="linkExistingCrm({{ $r['id'] }})"
                            class="w-full text-left py-2 px-1 hover:bg-[color:var(--nx-hover)] flex items-center justify-between">
                        <span class="text-sm text-[color:var(--nx-text)]">{{ $r['label'] }}</span>
                        @if(!empty($r['subtitle']))<span class="text-xs text-[color:var(--nx-faint)]">{{ $r['subtitle'] }}</span>@endif
                    </button>
                @empty
                    <p class="text-sm text-[color:var(--nx-faint)] py-2">Keine Treffer — oben nach Name suchen.</p>
                @endforelse
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end">
                <x-nx-button variant="ghost" wire:click="$set('showCrmModal', false)">Schließen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
