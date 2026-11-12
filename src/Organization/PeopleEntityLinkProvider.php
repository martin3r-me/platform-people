<?php

namespace Platform\People\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\People\Models\Employee;
use Platform\People\Models\EmployeeJobProfile;
use Platform\People\Models\Employment;
use Platform\People\Models\JobProfile;

/**
 * PeopleEntityLinkProvider — people als Facetten am Org-Baum (die Organisation kennt die Wahrheit):
 *   people_employee     Mitarbeiter (Mensch oder Agent) am Personen-/Agent-Knoten
 *   people_job_profile  Stelle an ihrer Einheit (Firma/Abteilung) — ergibt den Stellenplan
 * und liefert dazu die Kennzahlen in die Dimensionen; organization rechnet sie den Baum hinauf.
 */
class PeopleEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['people_employee', 'people_job_profile'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'people_employee' => [
                'label'    => 'Mitarbeiter',
                'singular' => 'Mitarbeiter:in',
                'icon'     => 'user',
                'route'    => 'people.employees.show',
            ],
            'people_job_profile' => [
                'label'    => 'Stellen',
                'singular' => 'Stelle',
                'icon'     => 'briefcase',
                'route'    => 'people.job-profiles.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === 'people_employee') {
            $query->withCount('skills');
        } elseif ($morphAlias === 'people_job_profile') {
            $query->withCount(['assignments' => fn ($q) => $this->valid($q)]);
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias === 'people_employee' && $model instanceof Employee) {
            return array_filter([
                'name'            => $model->display_name,
                'kind'            => Employee::KIND_LABELS[$model->kind] ?? $model->kind,
                'employee_number' => $model->employee_number,
                'status'          => $model->status === 'active' ? 'aktiv' : ($model->status === 'inactive' ? 'inaktiv' : $model->status),
                'skills'          => isset($model->skills_count) && $model->skills_count > 0 ? (string) $model->skills_count : null,
            ], fn ($v) => $v !== null && $v !== '');
        }
        if ($morphAlias === 'people_job_profile' && $model instanceof JobProfile) {
            $held = isset($model->assignments_count) ? (int) $model->assignments_count : null;

            return array_filter([
                'name'     => $model->name,
                'level'    => $model->level,
                'besetzt'  => $held === null ? null : ($held > 0 ? 'besetzt' : 'offen'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        return [];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'people_employee' => [
                ['field' => 'kind', 'format' => 'badge'],
                ['field' => 'employee_number', 'format' => 'text'],
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'skills', 'format' => 'text'],
            ],
            'people_job_profile' => [
                ['field' => 'level', 'format' => 'text'],
                ['field' => 'besetzt', 'format' => 'badge'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metricDefinitions(): array
    {
        return [
            // Mitglieder (Menschen + Agenten) — Bestand der Organisation.
            'people_members'         => ['label' => 'Mitglieder', 'group' => 'people', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'people_members_agents'  => ['label' => 'davon Agenten', 'group' => 'people', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag', 'subset_of' => 'people_members'],
            'people_members_new_90d' => ['label' => 'Neu (≤ 90 Tage)', 'group' => 'people', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],

            // Stellenplan — Stellen hängen an ihrer Einheit.
            'people_positions'       => ['label' => 'Stellen', 'group' => 'people', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'people_positions_open'  => ['label' => 'Offene Stellen', 'group' => 'people', 'direction' => 'down', 'unit' => 'count', 'pair' => 'people_positions', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        $all = array_values(array_unique(array_merge(...array_values(array_map(fn ($ids) => (array) $ids, $linksByEntity ?: [[]])))));
        if (! $all) {
            return [];
        }

        if ($morphAlias === 'people_employee') {
            $employees = Employee::query()->whereIn('id', $all)->where('status', 'active')->get(['id', 'kind'])->keyBy('id');
            $since = now()->subDays(90)->toDateString();
            $newIds = Employment::query()->whereIn('employee_id', $employees->keys())->where('started_on', '>=', $since)
                ->pluck('employee_id')->flip();

            $result = [];
            foreach ($linksByEntity as $entityId => $ids) {
                $mine = $employees->only((array) $ids);
                $result[$entityId] = [
                    'people_members'         => $mine->count(),
                    'people_members_agents'  => $mine->where('kind', 'agent')->count(),
                    'people_members_new_90d' => $mine->keys()->filter(fn ($id) => isset($newIds[$id]))->count(),
                ];
            }

            return $result;
        }

        if ($morphAlias === 'people_job_profile') {
            $active = JobProfile::query()->whereIn('id', $all)->where('status', 'active')->pluck('id')->flip();
            $filled = $this->valid(EmployeeJobProfile::query()->whereIn('job_profile_id', $active->keys()))
                ->whereHas('employee', fn ($q) => $q->where('status', 'active'))
                ->pluck('job_profile_id')->flip();

            $result = [];
            foreach ($linksByEntity as $entityId => $ids) {
                $mine = array_values(array_filter((array) $ids, fn ($id) => isset($active[$id])));
                $result[$entityId] = [
                    'people_positions'      => count($mine),
                    'people_positions_open' => count(array_filter($mine, fn ($id) => ! isset($filled[$id]))),
                ];
            }

            return $result;
        }

        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    /** Heute gültige Zuweisungen. */
    private function valid($query)
    {
        $today = now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today));
    }
}
