<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\Skill;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class CreateSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/skills - Erstellt einen Skill im Team-Katalog.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Skills (ERFORDERLICH).',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Kategorie (technical/methodical/domain/social). Default: technical.',
                    'enum' => ['technical', 'methodical', 'domain', 'social'],
                    'default' => 'technical',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Skills.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['name'],
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

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $category = trim((string)($arguments['category'] ?? 'technical'));
            if (!in_array($category, ['technical', 'methodical', 'domain', 'social'], true)) {
                return ToolResult::error('VALIDATION_ERROR', 'category muss technical, methodical, domain oder social sein.');
            }

            $exists = Skill::query()
                ->where('team_id', $rootTeamId)
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Skill mit name '{$name}' existiert bereits im Team.");
            }

            $skill = Skill::create([
                'team_id' => $rootTeamId,
                'name' => $name,
                'category' => $category,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'is_active' => (bool)($arguments['is_active'] ?? true),
            ]);

            return ToolResult::success([
                'id' => $skill->id,
                'uuid' => $skill->uuid,
                'name' => $skill->name,
                'category' => $skill->category,
                'message' => 'Skill erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Skills: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['people', 'skills', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
