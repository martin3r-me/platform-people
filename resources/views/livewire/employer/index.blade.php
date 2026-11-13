<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :crumbs="[['label' => 'Arbeitgeber']]" :filters-active="$search !== '' ? 1 : 0">
            <x-slot:filters>
                <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name suchen…" size="sm" />
                <p class="text-xs text-[color:var(--nx-muted)]">Ein Arbeitgeber ist ein Org-Carrier (z. B. eine Gesellschaft) mit HR-Defaults für Verträge.</p>
            </x-slot:filters>
        </x-people::actionbar>
    </x-slot>


    <x-people::body>
        <x-nx-card flush>
            <x-nx-table>
                <x-nx-table-header>
                    <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                    <x-nx-table-header-cell>Org-Carrier</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Urlaub</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Std/Wo</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Verträge</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Status</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="right">Aktionen</x-nx-table-header-cell>
                </x-nx-table-header>
                <x-nx-table-body>
                    @forelse($this->employers as $employer)
                        <x-nx-table-row class="{{ !$employer->is_active ? 'opacity-50' : '' }}">
                            <x-nx-table-cell><span class="font-medium text-[color:var(--nx-text)]">{{ $employer->name }}</span></x-nx-table-cell>
                            <x-nx-table-cell>{{ $employer->org_entity_id ? ('#' . $employer->org_entity_id) : '— nicht verknüpft —' }}</x-nx-table-cell>
                            <x-nx-table-cell align="center">{{ $employer->default_vacation_days ?? '—' }}</x-nx-table-cell>
                            <x-nx-table-cell align="center">{{ $employer->default_weekly_hours ? rtrim(rtrim((string)$employer->default_weekly_hours,'0'),'.') : '—' }}</x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[color:var(--nx-hover)] text-[color:var(--nx-text)]">{{ $employer->employments_count }}</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                @if($employer->is_active)
                                    <x-nx-badge variant="success">Aktiv</x-nx-badge>
                                @else
                                    <x-nx-badge variant="neutral">Inaktiv</x-nx-badge>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell align="right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="openEdit({{ $employer->id }})">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </x-nx-button>
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="delete({{ $employer->id }})" wire:confirm="Wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-nx-button>
                                </div>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @empty
                        <x-nx-table-row>
                            <x-nx-table-cell colspan="7">
                                <x-nx-empty icon="heroicon-o-building-office-2">
                                    Noch keine Arbeitgeber.
                                    <x-slot name="action">
                                        <x-nx-button variant="primary" size="sm" wire:click="openCreate">
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
        <x-nx-modal size="lg" wire:model="showModal">
            <x-slot name="header">{{ $editingId ? 'Arbeitgeber bearbeiten' : 'Neuer Arbeitgeber' }}</x-slot>
            <div class="space-y-4">
                <div>
                    <x-nx-input-select name="form.org_entity_id" label="Org-Carrier" wire:model.live="form.org_entity_id"
                        nullable nullLabel="— kein / manuell —"
                        :options="$this->carrierOptions"
                        size="sm" />
                    @if(empty($this->carrierOptions))
                        <p class="text-xs text-[color:var(--nx-muted)] mt-1">Keine Carrier gefunden (Organization-Modul nicht verfügbar oder keine Carrier gepflegt).</p>
                    @endif
                </div>
                <x-nx-input-text name="form.name" label="Name" wire:model="form.name" placeholder="Anzeigename" size="sm" />
                <div class="grid grid-cols-2 gap-4">
                    <x-nx-input-number name="form.default_vacation_days" label="Standard-Urlaubstage" wire:model="form.default_vacation_days" :min="0" :max="365" placeholder="z.B. 30" size="sm" />
                    <x-nx-input-number name="form.default_weekly_hours" label="Standard-Wochenstunden" wire:model="form.default_weekly_hours" :step="0.5" :min="0" :max="80" placeholder="z.B. 40" size="sm" />
                </div>
                <x-nx-input-text name="form.working_time_model" label="Arbeitszeitmodell" wire:model="form.working_time_model" placeholder="z.B. Gleitzeit, Vertrauensarbeitszeit" size="sm" />
                <x-nx-input-checkbox name="empactive" label="Aktiv" wire:model="form.is_active" />
                <x-nx-input-textarea name="form.note" label="Notiz" wire:model="form.note" :rows="2" placeholder="Optional" size="sm" />
            </div>
            <x-slot name="footer">
                <x-nx-button variant="ghost" size="sm" wire:click="$set('showModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4') <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                </x-nx-button>
            </x-slot>
        </x-nx-modal>
    </x-people::body>
</x-ui-page>
