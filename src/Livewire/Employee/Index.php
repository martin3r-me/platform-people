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
        $this->form = ['display_name' => '', 'employee_number' => '', 'status' => 'active'];
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
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.display_name' => 'required|string|max:255',
            'form.employee_number' => 'nullable|string|max:255',
            'form.status' => 'required|in:active,inactive,left',
        ]);

        $teamId = Auth::user()->currentTeam->id;

        $data = [
            'display_name' => trim($this->form['display_name']),
            'employee_number' => $this->form['employee_number'] !== '' ? trim($this->form['employee_number']) : null,
            'status' => $this->form['status'],
            'team_id' => $teamId,
        ];

        if ($this->editingId) {
            Employee::forTeam($teamId)->where('id', $this->editingId)->update($data);
            $msg = 'Gespeichert';
        } else {
            Employee::create($data);
            $msg = 'Erstellt';
        }

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
        return view('people::livewire.employee.index')
            ->layout('platform::layouts.app');
    }
}
