<?php

namespace Platform\People\Livewire\Skill;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeSkill;
use Platform\People\Models\Skill;

class Index extends Component
{
    public string $search = '';
    public string $categoryFilter = '';
    public bool $showMatrix = false;

    // Create/Edit catalog modal
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = ['name' => '', 'category' => 'technical', 'description' => ''];

    // Matrix assignment modal
    public bool $showAssignModal = false;
    public ?int $assignSkillId = null;
    public ?int $assignEmployeeId = null;
    public string $assignLevel = 'basic';

    #[Computed]
    public function skills()
    {
        $teamId = Auth::user()->currentTeam->id;

        return Skill::forTeam($teamId)
            ->withCount('employeeSkills')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function employees()
    {
        $teamId = Auth::user()->currentTeam->id;

        return Employee::forTeam($teamId)
            ->active()
            ->with('skills')
            ->orderBy('display_name')
            ->get();
    }

    #[Computed]
    public function matrix(): array
    {
        $employees = $this->employees;
        $grid = [];

        foreach ($this->skills as $skill) {
            $row = ['skill' => $skill, 'cells' => []];
            foreach ($employees as $employee) {
                $relation = $employee->skills->firstWhere('skill_id', $skill->id);
                $row['cells'][] = [
                    'employee_id' => $employee->id,
                    'level' => $relation?->level,
                ];
            }
            $grid[] = $row;
        }

        return $grid;
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'category' => 'technical', 'description' => ''];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $skill = Skill::forTeam($teamId)->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'name' => $skill->name,
            'category' => $skill->category,
            'description' => $skill->description ?? '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.category' => 'required|in:technical,methodical,domain,social',
            'form.description' => 'nullable|string',
        ]);

        $teamId = Auth::user()->currentTeam->id;

        $data = [
            'name' => trim($this->form['name']),
            'category' => $this->form['category'],
            'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
            'team_id' => $teamId,
        ];

        if ($this->editingId) {
            Skill::forTeam($teamId)->where('id', $this->editingId)->update($data);
            $msg = 'Gespeichert';
        } else {
            Skill::create($data);
            $msg = 'Erstellt';
        }

        $this->showModal = false;
        $this->editingId = null;
        unset($this->skills, $this->matrix);
        $this->dispatch('toast', message: $msg);
    }

    public function delete(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        Skill::forTeam($teamId)->where('id', $id)->delete();

        unset($this->skills, $this->matrix);
        $this->dispatch('toast', message: 'Gelöscht');
    }

    public function toggleActive(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $skill = Skill::forTeam($teamId)->findOrFail($id);
        $skill->update(['is_active' => ! $skill->is_active]);

        unset($this->skills);
        $this->dispatch('toast', message: $skill->is_active ? 'Aktiviert' : 'Deaktiviert');
    }

    public function openAssignModal(int $skillId, int $employeeId): void
    {
        $this->assignSkillId = $skillId;
        $this->assignEmployeeId = $employeeId;

        $existing = EmployeeSkill::where('employee_id', $employeeId)
            ->where('skill_id', $skillId)
            ->first();
        $this->assignLevel = $existing?->level ?? 'basic';

        $this->showAssignModal = true;
    }

    public function saveAssignment(): void
    {
        $teamId = Auth::user()->currentTeam->id;

        EmployeeSkill::updateOrCreate(
            ['employee_id' => $this->assignEmployeeId, 'skill_id' => $this->assignSkillId],
            ['level' => $this->assignLevel, 'team_id' => $teamId],
        );

        $this->showAssignModal = false;
        unset($this->employees, $this->matrix);
        $this->dispatch('toast', message: 'Zuordnung gespeichert');
    }

    public function removeAssignment(): void
    {
        EmployeeSkill::where('employee_id', $this->assignEmployeeId)
            ->where('skill_id', $this->assignSkillId)
            ->delete();

        $this->showAssignModal = false;
        unset($this->employees, $this->matrix);
        $this->dispatch('toast', message: 'Zuordnung entfernt');
    }

    public function render()
    {
        return view('people::livewire.skill.index')
            ->layout('platform::layouts.app');
    }
}
