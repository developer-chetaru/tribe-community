<?php

namespace Tests\Unit;

use App\Models\SubscriptionRecord;
use PHPUnit\Framework\TestCase;

class SubscriptionRecordTest extends TestCase
{
    /**
     * Test subscription isActive method returns true for active status
     */
    public function test_subscription_is_active_when_status_is_active(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'active']);
        
        $this->assertTrue($subscription->isActive());
    }

    /**
     * Test subscription isActive method returns false for inactive status
     */
    public function test_subscription_is_not_active_when_status_is_inactive(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'canceled']);
        
        $this->assertFalse($subscription->isActive());
    }

    /**
     * Test subscription isPastDue method returns true for past_due status
     */
    public function test_subscription_is_past_due_when_status_is_past_due(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'past_due']);
        
        $this->assertTrue($subscription->isPastDue());
    }

    /**
     * Test subscription isPastDue method returns false for other statuses
     */
    public function test_subscription_is_not_past_due_for_active_status(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'active']);
        
        $this->assertFalse($subscription->isPastDue());
    }

    /**
     * Test subscription isSuspended method returns true for suspended status
     */
    public function test_subscription_is_suspended_when_status_is_suspended(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'suspended']);
        
        $this->assertTrue($subscription->isSuspended());
    }

    /**
     * Test subscription isSuspended method returns false for other statuses
     */
    public function test_subscription_is_not_suspended_for_active_status(): void
    {
        $subscription = new SubscriptionRecord(['status' => 'active']);
        
        $this->assertFalse($subscription->isSuspended());
    }
}

