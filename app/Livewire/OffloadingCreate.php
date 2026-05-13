<?php

namespace App\Livewire;

use App\Mail\PostFeedbackNotificationMail;
use App\Models\IotFeedback;
use App\Models\IotTheme;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

class OffloadingCreate extends Component
{
    use WithFileUploads;

    public $message;
    public $image;
    public $SWOT = '';
    public $themeId = '';
    public $themes = [];
    public $imagePreview = null;
    public $alertType = '';
    public $alertMessage = '';

    public function mount()
    {
        $user = Auth::user();
        if ($user && $user->orgId) {
            $this->themes = IotTheme::where('orgId', $user->orgId)
                ->where('status', 'Active')
                ->orderBy('title')
                ->get();
        }
    }

    public function updatedImage()
    {
        if ($this->image) {
            $this->validate(['image' => 'image|max:2048']);
            $this->imagePreview = $this->image->temporaryUrl();
        }
    }

    public function removeImage()
    {
        $this->image = null;
        $this->imagePreview = null;
    }

    public function submit()
    {
        $this->validate([
            'message' => 'required|string|min:10|max:2000',
            'image' => 'nullable|image|max:2048',
            'SWOT' => 'nullable|string|max:255',
            'themeId' => 'nullable|exists:iot_themes,id',
        ], [
            'message.required' => 'Please enter your feedback message.',
            'message.min' => 'Message must be at least 10 characters.',
            'message.max' => 'Message cannot exceed 2000 characters.',
            'image.image' => 'File must be an image.',
            'image.max' => 'Image size cannot exceed 2MB.',
        ]);

        $user = Auth::user();

        if (!$user) {
            $this->alertType = 'error';
            $this->alertMessage = 'You must be logged in to submit feedback.';
            return;
        }

        if (!$user->orgId) {
            $this->alertType = 'error';
            $this->alertMessage = 'Your account is not associated with an organisation.';
            return;
        }

        try {
            $imageFilename = null;

            if ($this->image) {
                $filename = 'iotFeedback_' . time() . '.' . $this->image->getClientOriginalExtension();
                $path = public_path('uploads/iot_files');

                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }

                $tempPath = $this->image->getRealPath();
                $destinationPath = $path . '/' . $filename;

                if ($tempPath && file_exists($tempPath)) {
                    copy($tempPath, $destinationPath);
                    $imageFilename = $filename;
                } else {
                    $storedPath = $this->image->storeAs('iot_files', $filename, 'public');
                    if ($storedPath) {
                        $imageFilename = $filename;
                    }
                }
            }

            IotFeedback::create([
                'message' => $this->message,
                'image' => $imageFilename,
                'userId' => $user->id,
                'orgId' => $user->orgId,
                'SWOT' => $this->SWOT ?: null,
                'themeId' => $this->themeId ?: null,
                'feedbackStatus' => '1',
                'initialRiskScore' => 1,
                'mitigatedScore' => '1',
                'status' => 'Active',
            ]);

            $user->increment('EIScore', 100);

            try {
                $imageUrl = $imageFilename ? url('uploads/iot_files/' . $imageFilename) : null;
                Mail::to('offloads@tribe365.co')->send(new PostFeedbackNotificationMail($user, $this->message, $imageUrl));
            } catch (\Exception $e) {
                Log::error('Failed to send offloading email: ' . $e->getMessage());
            }

            $this->alertType = 'success';
            $this->alertMessage = 'Your feedback has been submitted successfully! You earned +100 EI Score points.';
            $this->reset(['message', 'image', 'SWOT', 'themeId', 'imagePreview']);
        } catch (\Exception $e) {
            $this->alertType = 'error';
            $this->alertMessage = 'An error occurred while submitting your feedback. Please try again.';
            Log::error('Offloading submission error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.offloading-create')
            ->layout('layouts.app', [
                'header' => 'Submit Offloading Feedback',
            ]);
    }
}

