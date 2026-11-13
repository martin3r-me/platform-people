{{--
    People-Actionbar — EINE Leiste für alle People-Seiten, feste Plätze (wie in Planner und Dev):

      [Breadcrumb]  [Übersicht · Mitarbeiter · Stellen · Skills · Arbeitgeber]  ·····  [Filter ▾] [+ Neu ▾] [⋯]

    Die Leiste ist auf jeder Seite identisch: keine Seiten-Kennzahlen hier —
    die gehören in <x-people::statusbar> als erstes Element des Inhalts.

    • Filter:  Inhalt der Seite (Slot "filters"); ohne Filter bleibt der Button sichtbar, aber inaktiv.
    • + Neu:   immer Mitarbeiter, Stelle, Skill, Arbeitgeber; Seiten ergänzen über Slot "create".
    • ⋯:       Seitenoptionen (Slot "more"); ohne Optionen ausgeblendet.

    Neu-Aktionen auf der zuständigen Seite direkt per $wire, sonst als Link mit ?aktion=neu.

    <x-people::actionbar :crumbs="[['label' => $employee->display_name]]" :filters-active="1">
        <x-slot:filters> … </x-slot:filters>
        <x-slot:more> <x-people::menu-item icon="…" wire:click="…">…</x-people::menu-item> </x-slot:more>
    </x-people::actionbar>
--}}
@props([
    'active' => null,
    'crumbs' => [],
    'filtersActive' => 0,
])

@php
    // Bei Livewire-Updates ist request()->route() die Update-Route — dann die ursprüngliche Seite auflösen.
    $route = request()->route();
    if (\Livewire\Livewire::isLivewireRequest()) {
        try {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create(\Livewire\Livewire::originalUrl(), 'GET'));
        } catch (\Throwable $e) {
            $route = null;
        }
    }
    $routeName = $route?->getName();

    $active ??= match ($routeName) {
        'people.dashboard' => 'overview',
        'people.employees.index', 'people.employees.show' => 'employees',
        'people.job-profiles.index', 'people.job-profiles.show' => 'positions',
        'people.skills.index' => 'skills',
        'people.employers.index' => 'employers',
        default => null,
    };

    $tabs = [
        'overview' => ['href' => route('people.dashboard'), 'label' => 'Übersicht', 'icon' => 'heroicon-o-home'],
        'employees' => ['href' => route('people.employees.index'), 'label' => 'Mitarbeiter', 'icon' => 'heroicon-o-users'],
        'positions' => ['href' => route('people.job-profiles.index'), 'label' => 'Stellen', 'icon' => 'heroicon-o-identification'],
        'skills' => ['href' => route('people.skills.index'), 'label' => 'Skills', 'icon' => 'heroicon-o-academic-cap'],
        'employers' => ['href' => route('people.employers.index'), 'label' => 'Arbeitgeber', 'icon' => 'heroicon-o-building-office-2'],
    ];

    // + Neu: Ziel-Seite und ob wir schon dort sind (dann direkt öffnen statt navigieren).
    $creates = [
        ['label' => 'Mitarbeiter', 'icon' => 'heroicon-o-user-plus', 'route' => 'people.employees.index'],
        ['label' => 'Stelle', 'icon' => 'heroicon-o-identification', 'route' => 'people.job-profiles.index'],
        ['label' => 'Skill', 'icon' => 'heroicon-o-academic-cap', 'route' => 'people.skills.index'],
        ['label' => 'Arbeitgeber', 'icon' => 'heroicon-o-building-office-2', 'route' => 'people.employers.index'],
    ];

    $trail = array_merge([['label' => 'People', 'href' => route('people.dashboard'), 'icon' => 'users']], $crumbs);
    $activeTab = $tabs[$active] ?? null;
    $hasFilters = isset($filters) && trim((string) $filters) !== '';
    $hasMore = isset($more) && trim((string) $more) !== '';

    $iconBtn = 'inline-flex items-center justify-center gap-1.5 h-8 sm:h-7 min-w-8 sm:min-w-7 px-2 rounded-md text-xs font-medium transition-colors';
    $popover = 'absolute top-full mt-1.5 z-40 bg-[color:var(--nx-surface)] border border-[color:var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)]';
@endphp

<div x-data="{ open: null, toggle(m) { this.open = this.open === m ? null : m } }"
     @keydown.escape.window="open = null"
     class="relative shrink-0 z-30 h-12 sm:h-11 px-2 sm:px-4 bg-[color:var(--nx-surface)] border-b border-[color:var(--nx-line)]">
    <div class="h-full flex items-center gap-1.5 sm:gap-2">

        {{-- Wo bin ich --}}
        <div class="min-w-0 flex-1 md:flex-initial md:max-w-[34%] lg:max-w-[30%]">
            <x-ui-breadcrumb :items="$trail" />
        </div>

        {{-- Reiter ab md --}}
        <span class="hidden md:block h-4 w-px bg-[color:var(--nx-line)] mx-1.5 shrink-0" aria-hidden="true"></span>
        <nav class="hidden md:flex self-stretch items-stretch min-w-0 overflow-x-auto -mb-px [scrollbar-width:none]" aria-label="Bereiche">
            @foreach($tabs as $key => $tab)
                @php $isActive = $active === $key; @endphp
                <a href="{{ $tab['href'] }}" wire:navigate title="{{ $tab['label'] }}" @if($isActive) aria-current="page" @endif
                   class="group relative shrink-0 inline-flex items-center gap-1.5 px-2 lg:px-3 text-[12px] whitespace-nowrap transition-colors {{ $isActive ? 'text-[color:var(--nx-text)] font-semibold' : 'text-[color:var(--nx-muted)] font-medium hover:text-[color:var(--nx-text)]' }}">
                    @svg($tab['icon'], 'w-4 h-4 shrink-0 ' . ($isActive ? 'text-[color:var(--nx-accent)]' : 'opacity-70 group-hover:opacity-100'))
                    <span class="{{ $isActive ? '' : 'hidden lg:inline' }}">{{ $tab['label'] }}</span>
                    <span class="absolute inset-x-1.5 lg:inset-x-2 bottom-0 h-0.5 rounded-t-full transition-colors {{ $isActive ? 'bg-[color:var(--nx-accent)]' : 'bg-transparent group-hover:bg-[color:var(--nx-line-strong)]' }}" aria-hidden="true"></span>
                </a>
            @endforeach
        </nav>

        {{-- Reiter: unter md als Dropdown --}}
        <div class="relative md:hidden shrink-0">
            <button type="button" @click="toggle('tabs')" :aria-expanded="open === 'tabs'"
                    class="{{ $iconBtn }} font-semibold text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @if($activeTab) @svg($activeTab['icon'], 'w-4 h-4 text-[color:var(--nx-accent)]') <span class="max-w-[7rem] truncate">{{ $activeTab['label'] }}</span>
                @else @svg('heroicon-o-squares-2x2', 'w-4 h-4') @endif
                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
            </button>
            <div x-show="open === 'tabs'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 class="{{ $popover }} left-0 w-56 max-h-[70vh] overflow-y-auto p-1">
                @foreach($tabs as $key => $tab)
                    <a href="{{ $tab['href'] }}" wire:navigate
                       class="flex items-center gap-2.5 px-3 py-2 rounded-md text-[13px] {{ $active === $key ? 'bg-[color:var(--nx-accent)]/10 text-[color:var(--nx-accent)] font-medium' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
                        @svg($tab['icon'], 'w-4 h-4 shrink-0')
                        <span class="flex-1 truncate">{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="flex-1"></div>

        {{-- Filter --}}
        <div class="relative shrink-0">
            <button type="button" @if($hasFilters) @click="toggle('filter')" @else disabled @endif
                    :aria-expanded="open === 'filter'"
                    title="{{ $hasFilters ? 'Filter' : 'Keine Filter auf dieser Seite' }}"
                    class="{{ $iconBtn }} {{ $hasFilters ? ($filtersActive > 0 ? 'text-[color:var(--nx-accent)] bg-[color:var(--nx-accent)]/10 hover:bg-[color:var(--nx-accent)]/15' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]') : 'text-[color:var(--nx-faint)] opacity-50 cursor-not-allowed' }}">
                @svg('heroicon-o-funnel', 'w-4 h-4')
                <span class="hidden sm:inline">Filter</span>
                @if($filtersActive > 0)
                    <span class="min-w-[16px] h-4 px-1 rounded-full text-[10px] font-semibold bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] tabular-nums leading-4 text-center">{{ $filtersActive }}</span>
                @endif
            </button>
            @if($hasFilters)
                <div x-show="open === 'filter'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                     class="{{ $popover }} right-0 w-[min(22rem,calc(100vw-1rem))] max-h-[min(70vh,36rem)] overflow-y-auto p-3 space-y-4">
                    {{ $filters }}
                </div>
            @endif
        </div>

        {{-- + Neu --}}
        <div class="relative shrink-0">
            <button type="button" @click="toggle('new')" :aria-expanded="open === 'new'" title="Neu anlegen"
                    class="{{ $iconBtn }} bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] hover:opacity-90">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span class="hidden sm:inline">Neu</span>
            </button>
            <div x-show="open === 'new'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 @click="if ($event.target.closest('[data-menu-item]')) open = null"
                 class="{{ $popover }} right-0 w-[min(16rem,calc(100vw-1rem))] p-1">
                @isset($create)
                    {{ $create }}
                    <div class="my-1 border-t border-[color:var(--nx-line)]"></div>
                @endisset
                @foreach($creates as $c)
                    @if($routeName === $c['route'])
                        <x-people::menu-item :icon="$c['icon']" x-on:click="$wire.openCreate()">{{ $c['label'] }}</x-people::menu-item>
                    @else
                        <x-people::menu-item :icon="$c['icon']" :href="route($c['route']) . '?aktion=neu'">{{ $c['label'] }}</x-people::menu-item>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- ⋯ --}}
        @if($hasMore)
            <div class="relative shrink-0">
                <button type="button" @click="toggle('more')" :aria-expanded="open === 'more'" title="Mehr"
                        class="{{ $iconBtn }} text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                    @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                </button>
                <div x-show="open === 'more'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                     @click="if ($event.target.closest('[data-menu-item]')) open = null"
                     class="{{ $popover }} right-0 w-[min(16rem,calc(100vw-1rem))] p-1">
                    {{ $more }}
                </div>
            </div>
        @endif
    </div>
</div>
