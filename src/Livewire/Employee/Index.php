<?php

namespace Platform\People\Livewire\Employee;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\Employee;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = '';

    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [
        'display_name' => '',
        'employee_number' => '',
        'status' => 'active',
        'org_entity_id' => '',
    ];

    #[Computed]
    public function employees()
    {
        $teamId = Auth::user()->currentTeam->id;

        return Employee::forTeam($teamId)
            ->withCount('skills')
            ->when($this->search, fn ($q) => $q->where('display_name', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('display_name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['display_name' => '', 'employee_number' => '', 'status' => 'active', 'org_entity_id' => ''];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $employee = Employee::forTeam($teamId)->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'display_name' => $employee->display_name,
            'employee_number' => $employee->employee_number ?? '',
            'status' => $employee->status,
            'org_entity_id' => $employee->org_entity_id ? (string) $employee->org_entity_id : '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.display_name' => 'required|string|max:255',
            'form.employee_number' => 'nullable|string|max:255',
            'form.status' => 'required|in:active,inactive,left',
            'form.org_entity_id' => 'nullable',
        ]);

        $teamId = Auth::user()->currentTeam->id;

        $orgEntityId = ($this->form['org_entity_id'] !== '' && ctype_digit((string) $this->form['org_entity_id']))
            ? (int) $this->form['org_entity_id']
            : null;

        // Über Model speichern (nicht Query-Builder), damit der saved-Hook den
        // dimension_link auf den Org-Personen-Knoten spiegelt.
        $employee = $this->editingId
            ? Employee::forTeam($teamId)->findOrFail($this->editingId)
            : new Employee(['team_id' => $teamId]);

        $employee->fill([
            'display_name'    => trim($this->form['display_name']),
            'employee_number' => $this->form['employee_number'] !== '' ? trim($this->form['employee_number']) : null,
            'status'          => $this->form['status'],
            'org_entity_id'   => $orgEntityId,
        ]);
        if (empty($employee->team_id)) {
            $employee->team_id = $teamId;
        }
        $employee->save();

        $msg = $this->editingId ? 'Gespeichert' : 'Erstellt';

        $this->showModal = false;
        $this->editingId = null;
        unset($this->employees);
        $this->dispatch('toast', message: $msg);
    }

    public function delete(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        Employee::forTeam($teamId)->where('id', $id)->delete();

        unset($this->employees);
        $this->dispatch('toast', message: 'Gelöscht');
    }

    public function render()
    {
        return view('people::livewire.employee.index', [
            'orgEntityOptions' => $this->orgEntityOptions(),
        ])->layout('platform::layouts.app');
    }

    /**
     * Org-Personen-Knoten zur Verknüpfung (guarded — organization optional).
     * @return array<int,string> [entity_id => name]
     */
    protected function orgEntityOptions(): array
    {
        try {
            $teamId = Auth::user()->currentTeam->id;
            return \Platform\Organization\Models\OrganizationEntity::query()
                ->forTeam($teamId)->persons()->orderBy('name')->limit(500)
                ->pluck('name', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
