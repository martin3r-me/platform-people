<?php

namespace Platform\People\Services;

use Platform\People\Contracts\ContactDirectoryProvider;

/**
 * ContactDirectoryRegistry — hält den (optionalen) Personen-Verzeichnis-Provider (CRM).
 * Spiegelbild zu customer's CompanyDirectoryRegistry.
 */
class ContactDirectoryRegistry
{
    protected ?ContactDirectoryProvider $provider = null;

    public function register(ContactDirectoryProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function provider(): ?ContactDirectoryProvider
    {
        return $this->provider;
    }

    public function available(): bool
    {
        return $this->provider !== null;
    }
}
