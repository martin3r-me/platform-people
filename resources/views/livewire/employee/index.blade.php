<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Mitarbeiter'],
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
                        <option value="inactive">Inaktiv</option>
                        <option value="left">Ausgeschieden</option>
                    </select>
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
        <div class="bg-white rounded-lg border border-[var(--ui-border)]">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-[var(--ui-muted)] uppercase border-b border-[var(--ui-border)]">
                        <th class="text-left py-3 px-4">Name</th>
                        <th class="text-left py-3 px-4">Personalnr.</th>
                        <th class="text-center py-3 px-4">Skills</th>
                        <th class="text-center py-3 px-4">Status</th>
                        <th class="text-right py-3 px-4">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->employees as $employee)
                        <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)] transition-colors {{ $employee->status !== 'active' ? 'opacity-60' : '' }}">
                            <td class="py-3 px-4 font-medium text-[var(--ui-secondary)]">
                                <a href="{{ route('people.employees.show', $employee->id) }}" wire:navigate class="hover:text-[var(--ui-primary)] hover:underline">{{ $employee->display_name }}</a>
                            </td>
                            <td class="py-3 px-4 text-[var(--ui-muted)] text-xs">{{ $employee->employee_number ?? '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $employee->skills_count }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @php
                                    $statusConfig = [
                                        'active' => ['variant' => 'success', 'label' => 'Aktiv'],
                                        'inactive' => ['variant' => 'muted', 'label' => 'Inaktiv'],
                                        'left' => ['variant' => 'danger', 'label' => 'Ausgeschieden'],
                                    ];
                                    $sc = $statusConfig[$employee->status] ?? $statusConfig['inactive'];
                                @endphp
                                <x-ui-badge variant="{{ $sc['variant'] }}" size="sm">{{ $sc['label'] }}</x-ui-badge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openEdit({{ $employee->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </button>
                                    <button wire:click="delete({{ $employee->id }})" wire:confirm="Wirklich löschen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-[var(--ui-muted)]">
                                <div class="flex flex-col items-center gap-2">
                                    @svg('heroicon-o-users', 'w-8 h-8 text-[var(--ui-muted)]')
                                    <span>Noch keine Mitarbeiter.</span>
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
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">
                        {{ $editingId ? 'Mitarbeiter bearbeiten' : 'Neuer Mitarbeiter' }}
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" wire:model="form.display_name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Vor- und Nachname" />
                            @error('form.display_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Personalnummer</label>
                            <input type="text" wire:model="form.employee_number" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional" />
                            @error('form.employee_number') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select wire:model="form.status" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="active">Aktiv</option>
                                <option value="inactive">Inaktiv</option>
                                <option value="left">Ausgeschieden</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Personen-Knoten (Organization)</label>
                            <select wire:model="form.org_entity_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">— neuen Person-Knoten anlegen —</option>
                                @foreach($orgEntityOptions as $eid => $ename)
                                    <option value="{{ $eid }}">{{ $ename }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Leer = people legt einen neuen Person-Knoten an (Personal-Wurzel). Oder bestehenden Knoten wählen.</p>
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
