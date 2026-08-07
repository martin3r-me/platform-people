<?php

namespace Platform\People\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Models\JobProfile;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'active';
    public ?string $jobFamilyFilter = null;

    // Inline aufklappbare Employee-Zuweisungen
    public ?int $expandedProfileId = null;
    public array $assignForm = [
        'employee_id' => '',
        'percentage' => '100',
        'is_primary' => false,
        'valid_from' => '',
        'valid_to' => '',
        'note' => '',
    ];

    // Create/Edit-Modal
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = [
        'name' => '',
        'level' => '',
        'job_family' => '',
        'status' => 'active',
        'description' => '',
        'purpose' => '',
        'effective_from' => '',
        'effective_to' => '',
    ];

    protected $queryString = [
        'search'          => ['except' => ''],
        'statusFilter'    => ['except' => 'active'],
        'jobFamilyFilter' => ['except' => null],
    ];

    protected function rules(): array
    {
        return [
            'form.name'           => ['required', 'string', 'max:255'],
            'form.level'          => ['nullable', 'string', 'max:50'],
            'form.job_family'     => ['nullable', 'string', 'max:100'],
            'form.status'         => ['required', 'in:active,archived,draft'],
            'form.description'    => ['nullable', 'string'],
            'form.purpose'        => ['nullable', 'string'],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_to'   => ['nullable', 'date'],
        ];
    }

    #[Computed]
    public function jobProfiles()
    {
        $teamId = Auth::user()->currentTeam->id;

        return JobProfile::forTeam($teamId)
            ->withCount(['assignments', 'roles', 'skillRecords'])
            ->with(['assignments.employee'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('purpose', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(! empty($this->jobFamilyFilter), fn ($q) => $q->where('job_family', $this->jobFamilyFilter))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function jobFamilies(): array
    {
        return JobProfile::forTeam(Auth::user()->currentTeam->id)
            ->whereNotNull('job_family')
            ->distinct()
            ->pluck('job_family')
            ->sort()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function employeeOptions(): array
    {
        return Employee::forTeam(Auth::user()->currentTeam->id)
            ->active()
            ->orderBy('display_name')
            ->pluck('display_name', 'id')
            ->all();
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'level' => '',
            'job_family' => '',
            'status' => 'active',
            'description' => '',
            'purpose' => '',
            'effective_from' => '',
            'effective_to' => '',
        ];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $jp = JobProfile::forTeam($teamId)->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $id;
        $this->form = [
            'name'           => (string) $jp->name,
            'level'          => (string) ($jp->level ?? ''),
            'job_family'     => (string) ($jp->job_family ?? ''),
            'status'         => (string) ($jp->status ?? 'active'),
            'description'    => (string) ($jp->description ?? ''),
            'purpose'        => (string) ($jp->purpose ?? ''),
            'effective_from' => $jp->effective_from?->toDateString() ?? '',
            'effective_to'   => $jp->effective_to?->toDateString() ?? '',
        ];
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate()['form'];
        $teamId = Auth::user()->currentTeam->id;

        $payload = [
            'name'           => trim($data['name']),
            'level'          => $data['level'] !== '' ? $data['level'] : null,
            'job_family'     => $data['job_family'] !== '' ? $data['job_family'] : null,
            'status'         => $data['status'],
            'description'    => $data['description'] !== '' ? $data['description'] : null,
            'purpose'        => $data['purpose'] !== '' ? $data['purpose'] : null,
            'effective_from' => $data['effective_from'] ?: null,
            'effective_to'   => $data['effective_to'] ?: null,
        ];

        if ($this->editingId) {
            $jp = JobProfile::forTeam($teamId)->findOrFail($this->editingId);
            $jp->update($payload);
            $msg = 'Gespeichert';
        } else {
            JobProfile::create(array_merge($payload, [
                'team_id' => $teamId,
                'user_id' => Auth::id(),
            ]));
            $msg = 'Erstellt';
        }

        $this->showModal = false;
        $this->editingId = null;
        unset($this->jobProfiles, $this->jobFamilies);
        $this->dispatch('toast', message: $msg);
    }

    public function archive(int $id): void
    {
        $jp = JobProfile::forTeam(Auth::user()->currentTeam->id)->find($id);
        if ($jp) {
            $jp->update(['status' => 'archived']);
            unset($this->jobProfiles);
            $this->dispatch('toast', message: 'Archiviert');
        }
    }

    public function unarchive(int $id): void
    {
        $jp = JobProfile::forTeam(Auth::user()->currentTeam->id)->find($id);
        if ($jp) {
            $jp->update(['status' => 'active']);
            unset($this->jobProfiles);
            $this->dispatch('toast', message: 'Reaktiviert');
        }
    }

    public function delete(int $id): void
    {
        $jp = JobProfile::forTeam(Auth::user()->currentTeam->id)
            ->withCount('assignments')
            ->find($id);

        if (! $jp) {
            return;
        }

        if ($jp->assignments_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Profil ist Mitarbeitern zugewiesen. Bitte archivieren statt löschen.');

            return;
        }

        $jp->delete();
        unset($this->jobProfiles);
        $this->dispatch('toast', message: 'Gelöscht');
    }

    // --- Inline Employee-Zuweisungen ---

    public function toggleAssignments(int $id): void
    {
        $this->expandedProfileId = $this->expandedProfileId === $id ? null : $id;
        $this->resetAssignForm();
    }

    protected function resetAssignForm(): void
    {
        $this->assignForm = [
            'employee_id' => '',
            'percentage' => '100',
            'is_primary' => false,
            'valid_from' => '',
            'valid_to' => '',
            'note' => '',
        ];
    }

    public function storeAssignment(): void
    {
        if (! $this->expandedProfileId || empty($this->assignForm['employee_id'])) {
            return;
        }

        $teamId = Auth::user()->currentTeam->id;

        $jp = JobProfile::forTeam($teamId)->find($this->expandedProfileId);
        if (! $jp) {
            return;
        }

        EmployeeJobProfile::create([
            'team_id'        => $teamId,
            'job_profile_id' => $jp->id,
            'employee_id'    => (int) $this->assignForm['employee_id'],
            'percentage'     => $this->assignForm['percentage'] !== '' ? (int) $this->assignForm['percentage'] : null,
            'is_primary'     => (bool) $this->assignForm['is_primary'],
            'valid_from'     => $this->assignForm['valid_from'] ?: null,
            'valid_to'       => $this->assignForm['valid_to'] ?: null,
            'note'           => $this->assignForm['note'] !== '' ? $this->assignForm['note'] : null,
        ]);

        $this->resetAssignForm();
        unset($this->jobProfiles);
        $this->dispatch('toast', message: 'Zuweisung erstellt');
    }

    public function deleteAssignment(int $id): void
    {
        $assignment = EmployeeJobProfile::forTeam(Auth::user()->currentTeam->id)->find($id);
        if ($assignment) {
            $assignment->delete();
            unset($this->jobProfiles);
            $this->dispatch('toast', message: 'Zuweisung entfernt');
        }
    }

    public function render()
    {
        return view('people::livewire.job-profile.index')
            ->layout('platform::layouts.app');
    }
}
