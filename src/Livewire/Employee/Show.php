<?php

namespace Platform\People\Livewire\Employee;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\People\Models\Employee as EmployeeModel;
use Platform\People\Support\PersonProfile;
use Platform\People\Support\OrganizationLink;
use Platform\People\Services\ContactDirectoryRegistry;

/**
 * Employee/Show — Steckbrief eines Mitarbeiters. Zeigt Basis-Stammdaten + (graph-nativ)
 * die am Personen-Knoten hängende CRM-Kontakt-Anreicherung und den „Mit CRM verknüpfen"-
 * Flow. Spiegelbild von customer Company/Show.
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

        $crmProfile = $employee->org_entity_id
            ? PersonProfile::crmForEntity((int) $employee->org_entity_id)
            : null;

        return view('people::livewire.employee.show', [
            'employee'           => $employee,
            'crmProfile'         => $crmProfile,
            'directoryAvailable' => $this->directory() !== null,
        ])->layout('platform::layouts.app');
    }
}
