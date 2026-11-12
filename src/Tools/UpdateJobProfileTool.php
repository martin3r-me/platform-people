<?php

namespace Platform\People\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\People\Models\JobProfile;
use Platform\People\Tools\Concerns\ResolvesPeopleTeam;

class UpdateJobProfileTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesPeopleTeam;

    public function getName(): string
    {
        return 'people.job_profiles.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /people/job-profiles/{id} - Aktualisiert ein JobProfile.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'job_profile_id'   => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
                'name'             => ['type' => 'string'],
                'description'      => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'purpose'          => ['type' => 'string', 'description' => 'Rollenzweck. "" zum Leeren.'],
                'job_family'       => ['type' => 'string', 'description' => 'Laufbahn/Kategorie. "" zum Leeren.'],
                'content'          => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'level'            => ['type' => 'string'],
                'vsm_level'        => ['type' => 'array', 'description' => 'VSM-Funktions-Stufe(n) als Liste: ["S1"] / ["S4"] / ["S2","S3","S3star"] (eine Rolle kann mehrere tragen). Treibt rollen-gescopte Sinne im Daemon (Pulse nur für S2↑).'],
                'skills'           => ['type' => 'array', 'description' => 'Strukturierte Skills: [{"name": "...", "level": "basic|advanced|expert", "category": "technical|methodical|domain"}]'],
                'responsibilities' => ['type' => 'array', 'description' => 'Verantwortungen: [{"name": "...", "is_core": true}]'],
                'requirements'     => ['type' => 'array', 'description' => 'Qualifikationen: [{"name": "...", "type": "degree|certification|experience", "required": true}]'],
                'soft_skills'      => ['type' => 'array', 'description' => 'Soft Skills: [{"name": "...", "level": "basic|advanced|expert"}]'],
                'kpis'               => ['type' => 'array', 'description' => 'Bewertungskriterien: [{"name": "...", "description": "..."}]'],
                'exclusion_criteria' => ['type' => 'array', 'description' => 'Ausschlusskriterien: ["Keine Influencerin", ...]'],
                'work_model'         => ['type' => 'object', 'description' => 'Arbeitsmodell: {"type": "remote|hybrid|onsite|travel", "travel_required": bool, "self_organized": bool, "location_notes": "..."}'],
                'reporting'          => ['type' => 'object', 'description' => 'Reporting: {"reports_to": "...", "autonomy_level": "full|guided|supervised"}'],
                'status'             => ['type' => 'string'],
                'unit_entity_id'     => ['type' => 'integer', 'description' => 'Einheit (Firma/Abteilung, Org-Entity-ID), zu der die Stelle gehört — als dimension_link in der Organisation. Wer die Stelle TRÄGT, steht nicht hier, sondern in people.employee_job_profiles. 0 = Verknüpfung entfernen.'],
                'effective_from'   => ['type' => 'string'],
                'effective_to'     => ['type' => 'string'],
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
                $arguments,
                $context,
                'job_profile_id',
                JobProfile::class,
                'NOT_FOUND',
                'JobProfile nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var JobProfile $jp */
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            foreach (['name', 'level', 'status', 'job_family'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = trim((string) ($arguments[$field] ?? ''));
                    if ($field === 'name' && $val === '') {
                        return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                    }
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            foreach (['description', 'content', 'purpose'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            foreach (['vsm_level', 'skills', 'responsibilities', 'requirements', 'soft_skills', 'kpis', 'exclusion_criteria', 'work_model', 'reporting'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = is_array($arguments[$field]) ? $arguments[$field] : null;
                }
            }
            foreach (['effective_from', 'effective_to'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            // Einheit (Org-Wahrheit als dimension_link): >0 setzen, 0/leer = Verknüpfung entfernen.
            // Zuerst setzen — eine ungültige Einheit bricht ab, bevor irgendetwas geändert ist.
            if (array_key_exists('unit_entity_id', $arguments)) {
                $jp->setUnit((int) ($arguments['unit_entity_id'] ?? 0));
            }
            if (! empty($update)) {
                $jp->update($update);
            }
            $jp->refresh();

            return ToolResult::success([
                'id'      => $jp->id,
                'uuid'    => $jp->uuid,
                'name'    => $jp->name,
                'level'   => $jp->level,
                'status'  => $jp->status,
                'team_id' => $jp->team_id,
                'unit_entity_id' => $jp->unitEntityId(),
                'message' => 'JobProfile erfolgreich aktualisiert.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des JobProfiles: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['people', 'job_profiles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
