<?php

namespace App\Models;

/**
 * Model para gerenciar eventos Stripe (idempotência de webhooks)
 */
class StripeEvent extends BaseModel
{
    protected string $table = 'stripe_events';

    /**
     * Verifica se evento já foi processado
     */
    public function isProcessed(string $eventId): bool
    {
        $event = $this->findBy('event_id', $eventId);
        return $event && $event['processed'] == 1;
    }

    /**
     * Marca evento como processado
     */
    public function markAsProcessed(string $eventId, string $eventType, array $data): int
    {
        $existing = $this->findBy('event_id', $eventId);

        $eventData = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'processed' => true,
            'data' => json_encode($data)
        ];

        if ($existing) {
            $this->update($existing['id'], $eventData);
            return $existing['id'];
        }

        return $this->insert($eventData);
    }

    /**
     * Registra evento (antes de processar)
     */
    public function register(string $eventId, string $eventType, array $data): int
    {
        $existing = $this->findBy('event_id', $eventId);

        if ($existing) {
            return $existing['id'];
        }

        return $this->insert([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'processed' => false,
            'data' => json_encode($data)
        ]);
    }
}

