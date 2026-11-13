<?php

namespace Platform\People\Tools\Legacy;

use Platform\People\Tools\CreateAgentCurationDraftTool;

/**
 * Alter Name aus der Zeit, als das Gedächtnis in organization lag — gelernte Routinen der Engrams können
 * ihn noch enthalten. Entfernen, sobald die Flotte nur noch people.agent_curations.POST nutzt.
 */
class OrganizationAgentCurationsAlias extends CreateAgentCurationDraftTool
{
    public function getName(): string
    {
        return 'organization.agent_curations.POST';
    }

    public function getDescription(): string
    {
        return 'VERALTET — heißt jetzt people.agent_curations.POST (gleiche Parameter). ' . parent::getDescription();
    }
}
