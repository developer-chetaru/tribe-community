<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SubscriptionRecord;
use App\Models\Organisation;
use App\Models\Invoice;
use App\Models\Payment;
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
        $this->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'tier' => 'required|in:spark,momentum,vision,basecamp',
            'user_count' => 'required|integer|min:1',
            'current_period_start' => 'required|date',
            'current_period_end' => 'required|date|after:current_period_start',
            'next_billing_date' => 'required|date|after:current_period_start',
        ]);

        $this->selectedSubscription->update([
            'organisation_id' => $this->organisation_id,
            'tier' => $this->tier,
            'user_count' => $this->user_count,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'next_billing_date' => $this->next_billing_date,
        ]);

        // Update organisation tier
        Organisation::where('id', $this->organisation_id)->update([
            'subscription_tier' => $this->tier,
        ]);

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
        // Get all organizations with their subscription records
        $query = Organisation::with('subscriptionRecord')
            ->when($this->search, function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name', 'asc');
        
        return $query->paginate(15);
    }


    public function render()
    {
        return view('livewire.admin.manage-subscriptions', [
            'subscriptions' => $this->subscriptions,
            'organisations' => Organisation::orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
