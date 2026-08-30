<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GpsAlertCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The trip ID.
     */
    public int $tripId;

    /**
     * The alert message.
     */
    public string $message;

    /**
     * The alert type.
     */
    public string $alertType;

    /**
     * Additional metadata.
     */
    public array $metadata;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(int $tripId, string $message, string $alertType, array $metadata = [])
    {
        $this->tripId = $tripId;
        $this->message = $message;
        $this->alertType = $alertType;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('gps-alerts');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'GpsAlertCreated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'tripId' => $this->tripId,
            'message' => $this->message,
            'alertType' => $this->alertType,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
