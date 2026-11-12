<?php

namespace Platform\People\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\JobProfile;

class Show extends Component
{
    public JobProfile $jobProfile;

    /** Einheit (Firma/Abteilung) der Stelle — lebt als dimension_link in der Organisation. */
    public string $unitId = '';

    public function mount(JobProfile $jobProfile): void
    {
        abort_unless(
            (int) $jobProfile->team_id === (int) Auth::user()->currentTeam->id,
            403
        );

        $this->jobProfile = $jobProfile;
        $this->unitId = (string) ($jobProfile->unitEntityId() ?? '');
    }

    public function updatedUnitId(): void
    {
        $id = ctype_digit($this->unitId) ? (int) $this->unitId : null;
        if ($id !== null && ! array_key_exists($id, $this->unitOptions())) {
            $this->unitId = (string) ($this->jobProfile->unitEntityId() ?? '');

            return;
        }
        $this->jobProfile->setUnit($id);
        $this->dispatch('toast', message: $id ? 'Einheit gesetzt' : 'Einheit entfernt');
    }

    /**
     * Einheiten des Teams, an die eine Stelle gehören kann: alle Knoten außer Personen und Agenten
     * (guarded — organization optional).
     *
     * @return array<int,string>
     */
    public function unitOptions(): array
    {
        try {
            return \Platform\Organization\Models\OrganizationEntity::query()
                ->forTeam((int) Auth::user()->currentTeam->id)
                ->where('is_active', true)
                ->whereDoesntHave('type', fn ($q) => $q->whereIn('code', ['person', 'agent']))
                ->orderBy('name')->limit(500)
                ->pluck('name', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
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
        return view('people::livewire.job-profile.show', ['unitOptions' => $this->unitOptions()])
            ->layout('platform::layouts.app');
    }
}
