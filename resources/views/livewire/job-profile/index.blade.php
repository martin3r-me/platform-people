<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Jobprofile'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neu</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Alle Status</option>
                        <option value="active">Aktiv</option>
                        <option value="draft">Entwurf</option>
                        <option value="archived">Archiviert</option>
                    </select>
                </div>
                @if(count($this->jobFamilies) > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Job-Family</h3>
                        <select wire:model.live="jobFamilyFilter" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Alle Families</option>
                            @foreach($this->jobFamilies as $family)
                                <option value="{{ $family }}">{{ $family }}</option>
                            @endforeach
                        </select>
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
        <div class="bg-white rounded-lg border border-[var(--ui-border)]">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-[var(--ui-muted)] uppercase border-b border-[var(--ui-border)]">
                        <th class="text-left py-3 px-4">Name</th>
                        <th class="text-left py-3 px-4">Level</th>
                        <th class="text-left py-3 px-4">Job-Family</th>
                        <th class="text-center py-3 px-4">Rollen</th>
                        <th class="text-center py-3 px-4">Skills</th>
                        <th class="text-center py-3 px-4">Zuweisungen</th>
                        <th class="text-center py-3 px-4">Status</th>
                        <th class="text-right py-3 px-4">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->jobProfiles as $profile)
                        @php
                            $statusConfig = [
                                'active' => ['variant' => 'success', 'label' => 'Aktiv'],
                                'draft' => ['variant' => 'info', 'label' => 'Entwurf'],
                                'archived' => ['variant' => 'muted', 'label' => 'Archiviert'],
                            ];
                            $sc = $statusConfig[$profile->status] ?? $statusConfig['draft'];
                        @endphp
                        <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)] transition-colors {{ $profile->status === 'archived' ? 'opacity-60' : '' }}">
                            <td class="py-3 px-4 font-medium text-[var(--ui-secondary)]">
                                <a href="{{ route('people.job-profiles.show', $profile->id) }}" wire:navigate class="hover:text-[var(--ui-primary)] hover:underline">{{ $profile->name }}</a>
                            </td>
                            <td class="py-3 px-4 text-[var(--ui-muted)] text-xs">{{ $profile->level ?? '—' }}</td>
                            <td class="py-3 px-4 text-[var(--ui-muted)] text-xs">{{ $profile->job_family ?? '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $profile->roles_count }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $profile->skill_records_count }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button wire:click="toggleAssignments({{ $profile->id }})" class="inline-flex items-center gap-1 px-2 py-1 rounded transition-colors hover:bg-[var(--ui-primary-5)] cursor-pointer">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $profile->assignments_count }}</span>
                                    @if($expandedProfileId === $profile->id)
                                        @svg('heroicon-o-chevron-up', 'w-3 h-3 text-[var(--ui-muted)]')
                                    @else
                                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[var(--ui-muted)]')
                                    @endif
                                </button>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <x-ui-badge variant="{{ $sc['variant'] }}" size="sm">{{ $sc['label'] }}</x-ui-badge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openEdit({{ $profile->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </button>
                                    @if($profile->status === 'archived')
                                        <button wire:click="unarchive({{ $profile->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" title="Reaktivieren">
                                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                        </button>
                                    @else
                                        <button wire:click="archive({{ $profile->id }})" wire:confirm="Profil wirklich archivieren?" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" title="Archivieren">
                                            @svg('heroicon-o-archive-box', 'w-4 h-4')
                                        </button>
                                    @endif
                                    <button wire:click="delete({{ $profile->id }})" wire:confirm="Wirklich löschen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @if($expandedProfileId === $profile->id)
                            <tr class="bg-[var(--ui-muted-5)]/50">
                                <td colspan="8" class="py-4 px-6">
                                    <h4 class="text-xs font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Zuweisungen</h4>

                                    @forelse($profile->assignments as $assignment)
                                        <div class="flex items-center justify-between py-2 border-b border-[var(--ui-border)]/40 last:border-0">
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
                                                @if($assignment->note)
                                                    <span class="text-xs text-[var(--ui-muted)] italic">{{ $assignment->note }}</span>
                                                @endif
                                            </div>
                                            <button wire:click="deleteAssignment({{ $assignment->id }})" wire:confirm="Zuweisung entfernen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </button>
                                        </div>
                                    @empty
                                        <p class="text-sm text-[var(--ui-muted)] py-2">Noch keine Zuweisungen.</p>
                                    @endforelse

                                    {{-- Zuweisungs-Formular --}}
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Mitarbeiter</label>
                                            <select wire:model="assignForm.employee_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                <option value="">— wählen —</option>
                                                @foreach($this->employeeOptions as $eid => $ename)
                                                    <option value="{{ $eid }}">{{ $ename }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">%</label>
                                            <input type="number" min="0" max="100" wire:model="assignForm.percentage" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Von</label>
                                            <input type="date" wire:model="assignForm.valid_from" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Bis</label>
                                            <input type="date" wire:model="assignForm.valid_to" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <label class="flex items-center gap-1 text-xs text-gray-600">
                                                <input type="checkbox" wire:model="assignForm.is_primary" class="rounded border-gray-300" />
                                                Primär
                                            </label>
                                        </div>
                                        <div class="md:col-span-5">
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Notiz</label>
                                            <input type="text" wire:model="assignForm.note" placeholder="Optional" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                        </div>
                                        <div>
                                            <x-ui-button variant="primary" size="sm" wire:click="storeAssignment">
                                                @svg('heroicon-o-plus', 'w-4 h-4')
                                                <span>Zuweisen</span>
                                            </x-ui-button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-[var(--ui-muted)]">
                                <div class="flex flex-col items-center gap-2">
                                    @svg('heroicon-o-identification', 'w-8 h-8 text-[var(--ui-muted)]')
                                    <span>Noch keine Jobprofile.</span>
                                    <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                                        @svg('heroicon-o-plus', 'w-4 h-4') Erstellen
                                    </x-ui-button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Create/Edit Modal --}}
        @if($showModal)
            <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showModal', false)">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">
                        {{ $editingId ? 'Jobprofil bearbeiten' : 'Neues Jobprofil' }}
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" wire:model="form.name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. Senior Backend Engineer" />
                            @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
                                <input type="text" wire:model="form.level" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. Senior" />
                                @error('form.level') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Job-Family</label>
                                <input type="text" wire:model="form.job_family" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. Engineering" />
                                @error('form.job_family') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select wire:model="form.status" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="active">Aktiv</option>
                                <option value="draft">Entwurf</option>
                                <option value="archived">Archiviert</option>
                            </select>
                            @error('form.status') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                            <textarea wire:model="form.description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Zweck</label>
                            <textarea wire:model="form.purpose" rows="2" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gültig ab</label>
                                <input type="date" wire:model="form.effective_from" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('form.effective_from') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gültig bis</label>
                                <input type="date" wire:model="form.effective_to" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                @error('form.effective_to') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showModal', false)">
                            Abbrechen
                        </x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="save">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
