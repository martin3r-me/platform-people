<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Skills'],
        ]">
            <x-nx-button variant="secondary" size="sm" wire:click="$toggle('showMatrix')">
                @if($showMatrix)
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    <span>Katalog</span>
                @else
                    @svg('heroicon-o-table-cells', 'w-4 h-4')
                    <span>Matrix</span>
                @endif
            </x-nx-button>
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
                    <x-nx-input-text name="search" wire:model.live.debounce.300ms="search" placeholder="Skill-Name..." size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kategorie</h3>
                    <x-nx-input-select name="categoryFilter" wire:model.live="categoryFilter"
                        nullable nullLabel="Alle Kategorien"
                        :options="['technical' => 'Technical', 'methodical' => 'Methodical', 'domain' => 'Domain', 'social' => 'Social']"
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
        @if(!$showMatrix)
            {{-- ═══ Katalog-Ansicht ═══ --}}
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Kategorie</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Beschreibung</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="center">Mitarbeiter</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="center">Status</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Aktionen</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @forelse($this->skills as $skill)
                            <x-nx-table-row class="{{ !$skill->is_active ? 'opacity-50' : '' }}">
                                <x-nx-table-cell><span class="font-medium text-[color:var(--nx-text)]">{{ $skill->name }}</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge variant="neutral">{{ ucfirst($skill->category) }}</x-nx-badge>
                                </x-nx-table-cell>
                                <x-nx-table-cell><span class="text-[var(--ui-muted)] text-xs max-w-xs truncate block">{{ $skill->description ?? '—' }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">{{ $skill->employee_skills_count }}</span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="center">
                                    <button wire:click="toggleActive({{ $skill->id }})" class="cursor-pointer">
                                        @if($skill->is_active)
                                            <x-nx-badge variant="success">Aktiv</x-nx-badge>
                                        @else
                                            <x-nx-badge variant="neutral">Inaktiv</x-nx-badge>
                                        @endif
                                    </button>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-nx-button variant="ghost" size="sm" icon wire:click="openEdit({{ $skill->id }})">
                                            @svg('heroicon-o-pencil', 'w-4 h-4')
                                        </x-nx-button>
                                        <x-nx-button variant="ghost" size="sm" icon wire:click="delete({{ $skill->id }})" wire:confirm="Wirklich löschen?">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </x-nx-button>
                                    </div>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @empty
                            <x-nx-table-row>
                                <x-nx-table-cell colspan="6">
                                    <x-nx-empty icon="heroicon-o-academic-cap">
                                        Keine Skills vorhanden.
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
        @else
            {{-- ═══ Matrix-Ansicht ═══ --}}
            @php
                $employees = $this->employees;
                $matrixData = $this->matrix;
            @endphp
            @if($employees->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-user-group">Keine aktiven Mitarbeiter vorhanden.</x-nx-empty>
                </x-nx-card>
            @elseif(empty($matrixData))
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-academic-cap">Keine Skills vorhanden.</x-nx-empty>
                </x-nx-card>
            @else
                {{-- Sticky-Spalten-Matrix mit dynamischen Mitarbeiter-Spalten: bewusst rohe Tabelle, nx-table hat dafür keinen Ersatz --}}
                <x-nx-card flush>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)]">
                                    <th class="text-left py-3 px-4 text-xs text-[var(--ui-muted)] uppercase sticky left-0 bg-white z-10 min-w-[200px]">Skill</th>
                                    @foreach($employees as $employee)
                                        <th class="text-center py-3 px-3 text-xs text-[var(--ui-muted)] min-w-[100px]">
                                            <div class="truncate max-w-[100px]" title="{{ $employee->display_name }}">{{ $employee->display_name }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matrixData as $row)
                                    <tr class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-muted-5)]">
                                        <td class="py-2 px-4 font-medium text-[var(--ui-secondary)] sticky left-0 bg-white z-10">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $row['skill']->name }}</span>
                                                <x-nx-badge variant="neutral">{{ ucfirst($row['skill']->category) }}</x-nx-badge>
                                            </div>
                                        </td>
                                        @foreach($row['cells'] as $cell)
                                            <td class="py-2 px-3 text-center">
                                                <button
                                                    wire:click="openAssignModal({{ $row['skill']->id }}, {{ $cell['employee_id'] }})"
                                                    class="w-full flex items-center justify-center min-h-[28px] rounded transition-colors hover:bg-[var(--ui-primary-5)] cursor-pointer"
                                                >
                                                    @if($cell['level'])
                                                        @php
                                                            $levelConfig = [
                                                                'basic' => ['variant' => 'neutral', 'label' => 'B'],
                                                                'advanced' => ['variant' => 'info', 'label' => 'A'],
                                                                'expert' => ['variant' => 'success', 'label' => 'E'],
                                                            ];
                                                            $lc = $levelConfig[$cell['level']] ?? $levelConfig['basic'];
                                                        @endphp
                                                        <x-nx-badge :variant="$lc['variant']">{{ $lc['label'] }}</x-nx-badge>
                                                    @else
                                                        <span class="text-[var(--ui-border)]">—</span>
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-nx-card>

                {{-- Legende --}}
                <div class="mt-3 flex items-center gap-4 text-xs text-[var(--ui-muted)]">
                    <span class="flex items-center gap-1"><x-nx-badge variant="neutral">B</x-nx-badge> Basic</span>
                    <span class="flex items-center gap-1"><x-nx-badge variant="info">A</x-nx-badge> Advanced</span>
                    <span class="flex items-center gap-1"><x-nx-badge variant="success">E</x-nx-badge> Expert</span>
                </div>
            @endif
        @endif

        {{-- Create/Edit Modal --}}
        <x-nx-modal size="md" wire:model="showModal">
            <x-slot name="header">{{ $editingId ? 'Skill bearbeiten' : 'Neuer Skill' }}</x-slot>
            <div class="space-y-4">
                <x-nx-input-text name="form.name" label="Name" wire:model="form.name" placeholder="Skill-Name" size="sm" />
                <x-nx-input-select name="form.category" label="Kategorie" wire:model="form.category"
                    :options="['technical' => 'Technical', 'methodical' => 'Methodical', 'domain' => 'Domain', 'social' => 'Social']"
                    size="sm" />
                <x-nx-input-textarea name="form.description" label="Beschreibung" wire:model="form.description" :rows="3" placeholder="Optional" size="sm" />
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

        {{-- Assignment Modal --}}
        <x-nx-modal size="sm" wire:model="showAssignModal">
            <x-slot name="header">Skill-Level zuordnen</x-slot>
            <div class="space-y-4">
                <x-nx-input-select name="assignLevel" label="Level" wire:model="assignLevel"
                    :options="['basic' => 'Basic', 'advanced' => 'Advanced', 'expert' => 'Expert']"
                    size="sm" />
            </div>
            <x-slot name="footer">
                <div class="flex w-full justify-between">
                    <x-nx-button variant="ghost" size="sm" wire:click="removeAssignment">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        <span>Entfernen</span>
                    </x-nx-button>
                    <div class="flex gap-2">
                        <x-nx-button variant="ghost" size="sm" wire:click="$set('showAssignModal', false)">
                            Abbrechen
                        </x-nx-button>
                        <x-nx-button variant="primary" size="sm" wire:click="saveAssignment">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span>Speichern</span>
                        </x-nx-button>
                    </div>
                </div>
            </x-slot>
        </x-nx-modal>
    </x-ui-page-container>
</x-ui-page>
