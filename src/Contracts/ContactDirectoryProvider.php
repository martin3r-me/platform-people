<?php

namespace Platform\People\Contracts;

/**
 * ContactDirectoryProvider — ein Personen-Verzeichnis (i.d.R. CRM), das people nutzt, um
 * einen Kontakt zu suchen/anzulegen und ihn am Personen-Knoten zu verknüpfen. Spiegelbild
 * zu customer's CompanyDirectoryProvider — nur für Personen (crm_contact) statt Firmen.
 */
interface ContactDirectoryProvider
{
    /** @return array<int,array{id:int,label:string,subtitle:?string}> */
    public function search(int $teamId, string $query): array;

    /** Neuen Kontakt anlegen (Name) → dessen id (context_id für den Alias 'crm_contact'), oder null. */
    public function createContact(int $teamId, string $name): ?int;
}
