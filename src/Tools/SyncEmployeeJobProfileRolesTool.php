<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Setzt die Override-Rollen-Anteile einer EmployeeJobProfile-Zuweisung.
 * Override gewinnt gegenueber den Default-Anteilen aus dem JobProfile.
 *
 * Leeres roles-Array entfernt alle Overrides — danach gelten wieder die
 * Defaults aus dem JobProfile.
 *
 * Rollen sind Organization-Rollen (weiche Referenz auf organization_roles.id).
 * Existenz/Team wird tolerant geprueft: fehlt Organization oder die Zeile, wird
 * die role_id trotzdem geschrieben (kein harter Abbruch).
 */
class SyncEmployeeJobProfileRolesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.employee_job_profile_roles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /people/employee-job-profile-roles - Setzt individuelle Rollen-Anteile fuer eine Mitarbeiter-Profile-Zuweisung (Override gegenueber JobProfile-Defaults). Leere Liste entfernt alle Overrides — Defaults greifen wieder. Input: roles als Array von { role_id, percentage_share, sort_order? }. role_id sind Organization-Rollen (weiche Referenz).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'employee_job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der EmployeeJobProfile-Zuweisung.'],
                'roles' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Liste der Rollen-Overrides. Leeres Array entfernt alle Overrides (Defaults aus JobProfile greifen wieder).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'role_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Organization-Rolle.'],
                            'percentage_share' => ['type' => 'integer'],
                            'sort_order' => ['type' => 'integer'],
                        ],
                        'required' => ['role_id'],
                    ],
                ],
            ],
            'required' => ['employee_job_profile_id', 'roles'],
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
                $arguments, $context, 'employee_job_profile_id',
                EmployeeJobProfile::class, 'NOT_FOUND', 'EmployeeJobProfile-Zuweisung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var EmployeeJobProfile $ejp */
            $ejp = $found['model'];
            if ((int) $ejp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuweisung gehoert nicht zum Team.');
            }

            $roles = $arguments['roles'] ?? null;
            if (! is_array($roles)) {
                return ToolResult::error('VALIDATION_ERROR', 'roles muss ein Array sein.');
            }

            $sync = [];
            $totalShare = 0;
            $unknownRoleIds = [];
            foreach ($roles as $i => $entry) {
                if (! is_array($entry) || ! isset($entry['role_id'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Jeder roles-Eintrag braucht role_id.');
                }
                $roleId = (int) $entry['role_id'];
                $share = isset($entry['percentage_share']) ? (int) $entry['percentage_share'] : 0;
                if ($share < 0 || $share > 100) {
                    return ToolResult::error('VALIDATION_ERROR', "percentage_share von role_id={$roleId} muss zwischen 0 und 100 liegen.");
                }
                $sortOrder = isset($entry['sort_order']) ? (int) $entry['sort_order'] : $i;

                // Weiche Existenz-Pruefung gegen Organization (optionales Modul).
                if (! $this->roleExistsInTeam($roleId, $rootTeamId)) {
                    $unknownRoleIds[] = $roleId;
                }

                $sync[$roleId] = [
                    'percentage_share' => $share,
                    'sort_order' => $sortOrder,
                ];
                $totalShare += $share;
            }

            $ejp->roleOverrides()->sync($sync);

            // Effektive Verteilung neu berechnen — Quelle ist jetzt 'override' wenn nicht leer
            $effective = $ejp->fresh()->effectiveRoleShares();

            $message = count($sync) > 0
                ? 'Override-Rollen-Anteile gesetzt.' . ($totalShare !== 100 ? " Hinweis: Summe = {$totalShare} (typisch 100)." : '')
                : 'Alle Overrides entfernt — Defaults aus JobProfile greifen wieder.';
            if (! empty($unknownRoleIds)) {
                $message .= ' Hinweis: Nicht auffindbare/fremde Organization-Rollen (weiche Referenz beibehalten): ' . implode(', ', $unknownRoleIds) . '.';
            }

            return ToolResult::success([
                'employee_job_profile_id' => $ejp->id,
                'overrides_count' => count($sync),
                'total_share' => $totalShare,
                'source' => count($sync) > 0 ? 'override' : 'default',
                'unknown_role_ids' => $unknownRoleIds,
                'effective_roles' => $effective->map(fn ($e) => [
                    'role_id' => $e['role_id'],
                    'role_name' => $e['role']->name ?? null,
                    'vsm_system' => $e['role']->vsm_system ?? null,
                    'percentage_share' => $e['percentage_share'],
                    'source' => $e['source'],
                ])->values()->all(),
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Prueft weich, ob eine Organization-Rolle existiert und zum Team gehoert.
     * Toleriert fehlendes Organization-Modul (Klasse/Row nicht vorhanden).
     */
    protected function roleExistsInTeam(int $roleId, int $rootTeamId): bool
    {
        try {
            if (! class_exists(\Platform\Organization\Models\OrganizationRole::class)) {
                return false;
            }
            $role = \Platform\Organization\Models\OrganizationRole::find($roleId);
            if (! $role) {
                return false;
            }

            return (int) $role->team_id === $rootTeamId;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['people', 'employee_job_profiles', 'roles', 'override', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
