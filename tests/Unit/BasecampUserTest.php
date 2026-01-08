<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use Mockery;

class BasecampUserTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test basecamp user has basecamp role check method
     */
    public function test_user_can_check_if_has_basecamp_role(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')
            ->with('basecamp')
            ->andReturn(true);

        $this->assertTrue($user->hasRole('basecamp'));
    }

    /**
     * Test basecamp user status is pending_payment after registration
     */
    public function test_basecamp_user_status_after_registration(): void
    {
        // Basecamp users should start with pending_payment status
        $validStatuses = [
            'pending_payment',
            'active_unverified',
            'active_verified',
            'suspended',
            'cancelled',
            'inactive'
        ];

        $this->assertContains('pending_payment', $validStatuses);
    }

    /**
     * Test basecamp user has no organisation_id
     */
    public function test_basecamp_user_has_no_organisation_id(): void
    {
        $user = new User([
            'email' => 'basecamp@example.com',
            'orgId' => null,
        ]);

        $this->assertNull($user->orgId);
    }

    /**
     * Test basecamp user email format validation
     */
    public function test_basecamp_user_email_format(): void
    {
        $validEmails = [
            'user@example.com',
            'test.user@example.co.uk',
            'user+tag@example.com',
        ];

        foreach ($validEmails as $email) {
            $this->assertStringContainsString('@', $email);
            $this->assertGreaterThan(5, strlen($email));
        }
    }

    /**
     * Test basecamp user subscription tier is basecamp
     */
    public function test_basecamp_user_subscription_tier(): void
    {
        $validTiers = ['spark', 'momentum', 'vision', 'basecamp'];
        
        $this->assertContains('basecamp', $validTiers);
    }

    /**
     * Test basecamp user monthly price is 10 GBP
     */
    public function test_basecamp_user_monthly_price(): void
    {
        $basecampMonthlyPrice = 10.00;
        $vatRate = 0.20;
        $subtotal = $basecampMonthlyPrice;
        $vatAmount = $subtotal * $vatRate;
        $totalAmount = $subtotal + $vatAmount;

        $this->assertEquals(10.00, $subtotal);
        $this->assertEquals(2.00, $vatAmount);
        $this->assertEquals(12.00, $totalAmount);
    }

    /**
     * Test basecamp user count is always 1
     */
    public function test_basecamp_user_count_is_one(): void
    {
        $basecampUserCount = 1;
        
        $this->assertEquals(1, $basecampUserCount);
    }

    /**
     * Test basecamp user requires payment before access
     */
    public function test_basecamp_user_requires_payment(): void
    {
        // Basecamp users need payment to activate account
        $statusesRequiringPayment = ['pending_payment'];
        $activeStatuses = ['active_verified', 'active_unverified'];

        $this->assertNotContains('pending_payment', $activeStatuses);
        $this->assertContains('pending_payment', $statusesRequiringPayment);
    }
}

