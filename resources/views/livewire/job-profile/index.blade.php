<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Jobprofile'],
        ]">
            <x-nx-button variant="primary" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neu</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--nx-text)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name..." size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--nx-text)] uppercase tracking-wider mb-3">Status</h3>
                    <x-nx-input-select name="statusFilter" wire:model.live="statusFilter" nullable nullLabel="Alle Status" size="sm" :options="[
                        ['value' => 'active', 'label' => 'Aktiv'],
                        ['value' => 'draft', 'label' => 'Entwurf'],
                        ['value' => 'archived', 'label' => 'Archiviert'],
                    ]" />
                </div>
                @if(count($this->jobFamilies) > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[color:var(--nx-text)] uppercase tracking-wider mb-3">Job-Family</h3>
                        <x-nx-input-select name="jobFamilyFilter" wire:model.live="jobFamilyFilter" nullable nullLabel="Alle Families" size="sm"
                            :options="collect($this->jobFamilies)->map(fn($family) => ['value' => $family, 'label' => $family])->all()" />
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

    <x-ui-page-container>
        <x-nx-card flush>
            <x-nx-table>
                <x-nx-table-header>
                    <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                    <x-nx-table-header-cell>Level</x-nx-table-header-cell>
                    <x-nx-table-header-cell>Job-Family</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Rollen</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Skills</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Zuweisungen</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Status</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="right">Aktionen</x-nx-table-header-cell>
                </x-nx-table-header>
                <x-nx-table-body>
                    @forelse($this->jobProfiles as $profile)
                        @php
                            $statusConfig = [
                                'active' => ['variant' => 'success', 'label' => 'Aktiv'],
                                'draft' => ['variant' => 'info', 'label' => 'Entwurf'],
                                'archived' => ['variant' => 'neutral', 'label' => 'Archiviert'],
                            ];
                            $sc = $statusConfig[$profile->status] ?? $statusConfig['draft'];
                        @endphp
                        <x-nx-table-row class="{{ $profile->status === 'archived' ? 'opacity-60' : '' }}">
                            <x-nx-table-cell class="font-medium">
                                <a href="{{ route('people.job-profiles.show', $profile->id) }}" wire:navigate class="hover:text-[color:var(--nx-accent)] hover:underline">{{ $profile->name }}</a>
                            </x-nx-table-cell>
                            <x-nx-table-cell class="text-[color:var(--nx-muted)] text-xs">{{ $profile->level ?? '—' }}</x-nx-table-cell>
                            <x-nx-table-cell class="text-[color:var(--nx-muted)] text-xs">{{ $profile->job_family ?? '—' }}</x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <x-nx-badge>{{ $profile->roles_count }}</x-nx-badge>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <x-nx-badge>{{ $profile->skill_records_count }}</x-nx-badge>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <button wire:click="toggleAssignments({{ $profile->id }})" class="inline-flex items-center gap-1 px-2 py-1 rounded transition-colors hover:bg-[color:var(--nx-hover)] cursor-pointer">
                                    <x-nx-badge>{{ $profile->assignments_count }}</x-nx-badge>
                                    @if($expandedProfileId === $profile->id)
                                        @svg('heroicon-o-chevron-up', 'w-3 h-3 text-[color:var(--nx-muted)]')
                                    @else
                                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[color:var(--nx-muted)]')
                                    @endif
                                </button>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <x-nx-badge variant="{{ $sc['variant'] }}" dot>{{ $sc['label'] }}</x-nx-badge>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-nx-button variant="ghost" icon wire:click="openEdit({{ $profile->id }})">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </x-nx-button>
                                    @if($profile->status === 'archived')
                                        <x-nx-button variant="ghost" icon wire:click="unarchive({{ $profile->id }})" title="Reaktivieren">
                                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                        </x-nx-button>
                                    @else
                                        <x-nx-button variant="ghost" icon wire:click="archive({{ $profile->id }})" wire:confirm="Profil wirklich archivieren?" title="Archivieren">
                                            @svg('heroicon-o-archive-box', 'w-4 h-4')
                                        </x-nx-button>
                                    @endif
                                    <x-nx-button variant="ghost" icon wire:click="delete({{ $profile->id }})" wire:confirm="Wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-nx-button>
                                </div>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                        @if($expandedProfileId === $profile->id)
                            <x-nx-table-row :hover="false" class="bg-[color:var(--nx-bg)]">
                                <x-nx-table-cell colspan="8" class="py-4 px-6">
                                    <h4 class="text-xs font-bold text-[color:var(--nx-text)] uppercase tracking-wider mb-3">Zuweisungen</h4>

                                    @forelse($profile->assignments as $assignment)
                                        <div class="flex items-center justify-between py-2 border-b border-[color:var(--nx-line)] last:border-0">
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
                                                @if($assignment->note)
                                                    <span class="text-xs text-[color:var(--nx-muted)] italic">{{ $assignment->note }}</span>
                                                @endif
                                            </div>
                                            <x-nx-button variant="ghost" icon wire:click="deleteAssignment({{ $assignment->id }})" wire:confirm="Zuweisung entfernen?">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-nx-button>
                                        </div>
                                    @empty
                                        <p class="text-sm text-[color:var(--nx-muted)] py-2">Noch keine Zuweisungen.</p>
                                    @endforelse

                                    {{-- Zuweisungs-Formular --}}
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
                                        <div class="md:col-span-2">
                                            <x-nx-input-select name="assignForm_employee_id" label="Mitarbeiter" wire:model="assignForm.employee_id" size="sm"
                                                nullable nullLabel="— wählen —"
                                                :options="collect($this->employeeOptions)->map(fn($ename, $eid) => ['value' => $eid, 'label' => $ename])->values()->all()" />
                                        </div>
                                        <div>
                                            <x-nx-input-number name="assignForm_percentage" label="%" wire:model="assignForm.percentage" :min="0" :max="100" size="sm" />
                                        </div>
                                        <div>
                                            <x-nx-input-date name="assignForm_valid_from" label="Von" wire:model="assignForm.valid_from" size="sm" />
                                        </div>
                                        <div>
                                            <x-nx-input-date name="assignForm_valid_to" label="Bis" wire:model="assignForm.valid_to" size="sm" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-nx-input-checkbox name="assignForm_is_primary" label="Primär" wire:model="assignForm.is_primary" />
                                        </div>
                                        <div class="md:col-span-5">
                                            <x-nx-input-text name="assignForm_note" label="Notiz" wire:model="assignForm.note" placeholder="Optional" size="sm" />
                                        </div>
                                        <div>
                                            <x-nx-button variant="primary" wire:click="storeAssignment">
                                                @svg('heroicon-o-plus', 'w-4 h-4')
                                                <span>Zuweisen</span>
                                            </x-nx-button>
                                        </div>
                                    </div>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endif
                    @empty
                        <x-nx-table-row :hover="false">
                            <x-nx-table-cell colspan="8">
                                <x-nx-empty icon="heroicon-o-identification">
                                    Noch keine Jobprofile.
                                    <x-slot name="action">
                                        <x-nx-button variant="primary" wire:click="openCreate">
                                            @svg('heroicon-o-plus', 'w-4 h-4') Erstellen
                                        </x-nx-button>
                                    </x-slot>
                                </x-nx-empty>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @endforelse
                </x-nx-table-body>
            </x-nx-table>
        </x-nx-card>

        {{-- Create/Edit Modal --}}
        <x-nx-modal size="md" wire:model="showModal">
            <x-slot name="header">{{ $editingId ? 'Jobprofil bearbeiten' : 'Neues Jobprofil' }}</x-slot>
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
    </x-ui-page-container>
</x-ui-page>
