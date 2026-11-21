<?php

namespace Platform\People\Contracts;

use Carbon\CarbonInterface;

/**
 * ImpactSource — ein Modul, das einen Teil der WIRKUNG eines Mitarbeiters kennt (Mensch oder Agent).
 *
 * people misst Wirkung im Sinne der Organisation, kennt aber keines der Module, in denen sie entsteht.
 * Jedes Modul meldet sich mit einer Quelle an (Registry-Muster, lose gekoppelt) und liefert nur, was es
 * weiß; alle anderen Facetten bleiben leer. Bezüge über Modulgrenzen sind Morph-Referenzen „alias:id"
 * (z. B. planner_task:12, planner_project:5, okr_key_result:7, management_book_milestone:3).
 *
 * Ein Subjekt ist ein Mitarbeiter: ['employee_id' => int, 'user_id' => ?int, 'entity_id' => ?int].
 * Jede Zeile, die einem Subjekt gehört, trägt dessen employee_id.
 *
 * Wer nur einen Teil kennt, erbt von {@see \Platform\People\Support\ImpactSourceBase}.
 */
interface ImpactSource
{
    /** Kurzer, eindeutiger Schlüssel der Quelle (z. B. 'planner'). */
    public function key(): string;

    /**
     * Abgeschlossene (abgenommene) Arbeitsstücke im Zeitraum — und im Zeitraum wieder geöffnete.
     *
     * @param  array<int,array{employee_id:int,user_id:?int,entity_id:?int}>  $subjects
     * @return array<int,array{
     *     employee_id:int, ref:string, title:?string,
     *     points:?int,          // selbst geschätzte Komplexität (Story Points als Zahl), null = nicht geschätzt
     *     accepted_at:CarbonInterface,
     *     documented:bool,      // nachvollziehbar dokumentiert (DoD/Zusammenfassung)
     *     reopened:bool,        // im Zeitraum wieder geöffnet — zieht ab
     *     minutes:?int,         // gebuchter Aufwand, falls bekannt
     *     context_refs:array<int,string>, // woran die Arbeit hängt (Aufgabe selbst, Projekt …)
     *     self_initiated:bool   // selbst angestoßen (nicht zugewiesen) — Kandidat fürs Querdenken
     * }>
     */
    public function work(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Ausrichtung: woran zahlen diese Bezüge ein?
     *
     * @param  array<int,string>  $contextRefs
     * @return array<string,array<int,array{ref:string,weight:float}>> context_ref → Ziele (z. B. Key Results), Gewicht 0..1
     */
    public function alignment(int $teamId, array $contextRefs): array;

    /**
     * Eigene Ziele der Subjekte (z. B. Objectives/Key Results), die im Zeitraum laufen oder endeten.
     *
     * @return array<int,array{employee_id:int, ref:string, title:?string, attainment:float, ends_at:?CarbonInterface, closed:bool, maintained?:bool}>
     *         attainment 0..1 (Zielerreichung)
     *         maintained (optional): das Ziel wird gepflegt — mindestens ein Teilziel wurde eingecheckt
     *         oder speist sich aus einem verfügbaren Messwert. Fehlt der Schlüssel, ist es unbekannt.
     */
    public function goals(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Im Zeitraum erreichte Meilensteine (Transformation Map) und was auf sie einzahlte.
     *
     * @return array<int,array{ref:string, title:?string, reached_at:CarbonInterface, contributors:array<int,array{ref:string,weight:float}>}>
     */
    public function milestones(int $teamId, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Aufgegriffenes: Objekte, die im Zeitraum entstanden und auf ihre Herkunft verweisen
     * (Beschluss, Key Result, Meilenstein, Aufgabe … aus einem Vorschlag).
     *
     * @return array<int,array{ref:string, title:?string, origin_ref:string, at:CarbonInterface}>
     */
    public function adoptions(int $teamId, CarbonInterface $from, CarbonInterface $to): array;
}
