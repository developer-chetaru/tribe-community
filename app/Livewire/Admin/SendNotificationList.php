<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SendNotification;

class SendNotificationList extends Component
{
    use WithPagination;

    public $searchInput = '';
    public $search = '';
    public $sortBy = 'new';
    public $confirmingDelete = false;
    public $deleteId = null;

    protected $paginationTheme = 'tailwind';
    protected $listeners = ['closeModal' => 'closeDeleteModal'];

    public function updatedSearchInput()
    {
        // Do NOT search when typing — only when clicking Search
        $this->resetPage();
    }

    public function runSearch()
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function clearSearch()
    {
        $this->searchInput = '';
        $this->search = '';
        $this->resetPage();
    }

    public function updatedSortBy()
    {
        $this->resetPage();
    }

    public function confirmDelete($id)
    {
        $this->confirmingDelete = true;
        $this->deleteId = $id;
    }

    public function closeDeleteModal()
    {
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function deleteNotification()
    {
        if ($this->deleteId) {
            SendNotification::find($this->deleteId)?->delete();
            session()->flash('success', 'Notification deleted successfully!');
        }
        $this->confirmingDelete = false;
    }

    public function render()
    {
        $sortDirection = $this->sortBy === 'new' ? 'desc' : 'asc';
        $term = '%' . $this->searchInput . '%';

        $notifications = SendNotification::query()
            ->with(['organisation', 'office', 'allDepartment'])
            ->when($this->searchInput !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('organisation', fn($x) => $x->where('name', 'like', $term))
                    ->orWhereHas('office', fn($x) => $x->where('name', 'like', $term))
                    ->orWhereHas('allDepartment', fn($x) => $x->where('name', 'like', $term));
                });
            })
            ->orderBy('created_at', $sortDirection)
            ->paginate(10);

        return view('livewire.admin.send-notification-list', [
            'notifications' => $notifications,
        ])->layout('layouts.app');
    }
}

