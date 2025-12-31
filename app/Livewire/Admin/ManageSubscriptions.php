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

    public function getSubscriptionsProperty()
    {
        $combined = collect();
        
        // Get organisation subscriptions (only if tab is organisation)
        if ($this->activeTab === 'organisation') {
            $orgQuery = Organisation::with('subscriptionRecord')
                ->when($this->search, function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->orderBy('name', 'asc')
                ->get();
            
            // Add organisation subscriptions
            foreach ($orgQuery as $org) {
                $subscription = $org->subscriptionRecord;
                
                // Get latest invoice payment status
                $paymentStatus = 'unpaid';
                if ($subscription) {
                    $latestInvoice = Invoice::where('subscription_id', $subscription->id)
                        ->orWhere('organisation_id', $org->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($latestInvoice) {
                        $paymentStatus = $latestInvoice->status === 'paid' ? 'paid' : 'unpaid';
                    }
                }
                
                $combined->push([
                    'type' => 'organisation',
                    'id' => $org->id,
                    'name' => $org->name,
                    'subscription' => $subscription,
                    'user_count' => $org->users()->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))->count(),
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
                    $q->whereHas('user', function($userQuery) {
                        $userQuery->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Add basecamp user subscriptions
            foreach ($basecampSubscriptions as $subscription) {
                $user = $subscription->user;
                
                // Get latest invoice payment status
                $latestInvoice = Invoice::where('subscription_id', $subscription->id)
                    ->orWhere(function($q) use ($subscription) {
                        $q->where('user_id', $subscription->user_id)
                          ->where('tier', 'basecamp');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $paymentStatus = 'unpaid';
                if ($latestInvoice) {
                    $paymentStatus = $latestInvoice->status === 'paid' ? 'paid' : 'unpaid';
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
