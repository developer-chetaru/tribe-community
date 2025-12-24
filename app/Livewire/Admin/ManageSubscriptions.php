<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Subscription;
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
    public $user_count = 0;
    public $price_per_user = 0.00;
    public $total_amount = 0.00;
    public $status = 'active';
    public $start_date;
    public $end_date;
    public $next_billing_date;
    public $billing_cycle = 'monthly';
    public $notes;

    public $search = '';

    public function mount()
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function updatedPricePerUser()
    {
        // Default to $10 if empty
        if ($this->price_per_user <= 0) {
            $this->price_per_user = 10.00;
        }
        $this->calculateTotal();
    }

    public function updatedUserCount()
    {
        $this->calculateTotal();
    }

    public function updatedOrganisationId()
    {
        if ($this->organisation_id) {
            // Auto-calculate user count when organisation is selected
            $this->user_count = \App\Models\User::where('orgId', $this->organisation_id)
                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
                ->count();
            
            // Set default price per user to $10 if not set
            if ($this->price_per_user <= 0) {
                $this->price_per_user = 10.00;
            }
            
            $this->calculateTotal();
        }
    }

    public function calculateTotal()
    {
        $this->total_amount = $this->user_count * $this->price_per_user;
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
        $this->showEditModal = false;
        $this->start_date = now()->toDateString();
        $this->next_billing_date = now()->addMonth()->toDateString();
    }

    public function openCreateModalForOrganisation($organisationId)
    {
        $this->resetForm();
        $this->organisation_id = $organisationId;
        $this->price_per_user = 10.00; // Default $10 per user
        
        // Auto-calculate user count
        $this->user_count = \App\Models\User::where('orgId', $organisationId)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();
        
        $this->calculateTotal();
        
        $this->showCreateModal = true;
        $this->showEditModal = false;
        $this->start_date = now()->toDateString();
        $this->next_billing_date = now()->addMonth()->toDateString();
    }

    public function openEditModal($subscriptionId)
    {
        \Log::info('openEditModal called with ID: ' . $subscriptionId);
        $subscription = Subscription::findOrFail($subscriptionId);
        $this->selectedSubscription = $subscription;
        $this->organisation_id = $subscription->organisation_id;
        $this->user_count = $subscription->user_count;
        $this->price_per_user = $subscription->price_per_user;
        $this->total_amount = $subscription->total_amount;
        $this->status = $subscription->status;
        $this->start_date = $subscription->start_date->toDateString();
        $this->end_date = $subscription->end_date?->toDateString();
        $this->next_billing_date = $subscription->next_billing_date->toDateString();
        $this->billing_cycle = $subscription->billing_cycle;
        $this->notes = $subscription->notes;
        
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
        $this->user_count = 0;
        $this->price_per_user = 0.00;
        $this->total_amount = 0.00;
        $this->status = 'active';
        $this->start_date = null;
        $this->end_date = null;
        $this->next_billing_date = null;
        $this->billing_cycle = 'monthly';
        $this->notes = null;
    }

    public function createSubscription()
    {
        $this->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'start_date' => 'required|date',
            'next_billing_date' => 'required|date|after:start_date',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        // Get actual user count from organisation
        $actualUserCount = \App\Models\User::where('orgId', $this->organisation_id)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'basecamp'))
            ->count();

        // Default to $10 per user if not specified
        $pricePerUser = $this->price_per_user > 0 ? $this->price_per_user : 10.00;
        $totalAmount = $actualUserCount * $pricePerUser;

        Subscription::create([
            'organisation_id' => $this->organisation_id,
            'user_count' => $actualUserCount,
            'price_per_user' => $pricePerUser,
            'total_amount' => $totalAmount,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'next_billing_date' => $this->next_billing_date,
            'billing_cycle' => $this->billing_cycle,
            'notes' => $this->notes,
        ]);

        session()->flash('success', 'Subscription created successfully.');
        $this->closeModal();
    }

    public function updateSubscription()
    {
        $this->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'user_count' => 'required|integer|min:1',
            'price_per_user' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'next_billing_date' => 'required|date|after:start_date',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $this->calculateTotal();

        $this->selectedSubscription->update([
            'organisation_id' => $this->organisation_id,
            'user_count' => $this->user_count,
            'price_per_user' => $this->price_per_user,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'next_billing_date' => $this->next_billing_date,
            'billing_cycle' => $this->billing_cycle,
            'notes' => $this->notes,
        ]);

        session()->flash('success', 'Subscription updated successfully.');
        $this->closeModal();
    }

    public function pauseSubscription($subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $subscription->update(['status' => 'suspended']);
        session()->flash('success', 'Subscription paused successfully.');
    }

    public function resumeSubscription($subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $subscription->update(['status' => 'active']);
        session()->flash('success', 'Subscription resumed successfully.');
    }

    public function getSubscriptionsProperty()
    {
        // Get all organizations with their subscriptions
        $query = Organisation::with('subscription')
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
