<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\JobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class ListJobProfileSkillsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.job_profile_skills.GET';
    }

    public function getDescription(): string
    {
        return 'GET /people/job-profile-skills - Listet alle Skills eines JobProfiles (strukturierte Zuordnungen aus dem People-Katalog).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
            ],
            'required' => ['job_profile_id'],
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

            $skills = $jp->skillRecords()->orderBy('people_job_profile_skills.sort_order')->get();
            $items = $skills->map(fn ($s) => [
                'skill_id'       => $s->id,
                'skill_name'     => $s->name,
                'skill_category' => $s->category,
                'level'          => $s->pivot->level,
                'is_required'    => (bool) $s->pivot->is_required,
                'sort_order'     => (int) $s->pivot->sort_order,
            ])->values()->toArray();

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'data'           => $items,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['people', 'job_profiles', 'skills'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
