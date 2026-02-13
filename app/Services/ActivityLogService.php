<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Available modules in the system
     */
    public static function getModules(): array
    {
        return [
            'user' => 'User',
            'organisation' => 'Organisation',
            'invoice' => 'Invoice',
            'payment' => 'Payment',
            'department' => 'Department',
            'office' => 'Office',
            'staff' => 'Staff',
            'reflection' => 'Reflection',
            'principle' => 'Principle',
            'learning_checklist' => 'Learning Checklist',
            'team_feedback' => 'Team Feedback',
            'subscription' => 'Subscription',
            'basecamp_user' => 'Basecamp User',
            'billing' => 'Billing',
            'notification' => 'Notification',
            'auth' => 'Authentication',
        ];
    }

    /**
     * Available actions
     */
    public static function getActions(): array
    {
        return [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'login' => 'Login',
            'logout' => 'Logout',
            'viewed' => 'Viewed',
            'exported' => 'Exported',
            'imported' => 'Imported',
            'activated' => 'Activated',
            'deactivated' => 'Deactivated',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
            'renewed' => 'Renewed',
        ];
    }

    /**
     * Log an activity
     *
     * @param string $module
     * @param string $action
     * @param string|null $description
     * @param mixed $subject
     * @param array|null $oldValues
     * @param array|null $newValues
     * @return ActivityLog
     */
    public static function log(
        string $module,
        string $action,
        ?string $description = null,
        $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        $user = Auth::user();

        $data = [
            'module' => $module,
            'action' => $action,
            'description' => $description ?? self::generateDescription($module, $action, $subject),
            'user_id' => $user?->id,
            'user_name' => $user ? ($user->first_name . ' ' . $user->last_name) : 'System',
            'user_email' => $user?->email,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        if ($subject) {
            if (is_object($subject)) {
                $data['subject_id'] = $subject->id ?? null;
                $data['subject_type'] = get_class($subject);
            } elseif (is_numeric($subject)) {
                $data['subject_id'] = $subject;
            }
        }

        if ($oldValues !== null) {
            $data['old_values'] = $oldValues;
        }

        if ($newValues !== null) {
            $data['new_values'] = $newValues;
        }

        return ActivityLog::create($data);
    }

    /**
     * Generate a description if not provided
     */
    protected static function generateDescription(string $module, string $action, $subject = null): string
    {
        $moduleName = self::getModules()[$module] ?? ucfirst($module);
        $actionName = self::getActions()[$action] ?? ucfirst($action);

        if ($subject && is_object($subject)) {
            $subjectName = method_exists($subject, 'getName') 
                ? $subject->getName() 
                : (method_exists($subject, 'name') 
                    ? $subject->name 
                    : ($subject->id ?? ''));
            
            return "{$actionName} {$moduleName}: {$subjectName}";
        }

        return "{$actionName} {$moduleName}";
    }

    /**
     * Log user creation
     */
    public static function logUserCreated($user, array $data = []): ActivityLog
    {
        return self::log(
            'user',
            'created',
            "Created user: {$user->first_name} {$user->last_name} ({$user->email})",
            $user,
            null,
            $data
        );
    }

    /**
     * Log user update
     */
    public static function logUserUpdated($user, array $oldValues, array $newValues): ActivityLog
    {
        return self::log(
            'user',
            'updated',
            "Updated user: {$user->first_name} {$user->last_name} ({$user->email})",
            $user,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log user login
     */
    public static function logLogin($user): ActivityLog
    {
        return self::log(
            'auth',
            'login',
            "User logged in: {$user->first_name} {$user->last_name} ({$user->email})",
            $user
        );
    }

    /**
     * Log user logout
     */
    public static function logLogout($user): ActivityLog
    {
        return self::log(
            'auth',
            'logout',
            "User logged out: {$user->first_name} {$user->last_name} ({$user->email})",
            $user
        );
    }

    /**
     * Log organisation creation
     */
    public static function logOrganisationCreated($organisation, array $data = []): ActivityLog
    {
        return self::log(
            'organisation',
            'created',
            "Created organisation: {$organisation->name}",
            $organisation,
            null,
            $data
        );
    }

    /**
     * Log organisation update
     */
    public static function logOrganisationUpdated($organisation, array $oldValues, array $newValues): ActivityLog
    {
        return self::log(
            'organisation',
            'updated',
            "Updated organisation: {$organisation->name}",
            $organisation,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log invoice creation
     */
    public static function logInvoiceCreated($invoice): ActivityLog
    {
        return self::log(
            'invoice',
            'created',
            "Created invoice #{$invoice->id} for £{$invoice->total_amount}",
            $invoice
        );
    }

    /**
     * Log payment
     */
    public static function logPayment($payment, $invoice): ActivityLog
    {
        return self::log(
            'payment',
            'paid',
            "Payment received: £{$payment->amount} for invoice #{$invoice->id}",
            $payment,
            null,
            ['invoice_id' => $invoice->id, 'amount' => $payment->amount]
        );
    }

    /**
     * Log subscription update
     */
    public static function logSubscriptionUpdated($subscription, array $oldValues, array $newValues): ActivityLog
    {
        $orgName = 'N/A';
        if ($subscription->organisation && isset($subscription->organisation->name)) {
            $orgName = $subscription->organisation->name;
        }
        
        return self::log(
            'subscription',
            'updated',
            "Updated subscription for organisation: {$orgName}",
            $subscription,
            $oldValues,
            $newValues
        );
    }
}
