<?php

namespace Platform\People\Tools\Legacy;

use Platform\People\Tools\ListAgentRulesTool;

/**
 * Alter Name aus der Zeit, als das Gedächtnis in organization lag — gelernte Routinen der Engrams können
 * ihn noch enthalten. Entfernen, sobald die Flotte nur noch people.agent_rules.GET nutzt.
 */
class OrganizationAgentRulesAlias extends ListAgentRulesTool
{
    public function getName(): string
    {
        return 'organization.agent_rules.GET';
    }

    public function getDescription(): string
    {
        return 'VERALTET — heißt jetzt people.agent_rules.GET (gleiche Parameter). ' . parent::getDescription();
    }
}
