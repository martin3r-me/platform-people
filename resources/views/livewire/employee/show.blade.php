<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Mitarbeiter', 'href' => route('people.employees.index')],
            ['label' => $employee->display_name],
        ]">
            <x-ui-button variant="secondary-ghost" size="sm" :href="route('people.employees.index')">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                <span>Zurück</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Informationen --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-4 text-sm">
                <div>
                    <div class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $employee->display_name }}</div>
                    @php
                        $sc = ['active' => ['success','Aktiv'], 'inactive' => ['muted','Inaktiv'], 'left' => ['danger','Ausgeschieden']][$employee->status] ?? ['muted', $employee->status];
                    @endphp
                    <x-ui-badge variant="{{ $sc[0] }}" size="sm">{{ $sc[1] }}</x-ui-badge>
                </div>
                <dl class="space-y-3">
                    <div><dt class="text-[var(--ui-muted)] text-xs uppercase tracking-wide">Personalnummer</dt><dd class="text-[var(--ui-secondary)]">{{ $employee->employee_number ?? '—' }}</dd></div>
                    <div><dt class="text-[var(--ui-muted)] text-xs uppercase tracking-wide">Skills</dt><dd class="text-[var(--ui-secondary)]">{{ $employee->skills_count }}</dd></div>
                    <div><dt class="text-[var(--ui-muted)] text-xs uppercase tracking-wide">Anstellungen</dt><dd class="text-[var(--ui-secondary)]">{{ $employments->count() }}</dd></div>
                    <div><dt class="text-[var(--ui-muted)] text-xs uppercase tracking-wide">Personen-Knoten</dt><dd class="text-[var(--ui-secondary)]">{{ $employee->org_entity_id ? ('#' . $employee->org_entity_id) : '— nicht verknüpft —' }}</dd></div>
                </dl>

                {{-- Aus Org abgeleitete Perspektive --}}
                @if($orgContext['carrier'] || $orgContext['department'])
                    <div class="pt-4 border-t border-[var(--ui-border)]">
                        <h3 class="text-[var(--ui-muted)] text-xs uppercase tracking-wide mb-2">Perspektive (aus Org)</h3>
                        <dl class="space-y-2">
                            @if($orgContext['carrier'])
                                <div><dt class="text-[var(--ui-muted)] text-xs">Carrier</dt><dd class="text-[var(--ui-secondary)]">{{ $orgContext['carrier']['name'] }}</dd></div>
                            @endif
                            @if($orgContext['department'])
                                <div>
                                    <dt class="text-[var(--ui-muted)] text-xs">Abteilung</dt>
                                    <dd class="text-[var(--ui-secondary)]">{{ $orgContext['department']['name'] }}
                                        @if(!empty($orgContext['source']))<span class="text-[10px] text-[var(--ui-muted)]">({{ $orgContext['source'] }})</span>@endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                        <p class="text-[10px] text-[var(--ui-muted)] mt-2 italic">abgeleitet aus dem Org-Graphen</p>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        {{-- Anstellung (Arbeitsvertrag) --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Anstellung</h3>
                <x-ui-button variant="primary" size="sm" wire:click="openEmpCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4') <span>Vertrag</span>
                </x-ui-button>
            </div>

            @forelse($employments as $emp)
                <div class="border border-[var(--ui-border)] rounded-md p-4 mb-3 {{ $emp->status !== 'active' ? 'opacity-60' : '' }}">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-[var(--ui-secondary)]">{{ \Platform\People\Models\Employment::TYPE_LABELS[$emp->employment_type] ?? $emp->employment_type }}</span>
                                @if($emp->employer)<span class="text-xs text-[var(--ui-muted)]">· {{ $emp->employer->name }}</span>@endif
                                @if($emp->is_fixed_term)<x-ui-badge variant="warning" size="sm">befristet</x-ui-badge>@endif
                                <x-ui-badge variant="{{ $emp->status === 'active' ? 'success' : 'muted' }}" size="sm">{{ $emp->status === 'active' ? 'Aktiv' : 'Beendet' }}</x-ui-badge>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)] flex flex-wrap gap-x-4 gap-y-1">
                                <span>{{ optional($emp->started_on)->format('d.m.Y') ?? '—' }} – {{ optional($emp->ended_on)->format('d.m.Y') ?? ($emp->is_fixed_term ? optional($emp->fixed_term_end_date)->format('d.m.Y') : 'unbefristet') }}</span>
                                @if($emp->weekly_hours)<span>{{ rtrim(rtrim((string)$emp->weekly_hours,'0'),'.') }} Std/Wo</span>@endif
                                @if($emp->fte)<span>{{ rtrim(rtrim((string)$emp->fte,'0'),'.') }} FTE</span>@endif
                                @if($emp->annual_vacation_days)<span>{{ $emp->annual_vacation_days }} Urlaubstage</span>@endif
                                @if($emp->gross_amount)<span>{{ number_format((float)$emp->gross_amount, 2, ',', '.') }} € {{ $emp->wage_type === 'hourly' ? '/ Std' : '/ Monat' }}</span>@endif
                                @if($emp->work_location)<span>{{ $emp->work_location }}</span>@endif
                                @if($emp->probation_end_date)<span>Probezeit bis {{ optional($emp->probation_end_date)->format('d.m.Y') }}</span>@endif
                            </div>
                            @if($emp->note)<div class="text-xs text-[var(--ui-muted)] italic">{{ $emp->note }}</div>@endif
                        </div>
                        <div class="flex items-center gap-1">
                            <button wire:click="openEmpEdit({{ $emp->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)]">@svg('heroicon-o-pencil', 'w-4 h-4')</button>
                            <button wire:click="deleteEmployment({{ $emp->id }})" wire:confirm="Anstellung wirklich entfernen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[var(--ui-muted)]">Noch kein Arbeitsvertrag hinterlegt.</p>
            @endforelse
        </div>

        {{-- Skills --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Skills</h3>
            @forelse($skills as $es)
                @php
                    $lc = ['basic' => ['muted','Basic'], 'advanced' => ['info','Advanced'], 'expert' => ['success','Expert']][$es->level] ?? ['muted', $es->level];
                @endphp
                <span class="inline-flex items-center gap-1.5 mr-2 mb-2 px-2 py-1 rounded-md border border-[var(--ui-border)] text-sm">
                    {{ $es->skill?->name ?? '—' }}
                    <x-ui-badge variant="{{ $lc[0] }}" size="sm">{{ $lc[1] }}</x-ui-badge>
                </span>
            @empty
                <p class="text-sm text-[var(--ui-muted)]">Keine Skills zugeordnet.</p>
            @endforelse
        </div>

        {{-- Jobprofile --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Jobprofile</h3>
            @forelse($assignments as $a)
                <div class="flex items-center justify-between py-2 border-b border-[var(--ui-border)]/50 last:border-0">
                    <a href="{{ $a->jobProfile ? route('people.job-profiles.show', $a->jobProfile->id) : '#' }}" wire:navigate class="text-sm text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]">
                        {{ $a->jobProfile?->name ?? '—' }}
                    </a>
                    <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                        @if($a->is_primary)<x-ui-badge variant="info" size="sm">primär</x-ui-badge>@endif
                        <span>{{ $a->percentage }}%</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[var(--ui-muted)]">Keine Jobprofile zugewiesen.</p>
            @endforelse
        </div>

        {{-- CRM-Kontakt (graph-nativ am Personen-Knoten) --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">CRM-Kontakt</h3>
                @if($employee->org_entity_id && $directoryAvailable)
                    <x-ui-button variant="secondary" size="sm" wire:click="openCrmLink">
                        @svg('heroicon-o-link', 'w-4 h-4')
                        <span>Mit CRM verknüpfen</span>
                    </x-ui-button>
                @endif
            </div>

            @if(!$employee->org_entity_id)
                <p class="text-sm text-gray-400">Kein Personen-Knoten verknüpft — im Mitarbeiter setzen, dann ist CRM-Anreicherung möglich.</p>
            @elseif($crmProfile)
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    @isset($crmProfile['name'])<div><dt class="text-gray-500">Name</dt><dd class="text-gray-900">{{ $crmProfile['name'] }}</dd></div>@endisset
                    @isset($crmProfile['status'])<div><dt class="text-gray-500">Status</dt><dd class="text-gray-900">{{ $crmProfile['status'] }}</dd></div>@endisset
                    @isset($crmProfile['birth_date'])<div><dt class="text-gray-500">Geburtsdatum</dt><dd class="text-gray-900">{{ \Illuminate\Support\Carbon::parse($crmProfile['birth_date'])->format('d.m.Y') }}</dd></div>@endisset
                    @isset($crmProfile['phone'])<div><dt class="text-gray-500">Telefon</dt><dd class="text-gray-900">{{ $crmProfile['phone'] }}</dd></div>@endisset
                    @isset($crmProfile['email'])<div><dt class="text-gray-500">E-Mail</dt><dd class="text-gray-900">{{ $crmProfile['email'] }}</dd></div>@endisset
                    @isset($crmProfile['address'])<div class="sm:col-span-2"><dt class="text-gray-500">Anschrift</dt><dd class="text-gray-900">{{ $crmProfile['address'] }}</dd></div>@endisset
                </dl>
            @else
                <p class="text-sm text-gray-400">Kein CRM-Kontakt verknüpft.</p>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Anstellungs-Modal (Arbeitsvertrag) --}}
    @if($showEmpModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showEmpModal', false)">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">{{ $editingEmpId ? 'Arbeitsvertrag bearbeiten' : 'Neuer Arbeitsvertrag' }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Arbeitgeber</label>
                        <select wire:model.live="empForm.employer_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— kein —</option>
                            @foreach($employers as $er)
                                <option value="{{ $er->id }}">{{ $er->name }}</option>
                            @endforeach
                        </select>
                        @if($employers->isEmpty())
                            <p class="text-xs text-[var(--ui-muted)] mt-1">Noch keine Arbeitgeber angelegt — unter „Arbeitgeber" pflegen (belegt Vertragsfelder vor).</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Art</label>
                        <select wire:model="empForm.employment_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="regular">Vollzeit</option>
                            <option value="part_time">Teilzeit</option>
                            <option value="temporary">Befristet</option>
                            <option value="marginal">Minijob</option>
                            <option value="freelance">Freelance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model="empForm.status" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="active">Aktiv</option>
                            <option value="ended">Beendet</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Eintritt</label>
                        <input type="date" wire:model="empForm.started_on" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Austritt</label>
                        <input type="date" wire:model="empForm.ended_on" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" wire:model.live="empForm.is_fixed_term" id="fixedterm" class="rounded border-gray-300" />
                        <label for="fixedterm" class="text-sm text-gray-700">Befristet</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Befristung bis</label>
                        <input type="date" wire:model="empForm.fixed_term_end_date" @disabled(!$empForm['is_fixed_term']) class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Probezeit bis</label>
                        <input type="date" wire:model="empForm.probation_end_date" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Urlaubstage / Jahr</label>
                        <input type="number" min="0" max="365" wire:model="empForm.annual_vacation_days" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. 30" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Wochenstunden</label>
                        <input type="number" step="0.5" min="0" wire:model="empForm.weekly_hours" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. 40" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">FTE</label>
                        <input type="number" step="0.01" min="0" max="1" wire:model="empForm.fte" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="0.00–1.00" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Arbeitstage / Woche</label>
                        <input type="number" step="0.5" min="0" max="7" wire:model="empForm.weekly_days" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. 5" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vergütungsart</label>
                        <select wire:model="empForm.wage_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">—</option>
                            <option value="salary">Monatsgehalt</option>
                            <option value="hourly">Stundenlohn</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Brutto (€)</label>
                        <input type="number" step="0.01" min="0" wire:model="empForm.gross_amount" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Betrag" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Arbeitsort</label>
                        <input type="text" wire:model="empForm.work_location" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Stadt" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notiz</label>
                        <textarea wire:model="empForm.note" rows="2" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showEmpModal', false)">Abbrechen</x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="saveEmployment">
                        @svg('heroicon-o-check', 'w-4 h-4') <span>{{ $editingEmpId ? 'Speichern' : 'Anlegen' }}</span>
                    </x-ui-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Mit CRM verknüpfen --}}
    @if($showCrmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showCrmModal', false)">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Mit CRM verknüpfen</h3>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Neuen Kontakt anlegen</label>
                    <div class="flex gap-2">
                        <input type="text" wire:model="newContactName" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm" placeholder="Vor- und Nachname" />
                        <x-ui-button variant="primary" size="sm" wire:click="createAndLinkCrm">Anlegen &amp; verknüpfen</x-ui-button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bestehenden Kontakt suchen</label>
                    <div class="flex gap-2 mb-3">
                        <input type="text" wire:model="crmSearch" wire:keydown.enter="searchCrm" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm" placeholder="Name..." />
                        <x-ui-button variant="secondary" size="sm" wire:click="searchCrm">Suchen</x-ui-button>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        @forelse($crmResults as $r)
                            <button type="button" wire:click="linkExistingCrm({{ $r['id'] }})"
                                    class="w-full text-left py-2 px-1 hover:bg-gray-50 flex items-center justify-between">
                                <span class="text-sm text-gray-900">{{ $r['label'] }}</span>
                                @if(!empty($r['subtitle']))<span class="text-xs text-gray-400">{{ $r['subtitle'] }}</span>@endif
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 py-2">Keine Treffer — oben nach Name suchen.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showCrmModal', false)">Schließen</x-ui-button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
