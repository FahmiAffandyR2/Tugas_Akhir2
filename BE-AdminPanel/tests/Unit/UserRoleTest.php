<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_user_with_role_zero_is_admin(): void
    {
        $user = new User(['role' => 0]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_user_with_customer_role_is_not_admin(): void
    {
        $user = new User(['role' => 1]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_user_with_driver_role_is_not_admin(): void
    {
        $user = new User(['role' => 2]);

        $this->assertFalse($user->isAdmin());
    }
}
