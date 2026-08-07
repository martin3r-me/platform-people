<?php

namespace Platform\People\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\People\Models\Employee;

/**
 * PeopleEntityLinkProvider — rendert den Mitarbeiter (Employee) reich am Org-Personen-Knoten
 * (dimension_link-Alias "people_employee"). So erscheint der Mensch als Angestellter im
 * Org-Graphen; die practice-Arzt-Facette komponiert später am selben Knoten. Vorbild:
 * CustomerEntityLinkProvider / CrmEntityLinkProvider.
 */
class PeopleEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['people_employee'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'people_employee' => [
                'label'    => 'Mitarbeiter',
                'singular' => 'Mitarbeiter:in',
                'icon'     => 'user',
                'route'    => 'people.employees.index',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->withCount('skills');
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias !== 'people_employee' || !$model instanceof Employee) {
            return [];
        }

        return array_filter([
            'name'            => $model->display_name,
            'employee_number' => $model->employee_number,
            'status'          => $model->status === 'active' ? 'aktiv' : ($model->status === 'inactive' ? 'inaktiv' : $model->status),
            'skills'          => isset($model->skills_count) && $model->skills_count > 0 ? (string) $model->skills_count : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function metadataDisplayRules(): array
    {
        return [
            'people_employee' => [
                ['field' => 'employee_number', 'format' => 'text'],
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'skills', 'format' => 'text'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'people_employee') {
            return [];
        }

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $result[$entityId] = [
                'people_employees_count' => is_countable($ids) ? count($ids) : 0,
            ];
        }
        return $result;
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }
}
