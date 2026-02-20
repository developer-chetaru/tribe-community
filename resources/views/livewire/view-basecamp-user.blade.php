<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="text-[14px] sm:text-[24px] font-medium tracking-tight capitalize text-[#EB1C24]">
            {{ $user->first_name }} {{ $user->last_name }} Details
        </h2>
        <a href="{{ route('basecampuser') }}" 
           class="text-gray-600 hover:text-gray-900 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </a>
    </div>
</x-slot>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-xl p-8">
        {{-- Profile Photo --}}
        <div class="flex justify-center mb-6">
            <div class="w-32 h-32 rounded-full bg-red-50 flex items-center justify-center 
                         text-2xl font-bold text-[#ff2323] overflow-hidden shadow-md ring-4 ring-red-100">
                @if ($user->profile_photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo_path))
                    <img src="{{ asset('storage/' . $user->profile_photo_path) }}"
                         class="w-full h-full object-cover rounded-full"
                         alt="{{ $user->first_name }} {{ $user->last_name }}">
                @else
                    @php
                        $first = strtoupper(substr($user->first_name ?? '', 0, 1));
                        $last  = strtoupper(substr($user->last_name ?? '', 0, 1));
                    @endphp
                    <span>{{ $first }}{{ $last }}</span>
                @endif
            </div>
        </div>

        {{-- User Information --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="text-sm font-semibold text-gray-600">Name</label>
                <p class="text-gray-900 font-medium text-lg">{{ $user->first_name }} {{ $user->last_name }}</p>
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-600">Email</label>
                <p class="text-gray-900 text-lg">{{ $user->email }}</p>
            </div>
            @if($user->phone)
            <div>
                <label class="text-sm font-semibold text-gray-600">Phone</label>
                <p class="text-gray-900 text-lg">{{ $user->country_code ?? '' }}{{ $user->phone }}</p>
            </div>
            @endif
            @if($user->timezone)
            <div>
                <label class="text-sm font-semibold text-gray-600">Timezone</label>
                <p class="text-gray-900 text-lg">{{ $user->timezone }}</p>
            </div>
            @endif
            
            {{-- Account Status --}}
            <div>
                <label class="text-sm font-semibold text-gray-600">Account Status</label>
                <div class="mt-1">
                    @php
                        $accountStatus = $user->status;
                        $accountStatusText = '';
                        $accountStatusClass = '';
                        
                        // Determine account status
                        if (in_array($accountStatus, ['active_verified', 'active_unverified', 'pending_payment', 'suspended', 'cancelled', 'inactive'])) {
                            switch($accountStatus) {
                                case 'active_verified':
                                case 'active_unverified':
                                    $accountStatusText = 'Active ✓';
                                    $accountStatusClass = 'bg-green-100 text-green-800 border border-green-300';
                                    break;
                                case 'pending_payment':
                                    $accountStatusText = 'Pending Payment 💳';
                                    $accountStatusClass = 'bg-yellow-100 text-yellow-800 border border-yellow-300';
                                    break;
                                case 'suspended':
                                    $accountStatusText = 'Suspended 🚫';
                                    $accountStatusClass = 'bg-red-100 text-red-800 border border-red-300';
                                    break;
                                case 'cancelled':
                                    $accountStatusText = 'Cancelled ❌';
                                    $accountStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                                    break;
                                case 'inactive':
                                    $accountStatusText = 'Inactive ⏸';
                                    $accountStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                                    break;
                                default:
                                    $accountStatusText = 'Unknown';
                                    $accountStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                            }
                        } elseif ($accountStatus === true || $accountStatus === '1' || $accountStatus === 1) {
                            $accountStatusText = 'Active (Legacy) ✓';
                            $accountStatusClass = 'bg-blue-100 text-blue-800 border border-blue-300';
                        } elseif ($accountStatus === false || $accountStatus === '0' || $accountStatus === 0 || $accountStatus === null) {
                            $accountStatusText = 'Inactive ⏸';
                            $accountStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                        } else {
                            $accountStatusText = 'Not Set';
                            $accountStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                        }
                    @endphp
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $accountStatusClass }} inline-block">
                        {{ $accountStatusText }}
                    </span>
                </div>
            </div>

            {{-- Email Verification Status --}}
            <div>
                <label class="text-sm font-semibold text-gray-600">Email Status</label>
                <div class="mt-1">
                    @php
                        $emailVerified = $user->email_verified_at !== null;
                        $emailStatusText = $emailVerified ? 'Verified ✓' : 'Not Verified ⚠';
                        $emailStatusClass = $emailVerified 
                            ? 'bg-green-100 text-green-800 border border-green-300' 
                            : 'bg-orange-100 text-orange-800 border border-orange-300';
                    @endphp
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $emailStatusClass }} inline-block">
                        {{ $emailStatusText }}
                    </span>
                    @if($emailVerified && $user->email_verified_at)
                        <p class="text-xs text-gray-500 mt-1">Verified on {{ $user->email_verified_at->format('M d, Y') }}</p>
                    @else
                        <p class="text-xs text-gray-500 mt-1">Email verification pending</p>
                    @endif
                </div>
            </div>

            {{-- Payment/Subscription Status --}}
            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-gray-600">Payment Status</label>
                <div class="mt-1">
                    @php
                        $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->first();
                        
                        if ($subscription) {
                            $paymentStatus = $subscription->status;
                            switch($paymentStatus) {
                                case 'active':
                                    $paymentStatusText = 'Active ✓';
                                    $paymentStatusClass = 'bg-green-100 text-green-800 border border-green-300';
                                    $paymentDescription = 'Subscription is active';
                                    if ($subscription->current_period_end) {
                                        $paymentDescription .= ' until ' . $subscription->current_period_end->format('M d, Y');
                                    }
                                    break;
                                case 'past_due':
                                    $paymentStatusText = 'Past Due ⚠';
                                    $paymentStatusClass = 'bg-yellow-100 text-yellow-800 border border-yellow-300';
                                    $paymentDescription = 'Payment is overdue';
                                    break;
                                case 'suspended':
                                    $paymentStatusText = 'Suspended 🚫';
                                    $paymentStatusClass = 'bg-red-100 text-red-800 border border-red-300';
                                    $paymentDescription = 'Subscription is suspended';
                                    break;
                                case 'cancelled':
                                    $paymentStatusText = 'Cancelled ❌';
                                    $paymentStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                                    $paymentDescription = 'Subscription has been cancelled';
                                    break;
                                default:
                                    $paymentStatusText = ucfirst(str_replace('_', ' ', $paymentStatus));
                                    $paymentStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                                    $paymentDescription = 'Subscription status: ' . $paymentStatus;
                            }
                        } else {
                            $paymentStatusText = 'No Subscription';
                            $paymentStatusClass = 'bg-gray-100 text-gray-800 border border-gray-300';
                            $paymentDescription = 'No subscription record found';
                        }
                    @endphp
                    <span class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $paymentStatusClass }} inline-block">
                        {{ $paymentStatusText }}
                    </span>
                    <p class="text-xs text-gray-500 mt-1">{{ $paymentDescription ?? '' }}</p>
                </div>
            </div>
            @if($user->created_at)
            <div>
                <label class="text-sm font-semibold text-gray-600">Joined</label>
                <p class="text-gray-900 text-lg">{{ $user->created_at->format('M d, Y') }}</p>
            </div>
            @endif
        </div>
        {{-- Engagement Details --}}
            @if(isset($hptmData['engagement']))
            <div class="mt-8">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">Engagement Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {{-- Total Engagement Score (Engagement Index) --}}
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 border border-blue-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700">Total Engagement</span>
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <p class="text-xl font-bold text-blue-900">{{ number_format($hptmData['engagement']['totalScore'] ?? 0, 2) }}</p>
                        <p class="text-xs text-gray-600">Engagement Index</p>
                        <div class="mt-1.5 pt-1.5 border-t border-blue-200">
                            <p class="text-xs text-gray-600 leading-tight">
                                <strong>Formula:</strong> (EI Score % / 100 × 200) + HPTM Score<br>
                                <span class="text-gray-500">
                                    (<span class="font-bold text-green-700">{{ number_format($hptmData['engagement']['eiScore'] ?? 0, 2) }}</span>% / 100 × 200) + 
                                    <span class="font-bold text-purple-700">{{ number_format($hptmData['engagement']['hptmScore'] ?? 0, 2) }}</span> = 
                                    <span class="font-bold text-blue-900 text-sm">{{ number_format($hptmData['engagement']['totalScore'] ?? 0, 2) }}</span>
                                </span>
                                <div class="mt-1 bg-blue-50 rounded p-1 text-xs">
                                    <p class="mb-0 text-gray-600">
                                        <strong>Example:</strong> (<span class="font-bold text-green-700">50</span>% × 2) + <span class="font-bold text-purple-700">230</span> = <span class="font-bold text-blue-900">330</span>
                                    </p>
                                </div>
                            </p>
                        </div>
                    </div>

                    {{-- EI Score --}}
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-3 border border-green-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700">EI Score</span>
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-xl font-bold text-green-900">{{ number_format($hptmData['engagement']['eiScore'] ?? 0, 2) }}%</p>
                        <p class="text-xs text-gray-600">Emotional Intelligence</p>
                        <div class="mt-1.5 pt-1.5 border-t border-green-200">
                            <p class="text-xs text-gray-600 leading-tight">
                                <strong>Formula:</strong> (Sentiment Submissions / Working Days) × 100<br>
                                <span class="text-gray-500">
                                    Current: <span class="font-bold text-green-700">{{ $hptmData['engagement']['eiScoreSubmissions'] ?? 0 }}</span> submission(s) / 
                                    <span class="font-bold text-green-700">{{ $hptmData['engagement']['eiScoreWorkingDays'] ?? 0 }}</span> working days = 
                                    <span class="font-bold text-green-700">{{ number_format($hptmData['engagement']['eiScore'] ?? 0, 2) }}</span>%
                                </span>
                                <div class="mt-1 bg-green-50 rounded p-1 text-xs">
                                    <p class="mb-0 text-gray-600">
                                        <strong>Example:</strong> <span class="font-bold text-green-700">5</span> submissions / <span class="font-bold text-green-700">10</span> working days = <span class="font-bold text-green-700">50</span>%
                                    </p>
                                </div>
                            </p>
                        </div>
                    </div>

                    {{-- HPTM Score --}}
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 border border-purple-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700">HPTM Score</span>
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <p class="text-xl font-bold text-purple-900">{{ number_format($hptmData['engagement']['hptmScore'] ?? 0, 2) }}</p>
                        <p class="text-xs text-gray-600">High Performance Team</p>
                        <div class="mt-1.5 pt-1.5 border-t border-purple-200">
                            <p class="text-xs text-gray-600 leading-tight">
                                <strong>Formula:</strong><br>
                                <span class="text-gray-500">Raw Score = Learning Score + Evaluation Score<br>
                                Current: {{ number_format($hptmData['totalRawScore'] ?? 0, 0) }} points</span>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
            @endif
        {{-- HPTM Details Section --}}
        <div class="mt-8 pt-8 border-t border-gray-200">
            <h3 class="text-xl font-bold text-[#EB1C24] mb-6">HPTM Details</h3>
            
            {{-- HPTM Score (Real/Raw Score - Auto-refresh) --}}
            <div class="mb-8" wire:poll.5s="loadHptmData">
                <div class="bg-white rounded-lg p-6 border border-gray-200 max-w-md mx-auto">
                    <label class="text-sm font-semibold text-gray-600 block mb-2">HPTM Score</label>
                    <p class="text-gray-900 font-bold text-3xl text-center" wire:key="admin-score-{{ $hptmData['totalRawScore'] ?? 0 }}">
                        {{ $hptmData['totalRawScore'] ?? 0 }}
                    </p>
                </div>
            </div>

            {{-- Principles Progress with Checked Items (Sorted & Compact) --}}
            @if(isset($hptmData['principles']) && count($hptmData['principles']) > 0)
            <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-base font-semibold text-gray-800">Principles Progress</h4>
                    <span class="text-xs text-gray-500">Sorted by completion</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($hptmData['principles'] as $principle)
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1 min-w-0">
                                <h5 class="font-semibold text-gray-900 text-sm">{{ $principle['title'] }}</h5>
                                @if($principle['description'])
                                    <p class="text-xs text-gray-600 mt-0.5">{{ Str::limit($principle['description'], 50) }}</p>
                                @endif
                            </div>
                            <div class="flex flex-col items-end ml-2 flex-shrink-0">
                                <span class="text-xs font-medium text-gray-700 bg-white px-2 py-0.5 rounded mb-1">
                                    {{ $principle['readChecklists'] }}/{{ $principle['totalChecklists'] }}
                                </span>
                                <span class="text-xs font-bold {{ $principle['completionPercent'] == 100 ? 'text-green-600' : ($principle['completionPercent'] >= 50 ? 'text-blue-600' : 'text-orange-600') }}">
                                    {{ $principle['completionPercent'] }}%
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 mb-2">
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full transition-all {{ $principle['completionPercent'] == 100 ? 'bg-green-500' : ($principle['completionPercent'] >= 50 ? 'bg-blue-500' : 'bg-orange-500') }}" 
                                     style="width: {{ min(100, $principle['completionPercent']) }}%"></div>
                            </div>
                        </div>
                        
                        {{-- Show Checked Items (Sorted Alphabetically) --}}
                        @if(isset($principle['checkedItems']) && count($principle['checkedItems']) > 0)
                        <div class="mt-2 pt-2 border-t border-gray-300">
                            <h6 class="text-xs font-semibold text-gray-700 mb-1">✓ {{ count($principle['checkedItems']) }} checked</h6>
                            <div class="space-y-0.5 max-h-32 overflow-y-auto">
                                @foreach($principle['checkedItems'] as $item)
                                <div class="bg-white rounded px-1.5 py-0.5 border-l-2 border-green-500 flex items-center justify-between text-xs">
                                    <div class="flex items-center flex-1 min-w-0">
                                        <span class="text-xs font-medium text-gray-900 truncate">{{ $item['title'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-0.5 ml-1 flex-shrink-0">
                                        <span class="text-xs bg-blue-100 text-blue-800 px-1 py-0.5 rounded">{{ Str::limit($item['learningType'], 8) }}</span>
                                        @if($item['hasLink'])
                                            <span class="text-xs bg-green-100 text-green-800 px-1 py-0.5 rounded" title="Video">V</span>
                                        @endif
                                        @if($item['hasDocument'])
                                            <span class="text-xs bg-purple-100 text-purple-800 px-1 py-0.5 rounded" title="PDF">P</span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @elseif($principle['totalChecklists'] > 0)
                        <div class="mt-2 pt-2 border-t border-gray-300">
                            <p class="text-xs text-gray-500 italic">No items checked yet.</p>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif


        </div>

        {{-- Action Buttons --}}
        <div class="mt-8 flex gap-4 justify-end">
            <a href="{{ route('basecampuser') }}" 
               class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition duration-200">
                Back
            </a>
            <a href="{{ route('basecampuser.edit', ['id' => $user->id]) }}" 
               class="px-6 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 transition duration-200">
                Edit User
            </a>
        </div>
    </div>
</div>
