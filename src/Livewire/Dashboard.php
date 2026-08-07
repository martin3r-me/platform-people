<?php

namespace Platform\People\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('people::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
        ])->layout('platform::layouts.app');
    }
}
