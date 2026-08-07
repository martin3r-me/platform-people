<?php

namespace Platform\People\Tools;

use Illuminate\Validation\ValidationException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Models\JobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class CreateEmployeeJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_job_profiles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/employee-job-profiles - Weist einem Mitarbeiter ein JobProfile mit Prozentsatz und optionalem Zeitraum zu. employee_id muss ein People-Mitarbeiter im Team sein.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'employee_id'      => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID eines People-Mitarbeiters.'],
                'job_profile_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des JobProfiles.'],
                'context_entity_id'=> ['type' => 'integer', 'description' => 'Optional: Linie/Carrier in dem das Profile getragen wird (weiche Organization-Referenz). Null = ohne festen Linien-Bezug.'],
                'percentage'       => ['type' => 'integer', 'description' => 'Optional: 0–100. Default: 100.'],
                'is_primary'       => ['type' => 'boolean', 'description' => 'Optional: Ist das Hauptprofil des Mitarbeiters? Default: false.'],
                'valid_from'       => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'valid_to'         => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'note'             => ['type' => 'string', 'description' => 'Optional: Notiz.'],
            ],
            'required' => ['employee_id', 'job_profile_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments, $context, 'employee_id',
                Employee::class, 'NOT_FOUND', 'Mitarbeiter nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var Employee $employee */
            $employee = $found['model'];
            if ((int) $employee->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Mitarbeiter gehört nicht zum Team.');
            }

            $jobProfileId = (int) ($arguments['job_profile_id'] ?? 0);
            if ($jobProfileId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'job_profile_id ist erforderlich.');
            }
            $jp = JobProfile::find($jobProfileId);
            if (! $jp || (int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'JobProfile nicht gefunden oder gehört nicht zum Team.');
            }

            $percentage = (int) ($arguments['percentage'] ?? 100);
            if ($percentage < 0 || $percentage > 100) {
                return ToolResult::error('VALIDATION_ERROR', 'percentage muss zwischen 0 und 100 liegen.');
            }

            $contextId = isset($arguments['context_entity_id']) && $arguments['context_entity_id']
                ? (int) $arguments['context_entity_id']
                : null;

            $assignment = EmployeeJobProfile::create([
                'team_id'           => $rootTeamId,
                'employee_id'       => $employee->id,
                'job_profile_id'    => $jobProfileId,
                'context_entity_id' => $contextId,
                'percentage'        => $percentage,
                'is_primary'        => (bool) ($arguments['is_primary'] ?? false),
                'valid_from'        => ($arguments['valid_from'] ?? null) ?: null,
                'valid_to'          => ($arguments['valid_to'] ?? null) ?: null,
                'note'              => ($arguments['note'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'id'                => $assignment->id,
                'employee_id'       => $assignment->employee_id,
                'job_profile_id'    => $assignment->job_profile_id,
                'context_entity_id' => $assignment->context_entity_id,
                'percentage'        => $assignment->percentage,
                'is_primary'        => (bool) $assignment->is_primary,
                'message'           => 'JobProfile erfolgreich dem Mitarbeiter zugewiesen.',
            ]);
        } catch (ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Zuweisung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'employee_job_profiles', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
