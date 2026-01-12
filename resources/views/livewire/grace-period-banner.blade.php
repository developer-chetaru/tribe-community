<div wire:poll.30s="poll">
@if($showBanner && $subscription)
@php
    // Grace period banner only shows after 3 payment failures
    // Calculate suspension date from last payment failure date (7 days grace period)
    $lastPayment = \App\Models\PaymentRecord::where('subscription_id', $subscription->id)
        ->where('status', 'failed')
        ->latest()
        ->first();
    
    if ($lastPayment) {
        // Use last payment failure date + 7 days
        $failureDate = \Carbon\Carbon::parse($lastPayment->created_at);
        $suspensionDate = $failureDate->copy()->addDays(7);
    } else {
        // Fallback: should not happen if banner is showing, but set default
        $suspensionDate = now()->addDays(7);
    }
    
    $now = now();
    $totalSeconds = max(0, $now->diffInSeconds($suspensionDate, false));
@endphp
<div x-data="{ 
    daysRemaining: {{ $daysRemaining }},
    hoursRemaining: {{ floor(($totalSeconds % 86400) / 3600) }},
    minutesRemaining: {{ floor(($totalSeconds % 3600) / 60) }},
    secondsRemaining: {{ $totalSeconds % 60 }},
    showBanner: true,
    suspensionDate: new Date('{{ $suspensionDate->toIso8601String() }}'),
    init() {
        this.updateCountdown();
        setInterval(() => this.updateCountdown(), 1000); // Update every second
    },
    updateCountdown() {
        const now = new Date();
        const diffMs = this.suspensionDate - now;
        
        if (diffMs > 0) {
            const totalSeconds = Math.floor(diffMs / 1000);
            this.daysRemaining = Math.floor(totalSeconds / 86400);
            this.hoursRemaining = Math.floor((totalSeconds % 86400) / 3600);
            this.minutesRemaining = Math.floor((totalSeconds % 3600) / 60);
            this.secondsRemaining = totalSeconds % 60;
        } else {
            this.daysRemaining = 0;
            this.hoursRemaining = 0;
            this.minutesRemaining = 0;
            this.secondsRemaining = 0;
            this.showBanner = false;
            // Reload page to check if suspension occurred
            setTimeout(() => window.location.reload(), 2000);
        }
    }
}" 
x-show="showBanner"
x-transition:enter="transition ease-out duration-300"
x-transition:enter-start="opacity-0 transform -translate-y-2"
x-transition:enter-end="opacity-100 transform translate-y-0"
x-transition:leave="transition ease-in duration-200"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
class="relative z-40 bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center space-x-4 flex-1">
                <!-- Warning Icon -->
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <!-- Message -->
                <div class="flex-1">
                    <p class="font-semibold text-sm md:text-base">
                        ⚠️ Payment Failed - Grace Period Active
                    </p>
                    <p class="text-xs md:text-sm text-red-100 mt-1">
                        Your account will be suspended in 
                        <span class="font-bold text-white" x-text="daysRemaining + ' day' + (daysRemaining !== 1 ? 's' : '')"></span>
                        <span x-show="daysRemaining === 0 && hoursRemaining > 0" class="font-bold text-white" x-text="' and ' + hoursRemaining + ' hour' + (hoursRemaining !== 1 ? 's' : '')"></span>
                        if payment is not completed.
                    </p>
                </div>
            </div>
            
            <!-- Countdown Timer -->
            <div class="flex items-center space-x-4">
                <div class="bg-red-800 bg-opacity-50 rounded-lg px-4 py-2 text-center">
                    <div class="text-xs text-red-200">Time Remaining</div>
                    <div class="text-lg font-bold" x-text="String(daysRemaining).padStart(2, '0') + 'd ' + String(hoursRemaining).padStart(2, '0') + 'h ' + String(minutesRemaining).padStart(2, '0') + 'm'"></div>
                </div>
                
                <!-- Pay Now Button -->
                <button 
                    wire:click="goToBilling"
                    class="bg-white text-red-600 hover:bg-red-50 font-semibold px-6 py-2 rounded-lg transition-colors duration-200 shadow-md hover:shadow-lg flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <span>Pay Now</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endif
</div>
