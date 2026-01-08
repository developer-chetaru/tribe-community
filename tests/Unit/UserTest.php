<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Storage;

class UserTest extends TestCase
{
    /**
     * Test user name attribute when first_name and last_name are provided
     */
    public function test_user_name_attribute_with_first_and_last_name(): void
    {
        $user = new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('John Doe', $user->name);
    }

    /**
     * Test user name attribute when only first_name is provided
     */
    public function test_user_name_attribute_with_only_first_name(): void
    {
        $user = new User([
            'first_name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('John', $user->name);
    }

    /**
     * Test user name attribute when only last_name is provided
     */
    public function test_user_name_attribute_with_only_last_name(): void
    {
        $user = new User([
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('Doe', $user->name);
    }

    /**
     * Test user name attribute falls back to email when no name provided
     */
    public function test_user_name_attribute_falls_back_to_email(): void
    {
        $user = new User([
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('john@example.com', $user->name);
    }

    /**
     * Test user getJWTIdentifier returns user id
     */
    public function test_get_jwt_identifier_returns_user_id(): void
    {
        $user = new User();
        $user->id = 123;

        $this->assertEquals(123, $user->getJWTIdentifier());
    }

    /**
     * Test user getJWTCustomClaims returns empty array
     */
    public function test_get_jwt_custom_claims_returns_empty_array(): void
    {
        $user = new User();

        $this->assertEquals([], $user->getJWTCustomClaims());
    }
}

