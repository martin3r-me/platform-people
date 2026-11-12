<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Mitarbeiter'],
        ]">
            @if($agentsToAdopt > 0)
                <x-nx-button variant="ghost" size="sm" wire:click="adoptAgents" title="Agenten der Organisation als Mitarbeiter führen">
                    @svg('heroicon-o-cpu-chip', 'w-4 h-4')
                    <span>{{ $agentsToAdopt }} {{ $agentsToAdopt === 1 ? 'Agent' : 'Agenten' }} übernehmen</span>
                </x-nx-button>
            @endif
            <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neu</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Name..." size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Art</h3>
                    <x-nx-input-select name="kindFilter" wire:model.live="kindFilter"
                        nullable nullLabel="Alle"
                        :options="\Platform\People\Models\Employee::KIND_LABELS"
                        size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <x-nx-input-select name="statusFilter" wire:model.live="statusFilter"
                        nullable nullLabel="Alle Status"
                        :options="['active' => 'Aktiv', 'inactive' => 'Inaktiv', 'left' => 'Ausgeschieden']"
                        size="sm" />
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
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $employee->skills_count }}</span>
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
    </x-ui-page-container>
</x-ui-page>
