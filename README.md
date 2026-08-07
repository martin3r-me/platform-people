# Platform People

**People** ist das System of Record für **den Menschen als Angestellten**. Es trägt
das Beschäftigungsverhältnis und den Fähigkeits-*Bestand* — bewusst schlank, als
Neuaufbau anstelle des überladenen `hcm`-Relikts.

## Kern-Domäne

| Konzept | Wofür |
|---|---|
| **Employee** | Stammsatz des angestellten Menschen. Join-Anker: verweist (weich) auf einen Platform-User und auf die Organization-Person-Entity. |
| **Employment** | Das Beschäftigungsverhältnis: Typ, Soll-Arbeitszeit/FTE, von–bis, Status. |
| **Skill** | Kompetenz-Katalog (fachlich *und* sozial über `category`). |
| **EmployeeSkill** | Fähigkeits-*Bestand*: Person → Skill mit `level` und `certified_at`. |

## Grenzlinie zu Organization

- **Organization** trägt die **Struktur** (Entities, Relations, Rollen) und leitet
  daraus die **Rechte** ab (Authz-Materializer liest Rollen- + Relation-Capabilities).
- **People** trägt den **Menschen** und seinen **Fähigkeits-Bestand**.
- **Bedarf vs. Bestand:** Organization formuliert *strukturellen Bedarf* (welche Rolle/
  VSM-Funktion eine Stelle braucht). People besitzt den *Ist-Bestand* (was die Person
  real kann). Das Matching ist eine Read-Time-Projektion, kein gespeicherter Zustand.
- **Abhängigkeitsrichtung:** nur **People → Organization**, nie umgekehrt. Die
  Verknüpfung läuft über eine weiche Referenz (`org_entity_id`), ohne DB-FK, damit die
  Module entkoppelt bleiben.

## Aufbau & Konventionen

Folgt dem Modul-Baukasten (wie `sandbox`): ServiceProvider registriert bei
`PlatformCore`, Config unter `config/people.php`, Route auf das Dashboard,
Livewire-Komponenten in `src/Livewire` (Auto-Discovery), Migrationen unter
`database/migrations`. Tabellen-Präfix `people_`.
