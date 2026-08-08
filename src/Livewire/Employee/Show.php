<?php

namespace Platform\People\Livewire\Employee;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\People\Models\Employee as EmployeeModel;
use Platform\People\Models\Employer;
use Platform\People\Models\Employment;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Support\PersonProfile;
use Platform\People\Support\OrganizationLink;
use Platform\People\Support\OrgContextResolver;
use Platform\People\Services\ContactDirectoryRegistry;

/**
 * Employee/Show — Steckbrief eines Mitarbeiters: Anstellung (Arbeitsvertrag),
 * Skills, Jobprofile und (graph-nativ) die CRM-Kontakt-Anreicherung am
 * Personen-Knoten inkl. „Mit CRM verknüpfen"-Flow.
 */
class Show extends Component
{
    #[Locked]
    public int $employeeId;

    // CRM-Verknüpfung
    public bool $showCrmModal = false;
    public string $crmSearch = '';
    public array $crmResults = [];
    public string $newContactName = '';

    // Anstellung (Arbeitsvertrag)
    public bool $showEmpModal = false;
    public ?int $editingEmpId = null;
    public array $empForm = [
        'employer_id'          => '',
        'employment_type'      => 'regular',
        'status'               => 'active',
        'started_on'           => '',
        'ended_on'             => '',
        'is_fixed_term'        => false,
        'fixed_term_end_date'  => '',
        'probation_end_date'   => '',
        'fte'                  => '',
        'weekly_hours'         => '',
        'weekly_days'          => '',
        'annual_vacation_days' => '',
        'wage_type'            => '',
        'gross_amount'         => '',
        'work_location'        => '',
        'note'                 => '',
    ];

    public function mount(int $employee): void
    {
        $this->employeeId = $this->resolve($employee)->id;
    }

    protected function resolve(int $id): EmployeeModel
    {
        $teamId = Auth::user()->currentTeam->id;

        return EmployeeModel::forTeam($teamId)->findOrFail($id);
    }

    protected function team(): int
    {
        return (int) Auth::user()->currentTeam->id;
    }

    // ── Anstellung / Arbeitsvertrag ──────────────────────────────────────
    public function openEmpCreate(): void
    {
        $this->editingEmpId = null;
        $this->empForm = [
            'employer_id' => '', 'employment_type' => 'regular', 'status' => 'active',
            'started_on' => '', 'ended_on' => '', 'is_fixed_term' => false,
            'fixed_term_end_date' => '', 'probation_end_date' => '',
            'fte' => '', 'weekly_hours' => '', 'weekly_days' => '',
            'annual_vacation_days' => '', 'wage_type' => '', 'gross_amount' => '',
            'work_location' => '', 'note' => '',
        ];
        $this->showEmpModal = true;
    }

    public function openEmpEdit(int $id): void
    {
        $emp = Employment::where('employee_id', $this->employeeId)->findOrFail($id);
        $this->editingEmpId = $id;
        $this->empForm = [
            'employer_id'          => $emp->employer_id ? (string) $emp->employer_id : '',
            'employment_type'      => $emp->employment_type,
            'status'               => $emp->status,
            'started_on'           => optional($emp->started_on)->format('Y-m-d') ?? '',
            'ended_on'             => optional($emp->ended_on)->format('Y-m-d') ?? '',
            'is_fixed_term'        => (bool) $emp->is_fixed_term,
            'fixed_term_end_date'  => optional($emp->fixed_term_end_date)->format('Y-m-d') ?? '',
            'probation_end_date'   => optional($emp->probation_end_date)->format('Y-m-d') ?? '',
            'fte'                  => $emp->fte ?? '',
            'weekly_hours'         => $emp->weekly_hours ?? '',
            'weekly_days'          => $emp->weekly_days ?? '',
            'annual_vacation_days' => $emp->annual_vacation_days ?? '',
            'wage_type'            => $emp->wage_type ?? '',
            'gross_amount'         => $emp->gross_amount ?? '',
            'work_location'        => $emp->work_location ?? '',
            'note'                 => $emp->note ?? '',
        ];
        $this->showEmpModal = true;
    }

    public function saveEmployment(): void
    {
        $this->validate([
            'empForm.employment_type' => 'required|in:regular,part_time,temporary,marginal,freelance',
            'empForm.status'          => 'required|in:active,ended',
            'empForm.started_on'      => 'nullable|date',
            'empForm.ended_on'        => 'nullable|date',
            'empForm.wage_type'       => 'nullable|in:salary,hourly',
            'empForm.gross_amount'    => 'nullable|numeric|min:0',
            'empForm.annual_vacation_days' => 'nullable|integer|min:0|max:365',
        ]);

        $employee = $this->resolve($this->employeeId);

        $data = [
            'team_id'              => $employee->team_id,
            'employee_id'          => $employee->id,
            'employer_id'          => ($this->empForm['employer_id'] !== '' && ctype_digit((string) $this->empForm['employer_id'])) ? (int) $this->empForm['employer_id'] : null,
            'employment_type'      => $this->empForm['employment_type'],
            'status'               => $this->empForm['status'],
            'started_on'           => $this->empForm['started_on'] ?: null,
            'ended_on'             => $this->empForm['ended_on'] ?: null,
            'is_fixed_term'        => (bool) $this->empForm['is_fixed_term'],
            'fixed_term_end_date'  => $this->empForm['fixed_term_end_date'] ?: null,
            'probation_end_date'   => $this->empForm['probation_end_date'] ?: null,
            'fte'                  => $this->empForm['fte'] !== '' ? $this->empForm['fte'] : null,
            'weekly_hours'         => $this->empForm['weekly_hours'] !== '' ? $this->empForm['weekly_hours'] : null,
            'weekly_days'          => $this->empForm['weekly_days'] !== '' ? $this->empForm['weekly_days'] : null,
            'annual_vacation_days' => $this->empForm['annual_vacation_days'] !== '' ? (int) $this->empForm['annual_vacation_days'] : null,
            'wage_type'            => $this->empForm['wage_type'] ?: null,
            'gross_amount'         => $this->empForm['gross_amount'] !== '' ? $this->empForm['gross_amount'] : null,
            'work_location'        => trim((string) $this->empForm['work_location']) ?: null,
            'note'                 => trim((string) $this->empForm['note']) ?: null,
        ];

        if ($this->editingEmpId) {
            Employment::where('employee_id', $employee->id)->where('id', $this->editingEmpId)->update($data);
            $msg = 'Anstellung gespeichert';
        } else {
            Employment::create($data);
            $msg = 'Anstellung angelegt';
        }

        $this->showEmpModal = false;
        $this->editingEmpId = null;
        $this->dispatch('toast', message: $msg);
    }

    public function deleteEmployment(int $id): void
    {
        Employment::where('employee_id', $this->employeeId)->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Anstellung entfernt');
    }

    /** Bei Arbeitgeber-Wahl leere Vertragsfelder mit dessen Defaults vorbelegen. */
    public function updatedEmpFormEmployerId($value): void
    {
        if ($value === '' || ! ctype_digit((string) $value)) {
            return;
        }
        $employer = Employer::forTeam($this->team())->find((int) $value);
        if (! $employer) {
            return;
        }
        if (($this->empForm['annual_vacation_days'] === '' || $this->empForm['annual_vacation_days'] === null) && $employer->default_vacation_days !== null) {
            $this->empForm['annual_vacation_days'] = $employer->default_vacation_days;
        }
        if (($this->empForm['weekly_hours'] === '' || $this->empForm['weekly_hours'] === null) && $employer->default_weekly_hours !== null) {
            $this->empForm['weekly_hours'] = $employer->default_weekly_hours;
        }
    }

    // ── CRM-Verknüpfung ──────────────────────────────────────────────────
    protected function directory()
    {
        try {
            return resolve(ContactDirectoryRegistry::class)->provider();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function openCrmLink(): void
    {
        $employee = $this->resolve($this->employeeId);
        $this->newContactName = (string) $employee->display_name;
        $this->crmSearch = '';
        $this->crmResults = [];
        $this->showCrmModal = true;
    }

    public function searchCrm(): void
    {
        $provider = $this->directory();
        if (!$provider) {
            $this->crmResults = [];
            return;
        }
        $this->crmResults = $provider->search($this->team(), trim($this->crmSearch));
    }

    public function linkExistingCrm(int $contactId): void
    {
        $this->attachCrm($contactId);
    }

    public function createAndLinkCrm(): void
    {
        $provider = $this->directory();
        if (!$provider || trim($this->newContactName) === '') {
            return;
        }
        $contactId = $provider->createContact($this->team(), trim($this->newContactName));
        if ($contactId) {
            $this->attachCrm($contactId);
        }
    }

    /** Verknüpft einen crm_contact mit dem Personen-Knoten des Mitarbeiters (dimension_link). */
    protected function attachCrm(int $contactId): void
    {
        $employee = $this->resolve($this->employeeId);
        if (!$employee->org_entity_id) {
            $this->dispatch('toast', message: 'Kein Personen-Knoten verknüpft — zuerst im Mitarbeiter setzen.');
            return;
        }

        OrganizationLink::sync('crm_contact', $contactId, (int) $employee->org_entity_id, $this->team(), Auth::id());

        $this->showCrmModal = false;
        $this->crmResults = [];
        $this->dispatch('toast', message: 'CRM-Kontakt verknüpft.');
    }

    public function render()
    {
        $employee = $this->resolve($this->employeeId)->loadCount('skills');

        $employments = Employment::where('employee_id', $employee->id)
            ->with('employer')->orderByDesc('started_on')->get();

        $skills = $employee->skills()->with('skill')->get();

        $assignments = EmployeeJobProfile::where('employee_id', $employee->id)
            ->with('jobProfile')->get();

        $employers = Employer::forTeam($employee->team_id)->active()->orderBy('name')->get();

        $orgContext = (new OrgContextResolver())->resolve($employee->org_entity_id);

        $crmProfile = $employee->org_entity_id
            ? PersonProfile::crmForEntity((int) $employee->org_entity_id)
            : null;

        return view('people::livewire.employee.show', [
            'employee'           => $employee,
            'employments'        => $employments,
            'skills'             => $skills,
            'assignments'        => $assignments,
            'employers'          => $employers,
            'orgContext'         => $orgContext,
            'crmProfile'         => $crmProfile,
            'directoryAvailable' => $this->directory() !== null,
        ])->layout('platform::layouts.app');
    }
}
