<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Skill;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class DeleteSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.skills.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /people/skills/{id} - Löscht einen Skill (soft delete). Warnung wenn Zuordnungen existieren.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'skill_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Skills (ERFORDERLICH). Nutze people.skills.GET.',
                ],
            ],
            'required' => ['skill_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'skill_id',
                Skill::class,
                'NOT_FOUND',
                'Skill nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var Skill $skill */
            $skill = $found['model'];
            if ((int)$skill->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Skill gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            if ($skill->employeeSkills()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Skill ist Mitarbeitern zugeordnet. Bitte zuerst Zuordnungen entfernen oder is_active=false setzen.');
            }

            $skill->delete();

            return ToolResult::success([
                'id' => $skill->id,
                'message' => 'Skill gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Skills: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['people', 'skills', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
