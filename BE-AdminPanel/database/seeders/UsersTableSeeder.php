<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Traits\UserUtils;

class UsersTableSeeder extends Seeder
{
    use UserUtils;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create super admin account
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ezbus.com'],
            [
                'name' => 'SuperAdmin',
                'password' => Hash::make('admin123'),
                'role' => 0,
                'status_id' => 1,
                'uid' => "i5jvrJJgdyaoQaqhxgQNP1G1TLH2"
            ]
        );

        if ($adminUser->wasRecentlyCreated) {
            $this->storeAvatar($adminUser);
        }

        // Create driver account
        $driverUser = User::firstOrCreate(
            ['email' => 'driver@ezbus.com'],
            [
                'name' => 'Driver',
                'password' => Hash::make('driver123'),
                'role' => 2,
                'status_id' => 1,
                'uid' => "driver-local-001"
            ]
        );

        if ($driverUser->wasRecentlyCreated) {
            $this->storeAvatar($driverUser);
        }

        // Create customer account for the external portal
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@ezbus.com'],
            [
                'name' => 'Customer Demo',
                'password' => Hash::make('customer123'),
                'role' => 1,
                'status_id' => 1,
                'uid' => 'customer-local-001',
            ]
        );

        if ($customerUser->wasRecentlyCreated) {
            $this->storeAvatar($customerUser);
        }
    }
}
