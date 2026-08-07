<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Liefert die effektive Rollen-Verteilung einer EmployeeJobProfile-Zuweisung.
 * Inklusive Quellen-Markierung ('override' oder 'default') und der
 * Multiplikation mit der overall percentage als 'effective_overall_share'.
 *
 * Rollen sind Organization-Rollen (weiche Referenz); Rollen-Attribute werden
 * tolerant gelesen.
 */
class GetEmployeeJobProfileEffectiveRolesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_job_profiles.effective_roles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/employee-job-profiles/{id}/effective-roles - Effektive Rollen-Verteilung einer Mitarbeiter-Profile-Zuweisung. Zeigt Override (wenn gesetzt) oder Default-Anteile aus dem JobProfile, jeweils auch multipliziert mit der overall percentage.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'employee_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['employee_job_profile_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $id = (int) ($arguments['employee_job_profile_id'] ?? 0);
            $ejp = EmployeeJobProfile::query()
                ->where('id', $id)
                ->where('team_id', $rootTeamId)
                ->with(['jobProfile', 'employee', 'contextEntity'])
                ->first();
            if (! $ejp) {
                return ToolResult::error('NOT_FOUND', 'EmployeeJobProfile-Zuweisung nicht gefunden.');
            }

            $overall = (int) $ejp->percentage;
            $rows = $ejp->effectiveRoleShares()->map(function ($e) use ($overall) {
                $share = (int) $e['percentage_share'];
                return [
                    'role_id' => $e['role_id'],
                    'role_name' => $e['role']->name ?? null,
                    'vsm_system' => $e['role']->vsm_system ?? null,
                    'percentage_share' => $share,
                    'effective_overall_share' => (int) round($share * $overall / 100),
                    'source' => $e['source'],
                ];
            })->values()->all();

            return ToolResult::success([
                'employee_job_profile_id' => $ejp->id,
                'employee_id' => $ejp->employee_id,
                'employee_name' => $ejp->employee?->display_name,
                'job_profile_id' => $ejp->job_profile_id,
                'job_profile_name' => $ejp->jobProfile?->name,
                'context_entity_id' => $ejp->context_entity_id,
                'context_name' => $ejp->contextEntity?->name ?? null,
                'overall_percentage' => $overall,
                'is_primary' => (bool) $ejp->is_primary,
                'effective_roles' => $rows,
                'total_share' => array_sum(array_column($rows, 'percentage_share')),
                'total_effective_share' => array_sum(array_column($rows, 'effective_overall_share')),
                'override_active' => ! empty($rows) && $rows[0]['source'] === 'override',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['people', 'employee_job_profiles', 'roles', 'effective'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
