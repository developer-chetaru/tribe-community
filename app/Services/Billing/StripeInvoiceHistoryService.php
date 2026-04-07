<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Stripe\Invoice as StripeInvoice;
use Stripe\Stripe;

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
            $ids = \App\Models\SubscriptionRecord::query()
                ->where('user_id', $user->id)
                ->whereNotNull('stripe_customer_id')
                ->pluck('stripe_customer_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if ($user->orgId) {
                $orgCid = \App\Models\Organisation::query()->whereKey($user->orgId)->value('stripe_customer_id');
                if ($orgCid && ! in_array($orgCid, $ids, true)) {
                    $ids[] = $orgCid;
                }
            }

            return array_values(array_unique($ids));
        }

        $ids = \App\Models\SubscriptionRecord::query()
            ->where('organisation_id', $user->orgId)
            ->whereNotNull('stripe_customer_id')
            ->pluck('stripe_customer_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($user->orgId) {
            $orgCid = \App\Models\Organisation::query()->whereKey($user->orgId)->value('stripe_customer_id');
            if ($orgCid && ! in_array($orgCid, $ids, true)) {
                $ids[] = $orgCid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<\Stripe\Invoice>
     */
    protected function fetchAllInvoicesForCustomer(string $customerId): array
    {
        $all = [];
        $params = ['customer' => $customerId, 'limit' => 100];

        while (true) {
            $page = StripeInvoice::all($params);
            foreach ($page->data as $inv) {
                $all[] = $inv;
            }
            if (! $page->has_more || empty($page->data)) {
                break;
            }
            $last = $page->data[count($page->data) - 1];
            $params['starting_after'] = $last->id;
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
