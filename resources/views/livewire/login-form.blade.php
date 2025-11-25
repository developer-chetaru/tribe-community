<div class="w-full max-w-md">
    <div class="flex justify-start mb-8">
        <img src="{{ asset('images/logo-tribe.svg') }}" alt="Tribe365 Logo" class="h-10 w-auto">
    </div>

    <h2 class="text-2xl font-bold mb-4 text-gray-900">
        Sign in to <span class="text-red-500">Tribe365</span>
    </h2>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="flex items-center border border-gray-300 rounded mb-1 px-3 py-2 bg-[#fafafa]">
                <svg class="text-gray-400 mr-2" width="16" height="16" fill="currentColor">
                    <use xlink:href="#email-icon" />
                </svg>
                <input
                    type="email"
                    wire:model.defer="email"
                    placeholder="E-Mail Address"
                    class="w-full bg-transparent outline-none text-base"
                    autocomplete="username"
                />
            </label>
            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="flex items-center border border-gray-300 rounded mb-1 px-3 py-2 bg-[#fafafa] relative">
                <svg class="text-gray-400 mr-2" width="16" height="16" fill="currentColor">
                    <use xlink:href="#lock-icon" />
                </svg>
                <input
                    type="password"
                    wire:model.defer="password"
                    placeholder="Password"
                    class="w-full bg-transparent outline-none text-base pr-8"
                    autocomplete="current-password"
                />
            </label>
            @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between text-sm text-gray-600">
            <label class="flex items-center space-x-2">
                <input type="checkbox" wire:model="remember" class="form-checkbox h-4 w-4 text-red-500 border-gray-300" />
                <span>Remember me</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-gray-800 hover:underline">
                    Forgot Password?
                </a>
            @endif
        </div>

        <button
            type="submit"
            class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2 rounded-full transition mt-2"
        >
            Log in
        </button>

        <div class="text-center text-xs mt-4">
            Don’t have an account?
            <a href="{{ route('register') }}" class="text-red-500 font-semibold hover:underline">
                Sign Up
            </a>
        </div>
    </form>
</div>
