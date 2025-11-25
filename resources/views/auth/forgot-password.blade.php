<x-guest-layout>
   
        <div class="min-h-screen flex flex-col md:flex-row">
            <!-- Left Section -->
            <div class="flex flex-col justify-center items-center md:w-1/2 w-full bg-white px-4 py-8 h-full min-h-screen">
                <div class="w-full max-w-md">
                    <!-- Logo -->
                    <div class="flex justify-start mb-8">
                        <img src="{{ asset('images/logo-tribe.svg') }}" width="300">
                    </div>

                    <!-- Heading -->
                    <h2 class="text-2xl font-bold mb-4 text-gray-900 text-start">Forgot your password?</h2>

                    <!-- Laravel Flash Messages -->
                    @if (session('status'))
                        <div class="mb-4 font-medium text-sm text-red-600">
                            {{ session('status') }}
                        </div>
                    @endif

                    <!-- Form -->
                    <form method="POST" action="{{ route('password.email') }}" class="text-left">
                        @csrf

                        <div class="mb-6">
                            <div class="flex items-center">
                                   <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-1 px-3 w-full">
        <i class="fa-solid fa-envelope text-gray-400 mr-2"></i>

                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    value="{{ old('email') }}"
                                    placeholder="E-Mail Address"
                                    class="w-full bg-transparent mt- border-none outline-none focus:ring-0 text-gray-500 placeholder-gray-400 text-base"
                                    
                                    autofocus
                                />
                            </div>
   @if ($errors->any())
                        <div class="mb-4">
                            <ul class="text-sm text-red-600 pl-5 space-y-1 list-none">
    @foreach ($errors->all() as $error)
        <li>{{ rtrim($error, '.') }}</li>
    @endforeach
</ul>

                        </div>
                    @endif
     
                        </div>

                        <!-- Submit Button -->
                        <div class="flex flex-col items-center gap-3">
                            <button type="submit"
                                    class="w-full text-white font-semibold py-2 rounded-full transition bg-red-500 hover:bg-red-600 cursor-pointer">
                                Submit
                            </button>

                            <div class="text-center text-xm mt-4">
                                Back to
                                <a href="{{ route('login') }}" class="text-red-500 font-semibold cursor-pointer hover:underline">Login</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Section -->
            <div class="hidden md:flex md:w-1/2 w-full flex-col px-8 py-8 h-full min-h-screen bg-cover bg-center"
                 style="background: url('{{ asset('images/group.svg') }}') no-repeat center; background-size: cover;">
                <div class="z-10 w-full pb-[90px] pt-12">
                    <h3 class="text-2xl font-bold text-red-500 mb-6 mt-4">Start Your Tribe365 Journey</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="font-semibold text-[#222] text-base">Log your emotions,<br>empower your team,<br>and align your mission.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-[#222] text-base">Create your space for<br>clarity, connection, and<br>culture.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-[#222] text-base">Because great cultures<br>don’t just happen — they’re<br>built, one day at a time.</p>
                        </div>
                    </div>
                </div>

                <!-- Call to Action Box -->
                <div class="absolute bottom-10 right-10 z-20 w-[90%] md:w-[85%] max-w-xl">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white shadow-lg rounded-xl px-6 py-4">
                        <span class="text-[#22294F] font-bold text-base md:text-xl text-center sm:text-left">
                            Want to know how it works?
                        </span>
                        <button class="bg-[#454B60] text-white font-medium rounded-lg px-5 py-2 text-sm md:text-base transition hover:bg-[#252A3A] cursor-pointer">
                            Explore Features
                        </button>
                    </div>
                </div>
            </div>
        </div>

</x-guest-layout>
