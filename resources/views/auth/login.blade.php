<x-guest-layout>
    <div class="min-h-screen flex flex-col md:flex-row bg-[#FFF7F7]">
        {{-- Left side: Login form --}}
        <div class="flex flex-col justify-center items-center md:w-1/2 w-full bg-white px-4 py-8 h-full min-h-screen">
            <div class="w-full max-w-md">
                <div class="flex justify-start mb-8">
                    <img src="{{ asset('images/logo-tribe.svg') }}" alt="Tribe365 Logo" class="h-10 w-auto">
                </div>

                <h2 class="text-2xl font-bold mb-4 text-gray-900">
                    Sign in to <span class="text-red-500">Tribe365</span>
                </h2>

              
            @if (session('status'))
    <div 
        x-data="{ show: true }" 
        x-init="setTimeout(() => show = false, 3000)" 
        x-show="show"
        x-transition
        class="mb-4 font-medium text-sm text-red-600"
    >
        {{ session('status') }}
    </div>
@endif


            <form 
    method="POST" 
    action="{{ route('login') }}" 
    x-data="{ loading: false }" 
    @submit.prevent="loading = true; $el.submit();"
>
    @csrf

    {{-- Email --}}
    <div>
        <label class="flex items-center border border-gray-300 rounded mb-2 px-3 bg-[#fafafa]">
            <svg class="text-gray-400 mr-2" width="16" height="16" fill="currentColor">
                <use xlink:href="#email-icon" />
            </svg>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="E-Mail Address"
                autocomplete="username"
                autofocus
                class="w-full bg-transparent border-none outline-none appearance-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base mb-1"
            />
        </label>
      @error('email')
        <p class="text-sm text-red-500 mt-1 mb-2">{{ $message }}</p>
      @enderror
    </div>

    {{-- Password --}}
    <div x-data="{ show: false }" class="relative">
        <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-1 px-3  w-full">
            <i class="fa-solid fa-lock text-gray-400 mr-2"></i>

            <input
                :type="show ? 'text' : 'password'"
                id="password"
                name="password"
                placeholder="Password"
                class="w-full bg-transparent border-none outline-none appearance-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                autocomplete="current-password"
            />

            <button type="button" @click="show = !show" class="ml-2 text-gray-500 focus:outline-none">
                <i :class="show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
            </button>
        </label>
        @error('password')
            <p class="text-sm text-red-500 mt-1 mb-2">{{ $message }}</p>
        @enderror
    </div>

    {{-- Remember Me + Forgot --}}
    <div class="flex items-center justify-between text-sm text-gray-600 mt-2">
        <label class="flex items-center space-x-2">
            <input type="checkbox" name="remember" class="form-checkbox h-4 w-4 text-red-500 accent-red-500 border-gray-300" />
            <span>Remember me</span>
        </label>
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="text-gray-800 hover:underline">
                Forgot Password?
            </a>
        @endif
    </div>

    {{-- Submit Button --}}
    <button
        type="submit"
        class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2 rounded-full transition mt-4"
        :disabled="loading"
        x-bind:class="{ 'opacity-50 cursor-not-allowed': loading }"
    >
        Log in
    </button>

    {{-- Register link --}}
    <div class="text-center text-xs mt-4">
        Don’t have an account?
        <a href="{{ route('register') }}" class="text-red-500 font-semibold hover:underline">
            Sign Up
        </a>
    </div>
</form>
</div>
        </div>

        {{-- Right side: Info / Background --}}
        <div class="hidden md:flex md:w-1/2 w-full flex-col px-8 py-8 h-full min-h-screen bg-[url('/images/group.svg')] bg-no-repeat bg-cover bg-center">
            <div class="z-10 w-full pb-[390px] pt-12">
                <h3 class="text-2xl font-bold text-red-500 mb-6 mt-4">
                    Welcome Back to Tribe365
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
        </div>
    </div>

    {{-- Optional SVG icons (or use Heroicons / FontAwesome) --}}
    <svg style="display: none;">
        <symbol id="email-icon" viewBox="0 0 16 16">
            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v.217l-8 4.8-8-4.8V4z"/>
            <path d="M0 6.383l6.803 4.082a.5.5 0 0 0 .394 0L16 6.383V12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6.383z"/>
        </symbol>
        <symbol id="lock-icon" viewBox="0 0 16 16">
            <path d="M8 1a4 4 0 0 0-4 4v3H3.5A1.5 1.5 0 0 0 2 9.5v5A1.5 1.5 0 0 0 3.5 16h9A1.5 1.5 0 0 0 14 14.5v-5A1.5 1.5 0 0 0 12.5 8H12V5a4 4 0 0 0-4-4z"/>
        </symbol>
    </svg>
</x-guest-layout>
