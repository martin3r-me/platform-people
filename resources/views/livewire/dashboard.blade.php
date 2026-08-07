{{--
    People Dashboard — Grundgerüst (nx-Design-System)

    People = der Mensch als Angestellter: Employee, Employment, Skill + EmployeeSkill.
--}}

<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="People" />
    </x-slot>

    {{-- Actionbar = Seitenkopf mit Breadcrumb --}}
    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'People', 'icon' => 'users'],
        ]" />
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-users">
                People — der Mensch als Angestellter. Employee, Employment und Skills
                leben hier. Diesen Content-Bereich durch deine Inhalte ersetzen.
            </x-nx-empty>
        </x-nx-card>
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Übersicht</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Noch keine Einträge.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Letzte Aktivitäten</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Keine Aktivitäten verfügbar.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
