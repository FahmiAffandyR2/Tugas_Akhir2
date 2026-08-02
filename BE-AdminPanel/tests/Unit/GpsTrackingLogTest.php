<?php

namespace Tests\Unit;

use App\Models\GpsTrackingLog;
use PHPUnit\Framework\TestCase;

class GpsTrackingLogTest extends TestCase
{
    public function test_gps_tracking_log_allows_expected_fields_to_be_filled(): void
    {
        $model = new GpsTrackingLog();

        $this->assertSame([
            'driver_id',
            'bus_id',
            'planned_trip_id',
            'latitude',
            'longitude',
            'speed',
            'heading',
            'accuracy',
            'recorded_at',
        ], $model->getFillable());
    }

    public function test_gps_tracking_log_casts_coordinate_and_time_fields(): void
    {
        $model = new GpsTrackingLog();

        $this->assertSame('double', $model->getCasts()['latitude']);
        $this->assertSame('double', $model->getCasts()['longitude']);
        $this->assertSame('double', $model->getCasts()['speed']);
        $this->assertSame('double', $model->getCasts()['heading']);
        $this->assertSame('double', $model->getCasts()['accuracy']);
        $this->assertSame('datetime', $model->getCasts()['recorded_at']);
    }
}
