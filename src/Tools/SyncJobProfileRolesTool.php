<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\JobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

/**
 * Setzt die Rollen-Verteilung eines JobProfiles atomar — bestehende Pivot-Zeilen
 * werden ersetzt, neue erzeugt. Das ist der praktische Hauptweg, weil ein
 * JobProfile typisch in einem Zug definiert wird (alle Rollen + Anteile auf einmal).
 *
 * Rollen sind Organization-Rollen (weiche Referenz auf organization_roles.id).
 * Existenz/Team wird tolerant geprueft: fehlt Organization oder die Zeile, wird
 * die role_id trotzdem geschrieben (weiche Referenz, kein harter Abbruch).
 */
class SyncJobProfileRolesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.job_profile_roles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /people/job-profile-roles - Setzt die Rollen-Verteilung eines JobProfiles atomar. Vorhandene Rollen-Verknuepfungen werden ersetzt. Input: roles als Array von { role_id, percentage_share, sort_order? }. role_id sind Organization-Rollen (weiche Referenz). Summe der percentage_share sollte typisch 100 ergeben.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des JobProfiles.'],
                'roles' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Liste der Rollen mit Anteil. Leer = alle Rollen-Verknuepfungen entfernen.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'role_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Organization-Rolle.'],
                            'percentage_share' => ['type' => 'integer', 'description' => 'Anteil in Prozent (0..100). Default: 0.'],
                            'sort_order' => ['type' => 'integer', 'description' => 'Optional: Sortierung. Default: index in der Liste.'],
                        ],
                        'required' => ['role_id'],
                    ],
                ],
            ],
            'required' => ['job_profile_id', 'roles'],
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
                $arguments, $context, 'job_profile_id',
                JobProfile::class, 'NOT_FOUND', 'JobProfile nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var JobProfile $jp */
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehoert nicht zum Team.');
            }

            $roles = $arguments['roles'] ?? null;
            if (! is_array($roles)) {
                return ToolResult::error('VALIDATION_ERROR', 'roles muss ein Array sein.');
            }

            // Build sync-Map: role_id => [percentage_share, sort_order]
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
                // Fehlt Klasse oder Row, wird die role_id dennoch geschrieben.
                if (! $this->roleExistsInTeam($roleId, $rootTeamId)) {
                    $unknownRoleIds[] = $roleId;
                }

                $sync[$roleId] = [
                    'percentage_share' => $share,
                    'sort_order' => $sortOrder,
                ];
                $totalShare += $share;
            }

            $jp->roles()->sync($sync);

            $message = 'Rollen-Verteilung des JobProfiles aktualisiert.';
            if ($totalShare !== 100 && count($sync) > 0) {
                $message .= " Hinweis: Summe der Anteile = {$totalShare} (typisch 100).";
            }
            if (! empty($unknownRoleIds)) {
                $message .= ' Hinweis: Nicht auffindbare/fremde Organization-Rollen (weiche Referenz beibehalten): ' . implode(', ', $unknownRoleIds) . '.';
            }

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'roles_count' => count($sync),
                'total_share' => $totalShare,
                'unknown_role_ids' => $unknownRoleIds,
                'roles' => $jp->roles()->get()->map(fn ($r) => [
                    'role_id' => $r->id,
                    'role_name' => $r->name,
                    'vsm_system' => $r->vsm_system ?? null,
                    'percentage_share' => (int) $r->pivot->percentage_share,
                    'sort_order' => (int) $r->pivot->sort_order,
                ])->all(),
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
            'tags' => ['people', 'job_profiles', 'roles', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
