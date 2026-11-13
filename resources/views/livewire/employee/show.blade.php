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
            <x-slot:more>
                @if(! $employee->isAgent() && $employee->org_entity_id && $directoryAvailable)
                    <x-people::menu-item icon="heroicon-o-link" wire:click="openCrmLink">Mit CRM verknüpfen</x-people::menu-item>
                @endif
                <x-people::menu-item icon="heroicon-o-trash" danger wire:click="deleteEmployee" wire:confirm="Mitarbeiter wirklich entfernen? Stellen und Anstellungen bleiben im Verlauf erhalten.">Mitarbeiter entfernen</x-people::menu-item>
            </x-slot:more>
        </x-people::actionbar>
    </x-slot>


    <x-people::body>
        @php
            $sc = ['active' => ['success','Aktiv'], 'inactive' => ['neutral','Inaktiv'], 'left' => ['danger','Ausgeschieden']][$employee->status] ?? ['neutral', $employee->status];
            $since = $employments->sortBy('started_on')->first()?->started_on;
            $isAgent = $employee->isAgent();
            $levels = ['basic' => ['neutral','Basic'], 'advanced' => ['info','Advanced'], 'expert' => ['success','Expert']];
        @endphp

        {{-- Kopf: wer, welche Stelle in welchem Bereich, wo in der Organisation --}}
        <header class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold text-[color:var(--nx-text)]">{{ $employee->display_name }}</h1>
                <x-nx-badge variant="{{ $sc[0] }}" dot>{{ $sc[1] }}</x-nx-badge>
                @if($isAgent)<x-nx-badge variant="info">Agent</x-nx-badge>@endif
            </div>
            <div class="text-sm text-[color:var(--nx-text)]">
                @if($position)
                    <a href="{{ route('people.job-profiles.show', $position['position_id']) }}" wire:navigate class="font-medium hover:text-[color:var(--nx-accent)] hover:underline">{{ $position['position'] }}</a>
                    @if($position['unit'])<span class="text-[color:var(--nx-muted)]"> · {{ $position['unit'] }}</span>@endif
                @else
                    <span class="text-[color:var(--nx-muted)]">Noch keine Stelle</span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                @if($orgContext['carrier'])
                    <span class="inline-flex items-center gap-1">@svg('heroicon-o-building-office', 'w-3.5 h-3.5') {{ $orgContext['carrier']['name'] }}</span>
                @endif
                @if($supervisor)
                    <span class="inline-flex items-center gap-1" title="{{ $supervisor['source_label'] ?? '' }}">@svg('heroicon-o-arrow-up-right', 'w-3.5 h-3.5') berichtet an {{ $supervisor['name'] }}</span>
                @endif
                @if($since)
                    <span class="inline-flex items-center gap-1 tabular-nums">@svg('heroicon-o-calendar', 'w-3.5 h-3.5') dabei seit {{ $since->format('d.m.Y') }}</span>
                @endif
                @if($employee->employee_number)
                    <span class="inline-flex items-center gap-1">@svg('heroicon-o-identification', 'w-3.5 h-3.5') Personalnr. {{ $employee->employee_number }}</span>
                @endif
                @unless($employee->org_entity_id)
                    <span class="inline-flex items-center gap-1 text-[color:var(--nx-warning)]">@svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5') nicht mit der Organisation verknüpft</span>
                @endunless
            </div>
        </header>

        @if($isAgent && $employee->org_entity_id)
            <x-nx-tabs class="!mb-0">
                <x-nx-tab :active="$tab === 'uebersicht'" wire:click="setTab('uebersicht')">Übersicht</x-nx-tab>
                <x-nx-tab :active="$tab === 'gedaechtnis'" wire:click="setTab('gedaechtnis')">Gedächtnis</x-nx-tab>
            </x-nx-tabs>
        @endif

        @if($tab === 'gedaechtnis')
            @livewire('people.employee.memory', ['employee' => $employee], key('employee-memory-' . $employee->id))
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 items-start">
                {{-- Entwicklung --}}
                <div class="lg:col-span-2 min-w-0">
                    @if($developmentCard)
                        <x-people::development-card :card="$developmentCard" />
                    @endif
                </div>

                {{-- Fakten: Stellen, Anstellung, Skills, Kontakt --}}
                <div class="min-w-0 space-y-4">
                    <x-nx-card flush>
                        <div class="px-4 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Stellen</div>
                        @forelse($assignments as $a)
                            <a href="{{ $a->jobProfile ? route('people.job-profiles.show', $a->jobProfile->id) : '#' }}" wire:navigate
                               class="flex items-center justify-between gap-2 px-4 py-2 text-sm hover:bg-[color:var(--nx-hover)]">
                                <span class="min-w-0 truncate text-[color:var(--nx-text)]">{{ $a->jobProfile?->name ?? '—' }}</span>
                                <span class="shrink-0 flex items-center gap-2 text-xs text-[color:var(--nx-muted)] tabular-nums">
                                    @if($a->is_primary)<x-nx-badge variant="info">Haupt</x-nx-badge>@endif
                                    {{ $a->percentage }} %
                                </span>
                            </a>
                        @empty
                            <p class="m-0 px-4 pb-3 text-sm text-[color:var(--nx-muted)]">Keine Stelle zugewiesen — unter „Stellen“ besetzen.</p>
                        @endforelse
                        <div class="h-2"></div>
                    </x-nx-card>

                    <x-nx-card flush>
                        <div class="flex items-center justify-between px-4 pt-3 pb-1">
                            <span class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Anstellung</span>
                            <button type="button" wire:click="openEmpCreate" class="text-xs text-[color:var(--nx-accent)] hover:underline">+ Vertrag</button>
                        </div>
                        @forelse($employments as $emp)
                            <div class="group flex items-start justify-between gap-2 px-4 py-2 {{ $emp->status !== 'active' ? 'opacity-60' : '' }}">
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex flex-wrap items-center gap-1.5 text-sm text-[color:var(--nx-text)]">
                                        <span class="font-medium">{{ \Platform\People\Models\Employment::TYPE_LABELS[$emp->employment_type] ?? $emp->employment_type }}</span>
                                        @if($emp->employer)<span class="text-xs text-[color:var(--nx-muted)]">· {{ $emp->employer->name }}</span>@endif
                                        @if($emp->is_fixed_term)<x-nx-badge variant="warning">befristet</x-nx-badge>@endif
                                        @if($emp->status !== 'active')<x-nx-badge variant="neutral">beendet</x-nx-badge>@endif
                                    </div>
                                    <div class="text-xs text-[color:var(--nx-muted)] tabular-nums">
                                        {{ optional($emp->started_on)->format('d.m.Y') ?? '—' }} – {{ optional($emp->ended_on)->format('d.m.Y') ?? ($emp->is_fixed_term ? optional($emp->fixed_term_end_date)->format('d.m.Y') : 'unbefristet') }}
                                        @if($emp->weekly_hours) · {{ rtrim(rtrim((string)$emp->weekly_hours,'0'),'.') }} Std/Wo @endif
                                        @if($emp->fte) · {{ rtrim(rtrim((string)$emp->fte,'0'),'.') }} FTE @endif
                                    </div>
                                    @if($emp->note)<div class="text-xs text-[color:var(--nx-muted)] italic">{{ $emp->note }}</div>@endif
                                </div>
                                <div class="shrink-0 flex items-center opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="openEmpEdit({{ $emp->id }})" title="Bearbeiten">@svg('heroicon-o-pencil', 'w-4 h-4')</x-nx-button>
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="deleteEmployment({{ $emp->id }})" wire:confirm="Anstellung wirklich entfernen?" title="Entfernen">@svg('heroicon-o-trash', 'w-4 h-4')</x-nx-button>
                                </div>
                            </div>
                        @empty
                            <p class="m-0 px-4 pb-3 text-sm text-[color:var(--nx-muted)]">Noch kein Vertrag hinterlegt.</p>
                        @endforelse
                        <div class="h-2"></div>
                    </x-nx-card>

                    <x-nx-card flush>
                        <div class="px-4 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Skills</div>
                        <div class="px-4 pb-3">
                            @forelse($skills as $es)
                                @php $lc = $levels[$es->level] ?? ['neutral', $es->level]; @endphp
                                <span class="inline-flex items-center gap-1.5 mr-1.5 mb-1.5 px-2 py-0.5 rounded-md border border-[color:var(--nx-line)] text-xs text-[color:var(--nx-text)]">
                                    {{ $es->skill?->name ?? '—' }} <x-nx-badge variant="{{ $lc[0] }}">{{ $lc[1] }}</x-nx-badge>
                                </span>
                            @empty
                                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Noch keine Skills erfasst.</p>
                            @endforelse
                        </div>
                    </x-nx-card>

                    @unless($isAgent)
                        <x-nx-card flush>
                            <div class="flex items-center justify-between px-4 pt-3 pb-1">
                                <span class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Kontakt (CRM)</span>
                                @if($employee->org_entity_id && $directoryAvailable)
                                    <button type="button" wire:click="openCrmLink" class="text-xs text-[color:var(--nx-accent)] hover:underline">verknüpfen</button>
                                @endif
                            </div>
                            <div class="px-4 pb-3">
                                @if($crmProfile)
                                    <div class="space-y-1">
                                        @isset($crmProfile['email'])<x-nx-property-row icon="heroicon-o-envelope" label="E-Mail">{{ $crmProfile['email'] }}</x-nx-property-row>@endisset
                                        @isset($crmProfile['phone'])<x-nx-property-row icon="heroicon-o-phone" label="Telefon">{{ $crmProfile['phone'] }}</x-nx-property-row>@endisset
                                        @isset($crmProfile['address'])<x-nx-property-row icon="heroicon-o-map-pin" label="Anschrift">{{ $crmProfile['address'] }}</x-nx-property-row>@endisset
                                        @isset($crmProfile['birth_date'])<x-nx-property-row icon="heroicon-o-cake" label="Geburtsdatum">{{ \Illuminate\Support\Carbon::parse($crmProfile['birth_date'])->format('d.m.Y') }}</x-nx-property-row>@endisset
                                    </div>
                                @else
                                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">Kein Kontakt verknüpft.</p>
                                @endif
                            </div>
                        </x-nx-card>
                    @endunless
                </div>
            </div>
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
