<?php

namespace Platform\People\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\People\Services\DevelopmentCard;

/**
 * People-Cockpit: alle aktiven Mitarbeiter mit Reifestufe und Handlungsbedarf, dringendster zuerst —
 * „der ist gut entwickelt, hier ist etwas zu tun“ auf einen Blick.
 */
class Dashboard extends Component
{
    /** all | needs | agent | human */
    #[Url(as: 'zeige', except: 'all')]
    public string $show = 'all';

    public function setShow(string $show): void
    {
        $this->show = in_array($show, ['all', 'needs', 'agent', 'human'], true) ? $show : 'all';
    }

    public function render()
    {
        $cards = (new DevelopmentCard())->forTeam((int) Auth::user()->currentTeam->id);

        $counts = [
            'all' => count($cards),
            'needs' => count(array_filter($cards, fn ($c) => $this->serious($c))),
            'agent' => count(array_filter($cards, fn ($c) => $c['kind'] === 'agent')),
            'human' => count(array_filter($cards, fn ($c) => $c['kind'] === 'human')),
        ];
        $visible = array_values(array_filter($cards, fn ($c) => match ($this->show) {
            'needs' => $this->serious($c),
            'agent', 'human' => $c['kind'] === $this->show,
            default => true,
        }));

        return view('people::livewire.dashboard', [
            'cards' => $visible,
            'counts' => $counts,
        ])->layout('platform::layouts.app');
    }

    /** Handlungsbedarf, der mehr als ein Hinweis ist. */
    private function serious(array $card): bool
    {
        return collect($card['needs'])->contains(fn ($n) => $n['severity'] !== 'info');
    }
}
