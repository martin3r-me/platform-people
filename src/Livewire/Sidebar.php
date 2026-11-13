<?php

namespace Platform\People\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\People\Models\Employee;

/**
 * Modul-Navigation: die Bereiche stehen in der Actionbar — hier die Objekte selbst (wie Dev seine Repositories):
 * aktive Agenten und Menschen zum direkten Hinspringen.
 */
class Sidebar extends Component
{
    public function render()
    {
        $teamId = (int) (Auth::user()?->currentTeam?->id ?? 0);
        $employees = $teamId
            ? Employee::forTeam($teamId)->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name', 'kind'])
            : collect();

        return view('people::livewire.sidebar', [
            'agents' => $employees->where('kind', 'agent')->values(),
            'humans' => $employees->where('kind', '!=', 'agent')->values(),
        ]);
    }
}
