<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users'],
            ['label' => 'Mitarbeiter', 'href' => route('people.employees.index')],
            ['label' => $employee->display_name],
        ]">
            <x-ui-button variant="secondary-ghost" size="sm" :href="route('people.employees.index')">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                <span>Zurück</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <div class="max-w-4xl mx-auto p-6 space-y-6">
        {{-- Stammdaten --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $employee->display_name }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Personalnummer</dt><dd class="text-gray-900">{{ $employee->employee_number ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd class="text-gray-900">{{ $employee->status }}</dd></div>
                <div><dt class="text-gray-500">Skills</dt><dd class="text-gray-900">{{ $employee->skills_count }}</dd></div>
                <div><dt class="text-gray-500">Personen-Knoten</dt><dd class="text-gray-900">{{ $employee->org_entity_id ? ('#' . $employee->org_entity_id) : '— nicht verknüpft —' }}</dd></div>
            </dl>
        </div>

        {{-- CRM-Steckbrief (graph-nativ am Personen-Knoten) --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">CRM-Kontakt</h3>
                @if($employee->org_entity_id && $directoryAvailable)
                    <x-ui-button variant="secondary" size="sm" wire:click="openCrmLink">
                        @svg('heroicon-o-link', 'w-4 h-4')
                        <span>Mit CRM verknüpfen</span>
                    </x-ui-button>
                @endif
            </div>

            @if(!$employee->org_entity_id)
                <p class="text-sm text-gray-400">Kein Personen-Knoten verknüpft — im Mitarbeiter setzen, dann ist CRM-Anreicherung möglich.</p>
            @elseif($crmProfile)
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    @isset($crmProfile['name'])<div><dt class="text-gray-500">Name</dt><dd class="text-gray-900">{{ $crmProfile['name'] }}</dd></div>@endisset
                    @isset($crmProfile['status'])<div><dt class="text-gray-500">Status</dt><dd class="text-gray-900">{{ $crmProfile['status'] }}</dd></div>@endisset
                    @isset($crmProfile['birth_date'])<div><dt class="text-gray-500">Geburtsdatum</dt><dd class="text-gray-900">{{ \Illuminate\Support\Carbon::parse($crmProfile['birth_date'])->format('d.m.Y') }}</dd></div>@endisset
                    @isset($crmProfile['phone'])<div><dt class="text-gray-500">Telefon</dt><dd class="text-gray-900">{{ $crmProfile['phone'] }}</dd></div>@endisset
                    @isset($crmProfile['email'])<div><dt class="text-gray-500">E-Mail</dt><dd class="text-gray-900">{{ $crmProfile['email'] }}</dd></div>@endisset
                    @isset($crmProfile['address'])<div class="sm:col-span-2"><dt class="text-gray-500">Anschrift</dt><dd class="text-gray-900">{{ $crmProfile['address'] }}</dd></div>@endisset
                </dl>
            @else
                <p class="text-sm text-gray-400">Kein CRM-Kontakt verknüpft.</p>
            @endif
        </div>
    </div>

    {{-- Mit CRM verknüpfen --}}
    @if($showCrmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" wire:click.self="$set('showCrmModal', false)">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Mit CRM verknüpfen</h3>

                {{-- Neu anlegen --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Neuen Kontakt anlegen</label>
                    <div class="flex gap-2">
                        <input type="text" wire:model="newContactName" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm" placeholder="Vor- und Nachname" />
                        <x-ui-button variant="primary" size="sm" wire:click="createAndLinkCrm">Anlegen &amp; verknüpfen</x-ui-button>
                    </div>
                </div>

                {{-- Bestehenden suchen --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bestehenden Kontakt suchen</label>
                    <div class="flex gap-2 mb-3">
                        <input type="text" wire:model="crmSearch" wire:keydown.enter="searchCrm" class="flex-1 rounded-md border-gray-300 shadow-sm text-sm" placeholder="Name..." />
                        <x-ui-button variant="secondary" size="sm" wire:click="searchCrm">Suchen</x-ui-button>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        @forelse($crmResults as $r)
                            <button type="button" wire:click="linkExistingCrm({{ $r['id'] }})"
                                    class="w-full text-left py-2 px-1 hover:bg-gray-50 flex items-center justify-between">
                                <span class="text-sm text-gray-900">{{ $r['label'] }}</span>
                                @if(!empty($r['subtitle']))<span class="text-xs text-gray-400">{{ $r['subtitle'] }}</span>@endif
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 py-2">Keine Treffer — oben nach Name suchen.</p>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('showCrmModal', false)">Schließen</x-ui-button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
