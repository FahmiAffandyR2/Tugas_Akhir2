<?php

namespace Tests\Unit;

use App\Events\TripPositionUpdated;
use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;

class TripPositionUpdatedTest extends TestCase
{
    public function test_trip_position_updated_broadcasts_on_trip_channel(): void
    {
        $event = new TripPositionUpdated('trip-channel-10', '{"lat":-6.2,"lng":106.8,"speed":40}');

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('trip-channel-10', $channel->name);
    }

    public function test_trip_position_updated_broadcast_payload_contains_gps_data(): void
    {
        $payload = '{"lat":-6.2,"lng":106.8,"speed":40}';
        $event = new TripPositionUpdated('trip-channel-10', $payload);

        $this->assertSame([
            'data' => $payload,
        ], $event->broadcastWith());
    }
}
