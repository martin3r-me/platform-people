<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\JobProfile;
use Platform\People\Models\Skill;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class AssignJobProfileSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.job_profile_skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /people/job-profile-skills - Ordnet einen Skill (aus dem People-Katalog) einem JobProfile zu.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
                'skill_id'       => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
                'level'          => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert. Default: expert.'],
                'is_required'    => ['type' => 'boolean', 'description' => 'Optional: Pflicht-Skill? Default: true.'],
                'sort_order'     => ['type' => 'integer', 'description' => 'Optional: Sortierung. Default: 0.'],
            ],
            'required' => ['job_profile_id', 'skill_id'],
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
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehört nicht zum Team.');
            }

            $skillId = (int) ($arguments['skill_id'] ?? 0);
            $skill = Skill::find($skillId);
            if (! $skill || (int) $skill->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Skill nicht gefunden oder gehört nicht zum Team.');
            }

            // Check duplicate
            if ($jp->skillRecords()->where('people_skills.id', $skillId)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Skill ist bereits diesem JobProfile zugeordnet.');
            }

            $level = $arguments['level'] ?? 'expert';
            if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                $level = 'expert';
            }

            $jp->skillRecords()->attach($skillId, [
                'level'       => $level,
                'is_required' => $arguments['is_required'] ?? true,
                'sort_order'  => (int) ($arguments['sort_order'] ?? 0),
            ]);

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'skill_id'       => $skillId,
                'level'          => $level,
                'message'        => 'Skill erfolgreich zugeordnet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'job_profiles', 'skills', 'assign'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
