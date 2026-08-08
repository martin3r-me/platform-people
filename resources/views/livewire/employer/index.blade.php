<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Arbeitgeber'],
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
                <p class="text-xs text-[var(--ui-muted)]">Ein Arbeitgeber ist ein Org-Carrier (z. B. eine Gesellschaft) mit HR-Defaults für Verträge.</p>
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
                        <th class="text-left py-3 px-4">Org-Carrier</th>
                        <th class="text-center py-3 px-4">Urlaub</th>
                        <th class="text-center py-3 px-4">Std/Wo</th>
                        <th class="text-center py-3 px-4">Verträge</th>
                        <th class="text-center py-3 px-4">Status</th>
                        <th class="text-right py-3 px-4">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->employers as $employer)
                        <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)] transition-colors {{ !$employer->is_active ? 'opacity-50' : '' }}">
                            <td class="py-3 px-4 font-medium text-[var(--ui-secondary)]">{{ $employer->name }}</td>
                            <td class="py-3 px-4 text-[var(--ui-muted)] text-xs">{{ $employer->org_entity_id ? ('#' . $employer->org_entity_id) : '— nicht verknüpft —' }}</td>
                            <td class="py-3 px-4 text-center">{{ $employer->default_vacation_days ?? '—' }}</td>
                            <td class="py-3 px-4 text-center">{{ $employer->default_weekly_hours ? rtrim(rtrim((string)$employer->default_weekly_hours,'0'),'.') : '—' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $employer->employments_count }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($employer->is_active)
                                    <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                                @else
                                    <x-ui-badge variant="muted" size="sm">Inaktiv</x-ui-badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="openEdit({{ $employer->id }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </button>
                                    <button wire:click="delete({{ $employer->id }})" wire:confirm="Wirklich löschen?" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition-colors">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-[var(--ui-muted)]">
                                <div class="flex flex-col items-center gap-2">
                                    @svg('heroicon-o-building-office-2', 'w-8 h-8 text-[var(--ui-muted)]')
                                    <span>Noch keine Arbeitgeber.</span>
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
            <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showModal', false)">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">{{ $editingId ? 'Arbeitgeber bearbeiten' : 'Neuer Arbeitgeber' }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Org-Carrier</label>
                            <select wire:model.live="form.org_entity_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">— kein / manuell —</option>
                                @foreach($this->carrierOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @if(empty($this->carrierOptions))
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Keine Carrier gefunden (Organization-Modul nicht verfügbar oder keine Carrier gepflegt).</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" wire:model="form.name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Anzeigename" />
                            @error('form.name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Standard-Urlaubstage</label>
                                <input type="number" min="0" max="365" wire:model="form.default_vacation_days" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. 30" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Standard-Wochenstunden</label>
                                <input type="number" step="0.5" min="0" max="80" wire:model="form.default_weekly_hours" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. 40" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Arbeitszeitmodell</label>
                            <input type="text" wire:model="form.working_time_model" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="z.B. Gleitzeit, Vertrauensarbeitszeit" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.is_active" id="empactive" class="rounded border-gray-300" />
                            <label for="empactive" class="text-sm text-gray-700">Aktiv</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notiz</label>
                            <textarea wire:model="form.note" rows="2" class="w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Optional"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-6">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showModal', false)">Abbrechen</x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="save">
                            @svg('heroicon-o-check', 'w-4 h-4') <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
