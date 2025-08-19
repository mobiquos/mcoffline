<?php

namespace App\Message;

final class SyncClients
{
    public function __construct(
        private readonly int $syncEventId
    ) {
    }

    public function getSyncEventId(): int
    {
        return $this->syncEventId;
    }
}
