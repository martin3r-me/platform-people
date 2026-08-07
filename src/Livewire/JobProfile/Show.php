<?php

namespace Platform\People\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\JobProfile;

class Show extends Component
{
    public JobProfile $jobProfile;

    public function mount(JobProfile $jobProfile): void
    {
        abort_unless(
            (int) $jobProfile->team_id === (int) Auth::user()->currentTeam->id,
            403
        );

        $this->jobProfile = $jobProfile;
    }

    #[Computed]
    public function skillRecords()
    {
        return $this->jobProfile->skillRecords()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function roles()
    {
        try {
            return $this->jobProfile->roles()->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    #[Computed]
    public function assignments()
    {
        return $this->jobProfile->assignments()
            ->with('employee')
            ->orderByDesc('is_primary')
            ->get();
    }

    /**
     * Effektive Rollen-Anteile pro Zuweisung (null-tolerant gegenüber Organization).
     *
     * @return array<int, \Illuminate\Support\Collection>
     */
    #[Computed]
    public function effectiveRolesByAssignment(): array
    {
        $result = [];
        foreach ($this->assignments as $assignment) {
            try {
                $result[$assignment->id] = $assignment->effectiveRoleShares();
            } catch (\Throwable $e) {
                $result[$assignment->id] = collect();
            }
        }

        return $result;
    }

    public function render()
    {
        return view('people::livewire.job-profile.show')
            ->layout('platform::layouts.app');
    }
}
