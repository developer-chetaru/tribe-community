<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SubscriptionRecord;
use App\Models\Organisation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManageSubscriptions extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $showCreateModal = false;
    public $showEditModal = false;
    public $selectedSubscription = null;
    public $selectedOrganisation = null;

    // Form fields
    public $organisation_id;
    public $tier = 'spark';
    public $user_count = 0;
    public $status = 'active';
    public $current_period_start;
    public $current_period_end;
    public $next_billing_date;
    public $notes;

    public $search = '';
    public $activeTab = 'organisation'; // 'organisation', 'basecamp'

    public function mount()
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Validate and sanitize search input to prevent SQL injection
     */
    public function updatedSearch()
    {
        // Validate search input
        $this->validate(['search' => 'nullable|string|max:255']);
        
        // Escape special characters for LIKE queries to prevent SQL injection
        if ($this->search) {
            $this->search = addcslashes($this->search, '%_\\');
        }
    }

    public function updatedOrganisationId()
    {
        if ($this->organisation_id) {
            // Auto-calculate user count when organisation is selected
            $this->user_count = \App\Models\User::where('orgId', $this->organisation_id)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
            
            // Get organisation's tier if set
            $org = Organisation::find($this->organisation_id);
            if ($org && $org->subscription_tier) {
                $this->tier = $org->subscription_tier;
            }
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
        $this->showEditModal = false;
        $this->current_period_start = now()->toDateString();
        $this->current_period_end = now()->addMonth()->toDateString();
        $this->next_billing_date = now()->addMonth()->toDateString();
    }

    public function openCreateModalForOrganisation($organisationId)
    {
        $this->resetForm();
        $this->organisation_id = $organisationId;
        
        // Auto-calculate user count
        $this->user_count = \App\Models\User::where('orgId', $organisationId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();
        
        // Get organisation's tier if set
        $org = Organisation::find($organisationId);
        if ($org && $org->subscription_tier) {
            $this->tier = $org->subscription_tier;
        }
        
        $this->showCreateModal = true;
        $this->showEditModal = false;
        $this->current_period_start = now()->toDateString();
        $this->current_period_end = now()->addMonth()->toDateString();
        $this->next_billing_date = now()->addMonth()->toDateString();
    }

    public function openEditModal($subscriptionId)
    {
        \Log::info('openEditModal called with ID: ' . $subscriptionId);
        $subscription = SubscriptionRecord::findOrFail($subscriptionId);
        $this->selectedSubscription = $subscription;
        $this->organisation_id = $subscription->organisation_id;
        $this->tier = $subscription->tier;
        $this->user_count = $subscription->user_count;
        $this->status = $subscription->status;
        $this->current_period_start = $subscription->current_period_start?->toDateString();
        $this->current_period_end = $subscription->current_period_end?->toDateString();
        $this->next_billing_date = $subscription->next_billing_date?->toDateString();
        
        // Reset all modals first
        $this->showCreateModal = false;
        
        // Then set edit modal to true
        $this->showEditModal = true;
        
        \Log::info('Modal state - showEditModal: ' . ($this->showEditModal ? 'true' : 'false'));
        
        // Force a render
        $this->dispatch('modal-opened', ['type' => 'edit']);
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetForm();
        $this->selectedSubscription = null;
        $this->selectedOrganisation = null;
    }

    public function resetForm()
    {
        $this->organisation_id = null;
        $this->tier = 'spark';
        $this->user_count = 0;
        $this->status = 'active';
        $this->current_period_start = null;
        $this->current_period_end = null;
        $this->next_billing_date = null;
    }

    public function createSubscription()
    {
        $this->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'tier' => 'required|in:spark,momentum,vision,basecamp',
            'user_count' => 'required|integer|min:1',
            'current_period_start' => 'required|date',
            'current_period_end' => 'required|date|after:current_period_start',
            'next_billing_date' => 'required|date|after:current_period_start',
        ]);

        // Get actual user count from organisation
        $actualUserCount = \App\Models\User::where('orgId', $this->organisation_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        SubscriptionRecord::create([
            'organisation_id' => $this->organisation_id,
            'tier' => $this->tier,
            'user_count' => $actualUserCount > 0 ? $actualUserCount : $this->user_count,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'next_billing_date' => $this->next_billing_date,
            'activated_at' => now(),
        ]);

        // Update organisation tier
        Organisation::where('id', $this->organisation_id)->update([
            'subscription_tier' => $this->tier,
        ]);

        session()->flash('success', 'Subscription created successfully.');
        $this->closeModal();
    }

    public function updateSubscription()
    {
        // For basecamp tier, organisation_id is not required
        $rules = [
            'tier' => 'required|in:spark,momentum,vision,basecamp',
            'user_count' => 'required|integer|min:1',
            'current_period_start' => 'required|date',
            'current_period_end' => 'required|date|after:current_period_start',
            'next_billing_date' => 'required|date|after:current_period_start',
        ];
        
        if ($this->tier !== 'basecamp') {
            $rules['organisation_id'] = 'required|exists:organisations,id';
        }
        
        $this->validate($rules);

        // For basecamp, user_count is always 1
        $finalUserCount = $this->tier === 'basecamp' ? 1 : $this->user_count;
        
        $updateData = [
            'tier' => $this->tier,
            'user_count' => $finalUserCount,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'next_billing_date' => $this->next_billing_date,
        ];
        
        // Only update organisation_id if it's not a basecamp subscription
        if ($this->tier !== 'basecamp' && $this->organisation_id) {
            $updateData['organisation_id'] = $this->organisation_id;
        }

        $this->selectedSubscription->update($updateData);

        // Update organisation tier only if it's not basecamp
        if ($this->tier !== 'basecamp' && $this->organisation_id) {
            Organisation::where('id', $this->organisation_id)->update([
                'subscription_tier' => $this->tier,
            ]);
        }

        session()->flash('success', 'Subscription updated successfully.');
        $this->closeModal();
    }

    public function pauseSubscription($subscriptionId)
    {
        $subscription = SubscriptionRecord::findOrFail($subscriptionId);
        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
        session()->flash('success', 'Subscription paused successfully.');
    }

    public function resumeSubscription($subscriptionId)
    {
        $subscription = SubscriptionRecord::findOrFail($subscriptionId);
        $subscription->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);
        session()->flash('success', 'Subscription resumed successfully.');
    }

    public function deleteUnpaidSubscription($subscriptionId)
    {
        try {
            $subscription = SubscriptionRecord::findOrFail($subscriptionId);
            
            // Check if subscription has any paid invoices
            $hasPaidInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('status', 'paid')
                ->exists();
            
            if ($hasPaidInvoice) {
                session()->flash('error', 'Cannot delete subscription with paid invoices. Please contact support.');
                return;
            }
            
            // Delete unpaid invoices
            Invoice::where('subscription_id', $subscription->id)
                ->where('status', '!=', 'paid')
                ->delete();
            
            // Delete payments related to this subscription
            $invoiceIds = Invoice::where('subscription_id', $subscription->id)->pluck('id');
            Payment::whereIn('invoice_id', $invoiceIds)->delete();
            
            // For basecamp users, also delete user-level invoices
            if ($subscription->tier === 'basecamp' && $subscription->user_id) {
                Invoice::where('user_id', $subscription->user_id)
                    ->where('tier', 'basecamp')
                    ->where('status', '!=', 'paid')
                    ->delete();
            }
            
            // Delete the subscription
            $subscription->delete();
            
            session()->flash('success', 'Unpaid subscription deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Error deleting unpaid subscription: ' . $e->getMessage());
            session()->flash('error', 'Failed to delete subscription: ' . $e->getMessage());
        }
    }

    public function getSubscriptionsProperty()
    {
        $combined = collect();
        
        // Get organisation subscriptions (only if tab is organisation)
        if ($this->activeTab === 'organisation') {
            // Fix N+1: Eager load user count and subscription
            $orgQuery = Organisation::with('subscriptionRecord')
                ->withCount(['users' => function($q) {
                    $q->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'));
                }])
                ->when($this->search, function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->orderBy('name', 'asc')
                ->get();
            
            // Get all subscription IDs and organisation IDs for batch invoice query
            $subscriptionIds = $orgQuery->pluck('subscriptionRecord.id')->filter()->toArray();
            $organisationIds = $orgQuery->pluck('id')->toArray();
            
            // Fix N+1: Optimized batch fetch - get only latest invoice per subscription/organisation
            // Order by created_at desc, then group to get latest per subscription/organisation
            $latestInvoices = Invoice::where(function($q) use ($subscriptionIds, $organisationIds) {
                if (!empty($subscriptionIds)) {
                    $q->whereIn('subscription_id', $subscriptionIds);
                }
                if (!empty($organisationIds)) {
                    if (!empty($subscriptionIds)) {
                        $q->orWhereIn('organisation_id', $organisationIds);
                    } else {
                        $q->whereIn('organisation_id', $organisationIds);
                    }
                }
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function($invoice) {
                // Group by subscription_id first, then fallback to organisation_id
                return $invoice->subscription_id ?? 'org_' . $invoice->organisation_id;
            })
            ->map(function($invoices) {
                return $invoices->first(); // Get the latest invoice for each group
            });
            
            // Create a map for quick lookup: subscription_id => invoice, organisation_id => invoice
            $invoiceMap = [];
            foreach ($latestInvoices as $invoice) {
                if ($invoice->subscription_id) {
                    $invoiceMap['sub_' . $invoice->subscription_id] = $invoice;
                }
                if ($invoice->organisation_id) {
                    $invoiceMap['org_' . $invoice->organisation_id] = $invoice;
                }
            }
            
            // Add organisation subscriptions
            foreach ($orgQuery as $org) {
                $subscription = $org->subscriptionRecord;
                
                // Get latest invoice payment status from map
                $paymentStatus = 'unpaid';
                if ($subscription) {
                    $invoice = $invoiceMap['sub_' . $subscription->id] ?? $invoiceMap['org_' . $org->id] ?? null;
                    if ($invoice) {
                        $paymentStatus = $invoice->status === 'paid' ? 'paid' : 'unpaid';
                    }
                } else {
                    // Check organisation-level invoice if no subscription
                    $invoice = $invoiceMap['org_' . $org->id] ?? null;
                    if ($invoice) {
                        $paymentStatus = $invoice->status === 'paid' ? 'paid' : 'unpaid';
                    }
                }
                
                $combined->push([
                    'type' => 'organisation',
                    'id' => $org->id,
                    'name' => $org->name,
                    'subscription' => $subscription,
                    'user_count' => $org->users_count ?? 0, // Use eager-loaded count
                    'payment_status' => $paymentStatus,
                ]);
            }
        }
        
        // Get basecamp user subscriptions (only if tab is basecamp)
        if ($this->activeTab === 'basecamp') {
            $basecampSubscriptions = SubscriptionRecord::where('tier', 'basecamp')
                ->whereNotNull('user_id')
                ->with('user')
                ->when($this->search, function($q) {
                    // Search is already sanitized in updatedSearch() method
                    $q->whereHas('user', function($userQuery) {
                        $userQuery->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Fix N+1: Batch fetch latest invoices for all basecamp subscriptions
            $subscriptionIds = $basecampSubscriptions->pluck('id')->toArray();
            $userIds = $basecampSubscriptions->pluck('user_id')->filter()->toArray();
            
            // Initialize invoice map
            $invoiceMap = [];
            
            // Only query if we have IDs to search for
            if (!empty($subscriptionIds) || !empty($userIds)) {
                $latestInvoices = Invoice::where(function($q) use ($subscriptionIds, $userIds) {
                    if (!empty($subscriptionIds)) {
                        $q->whereIn('subscription_id', $subscriptionIds);
                    }
                    if (!empty($userIds)) {
                        if (!empty($subscriptionIds)) {
                            $q->orWhere(function($q2) use ($userIds) {
                                $q2->whereIn('user_id', $userIds)
                                   ->where('tier', 'basecamp');
                            });
                        } else {
                            $q->where(function($q2) use ($userIds) {
                                $q2->whereIn('user_id', $userIds)
                                   ->where('tier', 'basecamp');
                            });
                        }
                    }
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function($invoice) {
                    // Group by subscription_id first, then user_id
                    return $invoice->subscription_id ?? 'user_' . $invoice->user_id;
                })
                ->map(function($invoices) {
                    return $invoices->first(); // Get the latest invoice for each group
                });
                
                // Create a map for quick lookup
                foreach ($latestInvoices as $invoice) {
                    if ($invoice->subscription_id) {
                        $invoiceMap['sub_' . $invoice->subscription_id] = $invoice;
                    }
                    if ($invoice->user_id) {
                        $invoiceMap['user_' . $invoice->user_id] = $invoice;
                    }
                }
            }
            
            // Add basecamp user subscriptions
            foreach ($basecampSubscriptions as $subscription) {
                $user = $subscription->user;
                
                // Get latest invoice payment status from map
                $invoice = $invoiceMap['sub_' . $subscription->id] ?? $invoiceMap['user_' . $subscription->user_id] ?? null;
                $paymentStatus = 'unpaid';
                if ($invoice) {
                    $paymentStatus = $invoice->status === 'paid' ? 'paid' : 'unpaid';
                }
                
                $combined->push([
                    'type' => 'basecamp',
                    'id' => $subscription->id,
                    'name' => $user ? ($user->first_name . ' ' . $user->last_name . ' (' . $user->email . ')') : 'Unknown User',
                    'subscription' => $subscription,
                    'user' => $user,
                    'user_count' => 1, // Basecamp is always single user
                    'payment_status' => $paymentStatus,
                ]);
            }
        }
        
        // Sort by name
        $sorted = $combined->sortBy('name')->values();
        
        // Manual pagination
        $perPage = 15;
        $currentPage = $this->getPage();
        $items = $sorted->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $total = $sorted->count();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
    
    public function getPage()
    {
        return request()->get('page', 1);
    }


    public function render()
    {
        return view('livewire.admin.manage-subscriptions', [
            'subscriptions' => $this->subscriptions,
            'organisations' => Organisation::orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
