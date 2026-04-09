<?php

namespace App\Services\Billing;

use App\Models\Organisation;
use App\Models\SubscriptionRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice as StripeInvoice;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

/**
 * Fetches all Stripe invoices for one or more customer IDs (full pagination).
 * Used by admin Basecamp view and user Billing page for consistent history.
 */
class StripeInvoiceHistoryService
{
    /**
     * @param  list<string>  $customerIds
     * @return list<\stdClass> Display-ready rows with from_stripe = true
     */
    public function fetchInvoiceDisplayRows(array $customerIds): array
    {
        $customerIds = array_values(array_unique(array_filter($customerIds)));
        if ($customerIds === []) {
            return [];
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $seen = [];
        $rows = [];

        foreach ($customerIds as $customerId) {
            foreach ($this->fetchAllInvoicesForCustomer($customerId) as $inv) {
                if (isset($seen[$inv->id])) {
                    continue;
                }
                $seen[$inv->id] = true;
                $rows[] = $this->mapStripeInvoiceToDisplayRow($inv);
            }
        }

        usort($rows, function ($a, $b) {
            return ($b->sort_ts ?? 0) <=> ($a->sort_ts ?? 0);
        });

        foreach ($rows as $r) {
            unset($r->sort_ts);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function collectCustomerIdsForUser(\App\Models\User $user): array
    {
        if ($user->hasRole('basecamp')) {
            $ids = SubscriptionRecord::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stripe_customer_id')
                ->pluck('stripe_customer_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            $subscriptionRows = SubscriptionRecord::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stripe_subscription_id')
                ->get(['stripe_subscription_id']);
        } else {
            $ids = SubscriptionRecord::query()
                ->where('organisation_id', $user->orgId)
                ->whereNotNull('stripe_customer_id')
                ->pluck('stripe_customer_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            $subscriptionRows = $user->orgId
                ? SubscriptionRecord::query()
                    ->where('organisation_id', $user->orgId)
                    ->whereNotNull('stripe_subscription_id')
                    ->get(['stripe_subscription_id'])
                : new Collection;
        }

        if ($user->orgId) {
            $orgCid = Organisation::query()->whereKey($user->orgId)->value('stripe_customer_id');
            if ($orgCid && ! in_array($orgCid, $ids, true)) {
                $ids[] = $orgCid;
            }
        }

        $ids = $this->mergeCustomersFromStripeSubscriptions($ids, $subscriptionRows);
        $ids = $this->mergeCustomersFromStripeEmailSearch($user, $ids);

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Checkout often creates a new Stripe Customer per session (guest checkout). Those customers
     * share the user's email but are not stored on subscription rows — invoice history would be split.
     * Search finds every customer with this email so we can load all invoices/charges.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function mergeCustomersFromStripeEmailSearch(\App\Models\User $user, array $ids): array
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return $ids;
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            return $ids;
        }

        Stripe::setApiKey($secret);

        $escaped = str_replace("'", "\\'", $email);
        $query = "email:'{$escaped}'";

        try {
            $firstPage = StripeCustomer::search([
                'query' => $query,
                'limit' => 100,
            ]);
            foreach ($firstPage->autoPagingIterator() as $customer) {
                $cid = $customer->id ?? null;
                if (is_string($cid) && $cid !== '' && ! in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Stripe customer search by email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $ids;
    }

    /**
     * Resolve the canonical Stripe customer id from each subscription. The DB copy can lag webhooks
     * or miss older customers; Stripe is authoritative for which customer owns invoice history.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function mergeCustomersFromStripeSubscriptions(array $ids, Collection $subscriptionRows): array
    {
        if ($subscriptionRows->isEmpty()) {
            return $ids;
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            return $ids;
        }

        Stripe::setApiKey($secret);

        $seenSubscriptionIds = [];
        foreach ($subscriptionRows as $sr) {
            $sid = $sr->stripe_subscription_id ?? null;
            if (! is_string($sid) || $sid === '' || isset($seenSubscriptionIds[$sid])) {
                continue;
            }
            $seenSubscriptionIds[$sid] = true;

            try {
                $stripeSub = StripeSubscription::retrieve($sid);
                $cid = $this->stripeCustomerIdFromStripeObject($stripeSub->customer ?? null);
                if ($cid && ! in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                }
            } catch (\Throwable) {
                // Deleted subscription or invalid id — keep DB-derived ids only
            }
        }

        return $ids;
    }

    protected function stripeCustomerIdFromStripeObject(mixed $customer): ?string
    {
        if ($customer === null || $customer === '') {
            return null;
        }
        if (is_string($customer)) {
            return $customer;
        }
        if (is_object($customer) && isset($customer->id)) {
            return (string) $customer->id;
        }

        return null;
    }

    /**
     * @return list<\Stripe\Invoice>
     */
    protected function fetchAllInvoicesForCustomer(string $customerId): array
    {
        $all = [];
        $params = ['customer' => $customerId, 'limit' => 100];
        $firstPage = StripeInvoice::all($params);
        foreach ($firstPage->autoPagingIterator() as $inv) {
            $all[] = $inv;
        }

        return $all;
    }

    protected function mapStripeInvoiceToDisplayRow(\Stripe\Invoice $inv): \stdClass
    {
        $amountPaid = isset($inv->amount_paid) ? ((int) $inv->amount_paid) / 100 : 0.0;
        $currency = strtoupper((string) ($inv->currency ?? 'gbp'));
        $created = isset($inv->created) ? Carbon::createFromTimestamp((int) $inv->created) : null;

        $subtotal = $inv->subtotal !== null ? ((int) $inv->subtotal) / 100 : null;
        $taxAmount = 0.0;
        if (! empty($inv->total_tax_amounts) && is_iterable($inv->total_tax_amounts)) {
            foreach ($inv->total_tax_amounts as $t) {
                $amt = is_object($t) ? ($t->amount ?? 0) : 0;
                $taxAmount += ((int) $amt) / 100;
            }
        } elseif (isset($inv->tax) && $inv->tax !== null) {
            $taxAmount = ((int) $inv->tax) / 100;
        } elseif ($subtotal !== null && $amountPaid >= $subtotal) {
            $taxAmount = max(0, round($amountPaid - $subtotal, 2));
        }

        $status = $inv->status ?? 'open';
        if ($status === 'paid') {
            $displayStatus = 'paid';
        } elseif (in_array($status, ['open', 'draft'], true)) {
            $displayStatus = 'pending';
        } else {
            $displayStatus = $status;
        }

        $row = new \stdClass();
        $row->from_stripe = true;
        $row->stripe_invoice_id = $inv->id;
        $row->invoice_number = $inv->number ?? $inv->id;
        $row->invoice_date = $created;
        $row->total_amount = $amountPaid;
        $row->subtotal = $subtotal ?? ($amountPaid > 0 && $taxAmount > 0 ? $amountPaid - $taxAmount : $amountPaid);
        $row->tax_amount = $taxAmount;
        $row->status = $displayStatus;
        $row->hosted_invoice_url = $inv->hosted_invoice_url ?? null;
        $row->invoice_pdf = $inv->invoice_pdf ?? null;
        $row->currency = $currency;
        $row->sort_ts = (int) ($inv->created ?? 0);

        return $row;
    }
}
