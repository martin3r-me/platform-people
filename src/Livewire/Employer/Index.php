<?php

namespace Platform\People\Livewire\Employer;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\Employer;

class Index extends Component
{
    public string $search = '';

    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [
        'org_entity_id'         => '',
        'name'                  => '',
        'default_vacation_days' => '',
        'default_weekly_hours'  => '',
        'working_time_model'    => '',
        'is_active'             => true,
        'note'                  => '',
    ];

    #[Computed]
    public function employers()
    {
        $teamId = Auth::user()->currentTeam->id;

        return Employer::forTeam($teamId)
            ->withCount('employments')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Wählbare Org-Carrier (vsm_class='carrier') als Arbeitgeber-Kandidaten.
     * @return array<int,string>
     */
    #[Computed]
    public function carrierOptions(): array
    {
        try {
            $teamId = Auth::user()->currentTeam->id;
            return \Platform\Organization\Models\OrganizationEntity::query()
                ->where('team_id', $teamId)
                ->whereHas('type', fn ($q) => $q->where('vsm_class', 'carrier'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function updatedFormOrgEntityId($value): void
    {
        // Name aus Carrier vorbelegen, wenn leer.
        if (trim((string) $this->form['name']) === '' && $value !== '') {
            $this->form['name'] = $this->carrierOptions[(int) $value] ?? '';
        }
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = [
            'org_entity_id' => '', 'name' => '', 'default_vacation_days' => '',
            'default_weekly_hours' => '', 'working_time_model' => '', 'is_active' => true, 'note' => '',
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $e = Employer::forTeam($teamId)->findOrFail($id);
        $this->editingId = $id;
        $this->form = [
            'org_entity_id'         => $e->org_entity_id ? (string) $e->org_entity_id : '',
            'name'                  => $e->name,
            'default_vacation_days' => $e->default_vacation_days ?? '',
            'default_weekly_hours'  => $e->default_weekly_hours ?? '',
            'working_time_model'    => $e->working_time_model ?? '',
            'is_active'             => (bool) $e->is_active,
            'note'                  => $e->note ?? '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name'                  => 'required|string|max:255',
            'form.default_vacation_days' => 'nullable|integer|min:0|max:365',
            'form.default_weekly_hours'  => 'nullable|numeric|min:0|max:80',
        ]);

        $teamId = Auth::user()->currentTeam->id;

        $data = [
            'team_id'               => $teamId,
            'org_entity_id'         => ($this->form['org_entity_id'] !== '' && ctype_digit((string) $this->form['org_entity_id'])) ? (int) $this->form['org_entity_id'] : null,
            'name'                  => trim($this->form['name']),
            'is_active'             => (bool) $this->form['is_active'],
            'default_vacation_days' => $this->form['default_vacation_days'] !== '' ? (int) $this->form['default_vacation_days'] : null,
            'default_weekly_hours'  => $this->form['default_weekly_hours'] !== '' ? $this->form['default_weekly_hours'] : null,
            'working_time_model'    => trim((string) $this->form['working_time_model']) ?: null,
            'note'                  => trim((string) $this->form['note']) ?: null,
        ];

        if ($this->editingId) {
            Employer::forTeam($teamId)->where('id', $this->editingId)->update($data);
            $msg = 'Gespeichert';
        } else {
            Employer::create($data);
            $msg = 'Erstellt';
        }

        $this->showModal = false;
        $this->editingId = null;
        unset($this->employers);
        $this->dispatch('toast', message: $msg);
    }

    public function delete(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        Employer::forTeam($teamId)->where('id', $id)->delete();
        unset($this->employers);
        $this->dispatch('toast', message: 'Gelöscht');
    }

    public function render()
    {
        return view('people::livewire.employer.index')
            ->layout('platform::layouts.app');
    }
}
