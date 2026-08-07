<?php

namespace Platform\People\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\People\Models\Employee;
use Platform\People\Models\Skill;

class Dashboard extends Component
{
    public function render()
    {
        $teamId = Auth::user()->currentTeam->id;

        return view('people::livewire.dashboard', [
            'currentDate'       => now()->format('d.m.Y'),
            'employeeCount'     => Employee::forTeam($teamId)->count(),
            'activeCount'       => Employee::forTeam($teamId)->active()->count(),
            'skillCount'        => Skill::forTeam($teamId)->count(),
        ])->layout('platform::layouts.app');
    }
}
