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
            <div class="hidden md:grid grid-cols-[minmax(0,1.3fr)_minmax(0,1.3fr)_minmax(0,1fr)_7rem_6.5rem_4rem] gap-4 px-4 py-2 border-b border-[color:var(--nx-line)] text-[11px] uppercase tracking-wide text-[color:var(--nx-faint)]">
                <span>Name</span><span>Stelle</span><span>Bereich</span><span>Dabei seit</span><span>Status</span><span></span>
            </div>
            @forelse($this->employees as $employee)
                @php
                    $pos = $positions[$employee->id] ?? null;
                    $since = $employee->employments->first()?->started_on;
                    $sc = ['active' => ['success', 'Aktiv'], 'inactive' => ['neutral', 'Inaktiv'], 'left' => ['danger', 'Ausgeschieden']][$employee->status] ?? ['neutral', $employee->status];
                @endphp
                <div wire:key="emp-{{ $employee->id }}"
                     class="group relative grid grid-cols-[minmax(0,1fr)_auto] md:grid-cols-[minmax(0,1.3fr)_minmax(0,1.3fr)_minmax(0,1fr)_7rem_6.5rem_4rem] gap-x-4 gap-y-1 items-center px-4 py-2.5 border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)] {{ $employee->status !== 'active' ? 'opacity-60' : '' }}">
                    <a href="{{ route('people.employees.show', $employee->id) }}" wire:navigate
                       class="min-w-0 flex items-center gap-2 font-medium text-[color:var(--nx-text)] after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline focus-visible:after:outline-2 focus-visible:after:outline-[color:var(--nx-accent)]">
                        <span class="truncate">{{ $employee->display_name }}</span>
                        @if($employee->isAgent())<x-nx-badge variant="info">Agent</x-nx-badge>@endif
                    </a>
                    <span class="md:hidden justify-self-end"><x-nx-badge :variant="$sc[0]" dot>{{ $sc[1] }}</x-nx-badge></span>
                    <span class="col-span-2 md:col-span-1 min-w-0 truncate text-sm {{ $pos ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">{{ $pos['position'] ?? 'keine Stelle' }}</span>
                    <span class="hidden md:block min-w-0 truncate text-sm text-[color:var(--nx-muted)]">{{ $pos['unit'] ?? '—' }}</span>
                    <span class="hidden md:block text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $since?->format('d.m.Y') ?? '—' }}</span>
                    <span class="hidden md:block"><x-nx-badge :variant="$sc[0]" dot>{{ $sc[1] }}</x-nx-badge></span>
                    <span class="hidden md:flex justify-end relative z-10">
                        <x-nx-button variant="ghost" size="sm" icon wire:click="openEdit({{ $employee->id }})" title="Stammdaten bearbeiten" class="opacity-0 group-hover:opacity-100 focus:opacity-100">
                            @svg('heroicon-o-pencil', 'w-4 h-4')
                        </x-nx-button>
                    </span>
                </div>
            @empty
                <x-nx-empty icon="heroicon-o-users">
                    Keine Mitarbeiter in dieser Ansicht.
                    <x-slot name="action">
                        <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                            @svg('heroicon-o-plus', 'w-4 h-4') Mitarbeiter anlegen
                        </x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @endforelse
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
