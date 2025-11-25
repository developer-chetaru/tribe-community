<x-guest-layout>
    <div class="min-h-screen flex flex-col md:flex-row bg-[#FFF7F7]">
        {{-- Left: Registration Form --}}
        <div class="flex flex-col justify-center items-center md:w-1/2 w-full bg-white px-4 py-8 h-full min-h-screen">
            <div class="w-full max-w-md">
                <div class="flex justify-start mb-8">
                    <img src="{{ asset('images/logo-tribe.svg') }}" alt="Tribe365 Logo" class="h-10 w-auto">
                </div>

                <h2 class="text-2xl font-bold mb-4 text-gray-900">
                    Create your <span class="text-red-500">Tribe365</span> account
                </h2>

              
                <form method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <!-- First Name -->
                    <div>
                        <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-1 px-3 w-full">
                            <i class="fa-solid fa-user text-gray-400 mr-2"></i>
                        <input
                    id="first_name"
                    type="text"
                    name="first_name"
                    value="{{ old('first_name') }}"
                    placeholder="First Name"
                    class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                    autofocus
                    autocomplete="given-name"
                />

                        </label>
                        @error('first_name')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-1 px-3 w-full">
                            <i class="fa-solid fa-user text-gray-400 mr-2"></i>
                        <input
                    id="last_name"
                    type="text"
                    name="last_name"
                    value="{{ old('last_name') }}"
                    placeholder="Last Name"
                    class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                    autocomplete="family-name"
                />

                        </label>
                        @error('last_name')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>



                            <div>
                    <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-1 px-3 w-full">
                        <i class="fa-solid fa-envelope text-gray-400 mr-2"></i>

                        <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Email"
                    class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                    autocomplete="username"
                />

                    </label>
                    @error('email')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>


                <!-- Password Field -->
                <div x-data="passwordStrength()" class="relative mt-4">
                    <label 
                        class="flex bg-[#fafafa] items-center rounded px-3 border mt-1 transition"
                        :class="{
                            'border-gray-300': strength === '',
                            'border-red-500': strength === 'weak',
                            'border-yellow-500': strength === 'medium',
                            'border-green-500': strength === 'strong'
                        }"
                    >
                        <i class="fa-solid fa-lock text-gray-400 mr-2"></i>

                        <input
                            :type="show ? 'text' : 'password'"
                            id="password"
                            name="password"
                            placeholder="Password"

                            @blur="validateStrength()"
                            @input="handleInput()"

                            class="w-full bg-transparent border-none outline-none appearance-none 
                                focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                            autocomplete="new-password"
                            x-ref="password"
                        />

                        <button type="button" @click="show = !show" class="ml-2 text-gray-500 focus:outline-none">
                            <i :class="show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                        </button>
                    </label>

                    <!-- Meter Bar -->
                    <div class="mt-2 w-full h-1 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full transition-all duration-300"
                            :class="{
                                'bg-red-500 w-1/3': strength === 'weak',
                                'bg-yellow-500 w-2/3': strength === 'medium',
                                'bg-green-500 w-full': strength === 'strong'
                            }">
                        </div>
                    </div>

                    <!-- Text Message -->
                    <p class="mt-1 text-sm font-semibold"
                    :class="{
                            'text-red-500': strength === 'weak',
                            'text-yellow-600': strength === 'medium',
                            'text-green-600': strength === 'strong'
                    }"
                    x-text="strengthMessage">
                    </p>

                    @error('password')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>


                   <!-- Confirm Password Field -->
                    <div x-data="confirmPasswordCheck()" class="relative mt-4">
                        <label 
                            class="flex bg-[#fafafa] items-center rounded px-3 mt-1 transition border"
                            :class="{
                                'border-gray-300': status === '',
                                'border-red-500': status === 'mismatch',
                                'border-green-500': status === 'match'
                            }"
                        >
                            <i class="fa-solid fa-lock text-gray-400 mr-2"></i>

                            <input
                                :type="showConfirm ? 'text' : 'password'"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="Confirm Password"

                                @blur="validateMatch()"  
                                @input="handleInput()" 

                                class="w-full bg-transparent border-none outline-none appearance-none focus:ring-0 
                                    text-gray-500 placeholder-gray-400 text-base"
                                autocomplete="new-password"
                                x-ref="confirm"
                            />

                            <button 
                                type="button" 
                                @click="showConfirm = !showConfirm"
                                class="ml-2 text-gray-500 focus:outline-none"
                            >
                                <i :class="showConfirm ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                            </button>
                        </label>

                        <!-- Result Message -->
                        <p class="text-sm mt-1 font-semibold"
                        :class="{
                            'text-red-500': status === 'mismatch',
                            'text-green-600': status === 'match'
                        }"
                        x-text="message">
                        </p>

                        @error('password_confirmation')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                        <div>
                            <x-label for="terms">
                                <div class="flex items-center">
                                    <x-checkbox name="terms" id="terms"  />
                                    <div class="ms-2">
                                        {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                            'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Terms of Service').'</a>',
                                            'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Privacy Policy').'</a>',
                                        ]) !!}
                                    </div>
                                </div>
                            </x-label>
                        </div>
                    @endif
				   			
                    <div class="mt-4 flex items-center justify-between">
					   			
                        <x-button class="w-full text-white font-semibold py-2 rounded-full transition bg-red-500 hover:bg-red-600 cursor-pointer">
                            Register
                        </x-button>
                      
                    </div>
                  <div class="text-center text-xs mt-4">Already have an account? 
    <a href="{{ route('login') }}" class="text-red-500 font-semibold cursor-pointer hover:underline">Login</a>
					   				</div>	
                </form>
            </div>
        </div>

        {{-- Right: Design Panel --}}
        <div class="hidden md:flex md:w-1/2 w-full flex-col px-8 py-8 h-full min-h-screen bg-[url('/images/group.svg')] bg-no-repeat bg-cover bg-center">
            <div class="z-10 w-full pb-[390px] pt-12">
                <h3 class="text-2xl font-bold text-red-500 mb-6 mt-4">
                    Welcome to Tribe365
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Stay connected<br />with your team.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Step back into your<br />daily rhythm.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Track your mood.<br />Reflect on your journey.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-[#222] text-base">
                            Together, we’re building a culture that thrives — one check-in at a time.
                        </p>
                    </div>
                </div>
            </div>
            		<div class="absolute bottom-10 right-10 z-20 w-[90%] md:w-[85%] max-w-xl">
	   			<div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white shadow-lg rounded-xl px-6 py-4">
	   				<span class="text-[#22294F] font-bold text-base md:text-xl text-center sm:text-left">Want to know how it works?</span>
	   				<button class="bg-[#454B60] text-white font-medium rounded-lg px-5 py-2 text-sm md:text-base transition hover:bg-[#252A3A] cursor-pointer">Explore Features</button>
	   			</div>
	   		</div>
        </div>
    </div>


    <!-- Alpine.js Password Strength Logic -->
    <script>
        function passwordStrength() {
            return {
                show: false,
                strength: '',
                strengthMessage: '',
                liveCheck: false,  // first time OFF

                validateStrength() {
                    const password = this.$refs.password.value;
                    this.calculate(password);

                    // If weak or medium → turn ON live checking after blur
                    if (this.strength === 'weak' || this.strength === 'medium') {
                        this.liveCheck = true;
                    }
                },

                handleInput() {
                    if (this.liveCheck) {
                        this.validateStrength();
                    }
                },

                calculate(password) {
                    let score = 0;

                    if (password.length >= 5) score++;
                    if (password.length >= 10) score++; // bonus
                    if (/[A-Z]/.test(password)) score++;
                    if (/[a-z]/.test(password)) score++;
                    if (/[0-9]/.test(password)) score++;
                    if (/[^A-Za-z0-9]/.test(password)) score++;

                    if (score <= 2) {
                        this.strength = 'weak';
                        this.strengthMessage = 'Weak password — add uppercase, numbers & symbols.';
                    } 
                    else if (score <= 4) {
                        this.strength = 'medium';
                        this.strengthMessage = 'Medium strength — try adding more characters or symbols.';
                    } 
                    else {
                        this.strength = 'strong';
                        this.strengthMessage = 'Strong password';
                    }
                }
            }
        }

        function confirmPasswordCheck() {
            return {
                showConfirm: false,
                status: '',
                message: '',
                liveCheck: false,

                validateMatch() {
                    const password = document.getElementById('password').value;
                    const confirm = this.$refs.confirm.value;

                    if (confirm.length === 0) {
                        this.status = '';
                        this.message = '';
                        return;
                    }

                    if (password === confirm) {
                        this.status = 'match';
                        this.message = 'Password matched';
                    } else {
                        this.status = 'mismatch';
                        this.message = 'Password does not match';
                        this.liveCheck = true; // Enable live checking AFTER first mismatch
                    }
                },

                handleInput() {
                    if (this.liveCheck) {
                        this.validateMatch();
                    }
                }
            }
        }
    </script>
</x-guest-layout>
