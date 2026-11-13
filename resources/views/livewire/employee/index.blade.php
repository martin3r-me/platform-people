<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-people::actionbar :crumbs="[['label' => 'Mitarbeiter']]"
            :filters-active="($search !== '' ? 1 : 0) + ($kindFilter ? 1 : 0) + ($statusFilter ? 1 : 0)">
            <x-slot:filters>
                <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name suchen…" size="sm" />
                <x-people::filter-group label="Art">
                    <x-people::chip :active="! $kindFilter" wire:click="$set('kindFilter', '')">Alle</x-people::chip>
                    @foreach(\Platform\People\Models\Employee::KIND_LABELS as $value => $label)
                        <x-people::chip :active="$kindFilter === $value" wire:click="$set('kindFilter', '{{ $value }}')">{{ $label }}</x-people::chip>
                    @endforeach
                </x-people::filter-group>
                <x-people::filter-group label="Status">
                    <x-people::chip :active="! $statusFilter" wire:click="$set('statusFilter', '')">Alle</x-people::chip>
                    @foreach(['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'left' => 'Ausgeschieden'] as $value => $label)
                        <x-people::chip :active="$statusFilter === $value" wire:click="$set('statusFilter', '{{ $value }}')">{{ $label }}</x-people::chip>
                    @endforeach
                </x-people::filter-group>
            </x-slot:filters>
            @if($agentsToAdopt > 0)
                <x-slot:create>
                    <x-people::menu-item icon="heroicon-o-cpu-chip" wire:click="adoptAgents" hint="aus der Organisation">
                        {{ $agentsToAdopt }} {{ $agentsToAdopt === 1 ? 'Agent' : 'Agenten' }} übernehmen
                    </x-people::menu-item>
                </x-slot:create>
            @endif
        </x-people::actionbar>
    </x-slot>


    <x-people::body>
        <x-nx-card flush>
            <x-nx-table>
                <x-nx-table-header>
                    <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                    <x-nx-table-header-cell>Personalnr.</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Skills</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="center">Status</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="right">Aktionen</x-nx-table-header-cell>
                </x-nx-table-header>
                <x-nx-table-body>
                    @forelse($this->employees as $employee)
                        <x-nx-table-row class="{{ $employee->status !== 'active' ? 'opacity-60' : '' }}">
                            <x-nx-table-cell>
                                <a href="{{ route('people.employees.show', $employee->id) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:text-[color:var(--nx-accent)] hover:underline">{{ $employee->display_name }}</a>
                                @if($employee->isAgent())<x-nx-badge variant="info" class="ml-1">Agent</x-nx-badge>@endif
                            </x-nx-table-cell>
                            <x-nx-table-cell>{{ $employee->employee_number ?? '—' }}</x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[color:var(--nx-hover)] text-[color:var(--nx-text)]">{{ $employee->skills_count }}</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="center">
                                @php
                                    $statusConfig = [
                                        'active' => ['variant' => 'success', 'label' => 'Aktiv'],
                                        'inactive' => ['variant' => 'neutral', 'label' => 'Inaktiv'],
                                        'left' => ['variant' => 'danger', 'label' => 'Ausgeschieden'],
                                    ];
                                    $sc = $statusConfig[$employee->status] ?? $statusConfig['inactive'];
                                @endphp
                                <x-nx-badge :variant="$sc['variant']">{{ $sc['label'] }}</x-nx-badge>
                            </x-nx-table-cell>
                            <x-nx-table-cell align="right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="openEdit({{ $employee->id }})">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </x-nx-button>
                                    <x-nx-button variant="ghost" size="sm" icon wire:click="delete({{ $employee->id }})" wire:confirm="Wirklich löschen?">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </x-nx-button>
                                </div>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @empty
                        <x-nx-table-row>
                            <x-nx-table-cell colspan="5">
                                <x-nx-empty icon="heroicon-o-users">
                                    Noch keine Mitarbeiter.
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
        <x-nx-modal size="md" wire:model="showModal">
            <x-slot name="header">{{ $editingId ? 'Mitarbeiter bearbeiten' : 'Neuer Mitarbeiter' }}</x-slot>
            <div class="space-y-4">
                <x-nx-input-text name="form.display_name" label="Name" wire:model="form.display_name" placeholder="Vor- und Nachname" size="sm" />
                <x-nx-input-text name="form.employee_number" label="Personalnummer" wire:model="form.employee_number" placeholder="Optional" size="sm" />
                <x-nx-input-select name="form.status" label="Status" wire:model="form.status"
                    :options="['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'left' => 'Ausgeschieden']"
                    size="sm" />
                <x-nx-input-select name="form.org_entity_id" label="Personen-Knoten (Organization)" wire:model="form.org_entity_id"
                    nullable nullLabel="— neuen Person-Knoten anlegen —"
                    :options="$orgEntityOptions"
                    hint="Leer = people legt einen neuen Person-Knoten an (Personal-Wurzel). Oder bestehenden Knoten wählen."
                    size="sm" />
            </div>
            <x-slot name="footer">
                <x-nx-button variant="ghost" size="sm" wire:click="$set('showModal', false)">
                    Abbrechen
                </x-nx-button>
                <x-nx-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>{{ $editingId ? 'Speichern' : 'Erstellen' }}</span>
                </x-nx-button>
            </x-slot>
        </x-nx-modal>
    </x-people::body>
</x-ui-page>
