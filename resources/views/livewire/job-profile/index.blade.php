<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :crumbs="[['label' => 'Stellen']]"
            :filters-active="($search !== '' ? 1 : 0) + ($statusFilter !== 'active' ? 1 : 0) + ($jobFamilyFilter ? 1 : 0)">
            <x-slot:filters>
                <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name suchen…" size="sm" />
                <x-people::filter-group label="Status">
                    <x-people::chip :active="! $statusFilter" wire:click="$set('statusFilter', '')">Alle</x-people::chip>
                    @foreach(['active' => 'Aktiv', 'draft' => 'Entwurf', 'archived' => 'Archiviert'] as $value => $label)
                        <x-people::chip :active="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">{{ $label }}</x-people::chip>
                    @endforeach
                </x-people::filter-group>
                @if(count($this->jobFamilies) > 0)
                    <x-people::filter-group label="Job-Family">
                        <x-people::chip :active="! $jobFamilyFilter" wire:click="$set('jobFamilyFilter', null)">Alle</x-people::chip>
                        @foreach($this->jobFamilies as $family)
                            <x-people::chip :active="$jobFamilyFilter === $family" wire:click="$set('jobFamilyFilter', {{ \Illuminate\Support\Js::from($family) }})">{{ $family }}</x-people::chip>
                        @endforeach
                    </x-people::filter-group>
                @endif
            </x-slot:filters>
        </x-people::actionbar>
    </x-slot>


    <x-people::body>
        @php
            $statusConfig = ['active' => ['success', 'Aktiv'], 'draft' => ['info', 'Entwurf'], 'archived' => ['neutral', 'Archiviert']];
            $today = now()->startOfDay();
            $cols = 'md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.6fr)_6rem_6.5rem_5.5rem]';
        @endphp

        <x-nx-card flush>
            <div class="hidden md:grid {{ $cols }} gap-4 px-4 py-2 border-b border-[color:var(--nx-line)] text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">
                <span>Stelle</span><span>Besetzt durch</span><span>Level</span><span>Status</span><span></span>
            </div>
            @forelse($groups as $unit => $profiles)
                <div class="px-4 pt-3 pb-1.5 bg-[color:var(--nx-bg)] border-b border-[color:var(--nx-line)] flex items-baseline gap-2">
                    <span class="text-xs font-semibold text-[color:var(--nx-text)]">{{ $unit !== '' ? $unit : 'Ohne Bereich' }}</span>
                    <span class="text-[11px] text-[color:var(--nx-faint)] tabular-nums">{{ $profiles->count() }} {{ $profiles->count() === 1 ? 'Stelle' : 'Stellen' }}</span>
                </div>
                @foreach($profiles as $profile)
                    @php
                        $sc = $statusConfig[$profile->status] ?? $statusConfig['draft'];
                        $holders = $profile->assignments->filter(fn ($a) => ! $a->valid_to || $a->valid_to->gte($today));
                    @endphp
                    <div wire:key="jp-{{ $profile->id }}"
                         class="group relative grid grid-cols-1 {{ $cols }} gap-x-4 gap-y-1 items-center px-4 py-2.5 border-b border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)] {{ $profile->status === 'archived' ? 'opacity-60' : '' }}">
                        <a href="{{ route('people.job-profiles.show', $profile->id) }}" wire:navigate
                           class="min-w-0 truncate font-medium text-[color:var(--nx-text)] after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline focus-visible:after:outline-2 focus-visible:after:outline-[color:var(--nx-accent)]">{{ $profile->name }}</a>
                        <span class="min-w-0 truncate text-sm">
                            @if($holders->isEmpty())
                                <span class="text-[color:var(--nx-warning)]">offen</span>
                            @else
                                <span class="text-[color:var(--nx-text)]">{{ $holders->map(fn ($a) => ($a->employee?->display_name ?? '#' . $a->employee_id) . ($a->percentage !== null && $a->percentage < 100 ? ' (' . $a->percentage . ' %)' : ''))->implode(', ') }}</span>
                            @endif
                        </span>
                        <span class="hidden md:block text-xs text-[color:var(--nx-muted)]">{{ $profile->level ? ucfirst($profile->level) : '—' }}</span>
                        <span class="hidden md:block"><x-nx-badge :variant="$sc[0]" dot>{{ $sc[1] }}</x-nx-badge></span>
                        <span class="hidden md:flex justify-end gap-0.5 relative z-10 opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                            <x-nx-button variant="ghost" size="sm" icon wire:click="toggleAssignments({{ $profile->id }})" title="Besetzung bearbeiten">@svg('heroicon-o-user-plus', 'w-4 h-4')</x-nx-button>
                            <x-nx-button variant="ghost" size="sm" icon wire:click="openEdit({{ $profile->id }})" title="Bearbeiten">@svg('heroicon-o-pencil', 'w-4 h-4')</x-nx-button>
                            @if($profile->status === 'archived')
                                <x-nx-button variant="ghost" size="sm" icon wire:click="unarchive({{ $profile->id }})" title="Reaktivieren">@svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')</x-nx-button>
                            @else
                                <x-nx-button variant="ghost" size="sm" icon wire:click="archive({{ $profile->id }})" wire:confirm="Stelle archivieren?" title="Archivieren">@svg('heroicon-o-archive-box', 'w-4 h-4')</x-nx-button>
                            @endif
                        </span>
                    </div>
                    @if($expandedProfileId === $profile->id)
                        <div class="px-4 py-4 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] space-y-3" wire:key="jp-assign-{{ $profile->id }}">
                            <div class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Besetzung · {{ $profile->name }}</div>
                            @forelse($profile->assignments as $assignment)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <div class="min-w-0 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="font-medium text-[color:var(--nx-text)]">{{ $assignment->employee?->display_name ?? 'Mitarbeiter #' . $assignment->employee_id }}</span>
                                        @if($assignment->is_primary)<x-nx-badge variant="info">Haupt</x-nx-badge>@endif
                                        @if($assignment->percentage !== null)<span class="text-xs text-[color:var(--nx-muted)] tabular-nums">{{ $assignment->percentage }} %</span>@endif
                                        @if($assignment->valid_from)<span class="text-xs text-[color:var(--nx-muted)]">ab {{ $assignment->valid_from->format('d.m.Y') }}</span>@endif
                                        @if($assignment->valid_to)<span class="text-xs text-[color:var(--nx-muted)]">bis {{ $assignment->valid_to->format('d.m.Y') }}</span>@endif
                                        @if($assignment->note)<span class="text-xs text-[color:var(--nx-muted)] italic truncate">{{ $assignment->note }}</span>@endif
                                    </div>
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="deleteAssignment({{ $assignment->id }})" wire:confirm="Zuweisung entfernen?" title="Entfernen">@svg('heroicon-o-trash', 'w-4 h-4')</x-nx-button>
                                </div>
                            @empty
                                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Noch niemand auf dieser Stelle.</p>
                            @endforelse

                            <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end pt-2 border-t border-[color:var(--nx-line)]">
                                <div class="md:col-span-2">
                                    <x-nx-input-select name="assignForm_employee_id" label="Mitarbeiter" wire:model="assignForm.employee_id" size="sm"
                                        nullable nullLabel="— wählen —"
                                        :options="collect($this->employeeOptions)->map(fn($ename, $eid) => ['value' => $eid, 'label' => $ename])->values()->all()" />
                                </div>
                                <x-nx-input-number name="assignForm_percentage" label="Anteil %" wire:model="assignForm.percentage" :min="0" :max="100" size="sm" />
                                <x-nx-input-date name="assignForm_valid_from" label="Von" wire:model="assignForm.valid_from" size="sm" />
                                <x-nx-input-date name="assignForm_valid_to" label="Bis" wire:model="assignForm.valid_to" size="sm" />
                                <div class="pb-2"><x-nx-input-checkbox name="assignForm_is_primary" label="Hauptstelle" wire:model="assignForm.is_primary" /></div>
                                <div class="md:col-span-5"><x-nx-input-text name="assignForm_note" label="Notiz" wire:model="assignForm.note" placeholder="Optional" size="sm" /></div>
                                <x-nx-button variant="primary" size="sm" wire:click="storeAssignment">@svg('heroicon-o-plus', 'w-4 h-4') <span>Besetzen</span></x-nx-button>
                            </div>
                        </div>
                    @endif
                @endforeach
            @empty
                <x-nx-empty icon="heroicon-o-identification">
                    Keine Stellen in dieser Ansicht.
                    <x-slot name="action">
                        <x-nx-button variant="primary" size="sm" wire:click="openCreate">@svg('heroicon-o-plus', 'w-4 h-4') Stelle anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @endforelse
        </x-nx-card>

        {{-- Create/Edit Modal --}}
        <x-nx-modal size="md" wire:model="showModal">
            <x-slot name="header">{{ $editingId ? 'Stelle bearbeiten' : 'Neue Stelle' }}</x-slot>
            <div class="space-y-4">
                <x-nx-input-text name="form_name" label="Name" wire:model="form.name" errorKey="form.name" placeholder="z.B. Senior Backend Engineer" />
                <div class="grid grid-cols-2 gap-4">
                    <x-nx-input-text name="form_level" label="Level" wire:model="form.level" errorKey="form.level" placeholder="z.B. Senior" />
                    <x-nx-input-text name="form_job_family" label="Job-Family" wire:model="form.job_family" errorKey="form.job_family" placeholder="z.B. Engineering" />
                </div>
                <x-nx-input-select name="form_status" label="Status" wire:model="form.status" errorKey="form.status" :options="[
                    ['value' => 'active', 'label' => 'Aktiv'],
                    ['value' => 'draft', 'label' => 'Entwurf'],
                    ['value' => 'archived', 'label' => 'Archiviert'],
                ]" />
                <x-nx-input-textarea name="form_description" label="Beschreibung" wire:model="form.description" :rows="3" placeholder="Optional" />
                <x-nx-input-textarea name="form_purpose" label="Zweck" wire:model="form.purpose" :rows="2" placeholder="Optional" />
                <div class="grid grid-cols-2 gap-4">
                    <x-nx-input-date name="form_effective_from" label="Gültig ab" wire:model="form.effective_from" errorKey="form.effective_from" />
                    <x-nx-input-date name="form_effective_to" label="Gültig bis" wire:model="form.effective_to" errorKey="form.effective_to" />
                </div>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-nx-button variant="ghost" wire:click="$set('showModal', false)">
                        Abbrechen
                    </x-nx-button>
                    <x-nx-button variant="primary" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                    </x-nx-button>
                </div>
            </x-slot>
        </x-nx-modal>
    </x-people::body>
</x-ui-page>
