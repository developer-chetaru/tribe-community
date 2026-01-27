<x-guest-layout>

    @if (session('status'))
        <div id="resetSuccessAlert" class="mb-6 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <i class="fa-solid fa-circle-check text-lg text-green-500"></i>
            <div class="flex flex-col">
                <span class="font-semibold">{{ session('status') }}</span>
                <span class="text-xs text-green-600">Redirecting to login in <span id="redirectCountdown">3</span>s...</span>
            </div>
        </div>
    @endif

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- Left Section -->
        <div class="flex flex-col justify-center items-center md:w-1/2 w-full bg-white px-4 py-8 h-full min-h-screen">
            <div class="w-full max-w-md">
                <div class="flex justify-start mb-8">
                    <img src="{{ asset('images/logo-tribe.svg') }}" width="300">
                </div>

                <h2 class="text-2xl font-bold mb-4 text-gray-900 text-start">Reset Password</h2>

                @if (session('status'))
                    <div class="text-center py-8">
                        <p class="text-lg text-gray-600">Your password has been successfully reset!</p>
                    </div>
                @else
                <form method="POST" action="{{ route('password.update') }}" class="text-left">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->token }}">

                    <!-- Email -->
                    <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded mb-4 px-3 w-full">
                        <i class="fa-solid fa-envelope text-gray-400 mr-2"></i>
                        <input
                            type="email"
                            name="email"
                            readonly
                            value="{{ old('email', $request->email) }}"
                            class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500"
                        />
                    </label>

                    <!-- New Password -->
                    <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded px-3 w-full mb-1">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="New Password"
                            class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500"
                        />
                        <button type="button" onclick="togglePassword('password', this)">
                            <i class="fa-solid fa-eye text-gray-500 ml-2"></i>
                        </button>
                    </label>

                    <p id="passwordMessage" class="text-sm mt-1"></p>

                    @error('password')
                        <p class="text-sm text-red-500 mb-2">{{ $message }}</p>
                    @enderror

                    <!-- Confirm Password -->
                    <label class="flex bg-[#fafafa] items-center border border-gray-300 rounded px-3 w-full mt-4 mb-1">
                        <input
                            id="confirmPassword"
                            type="password"
                            name="password_confirmation"
                            placeholder="Confirm Password"
                            class="w-full bg-transparent border-none outline-none focus:ring-0 text-gray-500"
                        />
                        <button type="button" onclick="togglePassword('confirmPassword', this)">
                            <i class="fa-solid fa-eye text-gray-500 ml-2"></i>
                        </button>
                    </label>

                    <p id="confirmMessage" class="text-sm mt-1"></p>

                    @error('password_confirmation')
                        <p class="text-sm text-red-500 mb-2">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col items-center gap-3 mt-4">
                        <button
                            id="submitBtn"
                            disabled
                            type="submit"
                            class="w-full text-white font-semibold py-3 px-4 rounded-full transition-all duration-200 bg-red-300 cursor-not-allowed opacity-100"
                            style="background-color: #fca5a5; min-height: 44px;"
                        >
                            Reset Password
                        </button>

                        <div class="text-center text-xm mt-4">
                            Back to
                            <a href="{{ route('login') }}" class="text-red-500 font-semibold hover:underline">
                                Login
                            </a>
                        </div>
                    </div>

                </form>
                @endif
            </div>
        </div>

        <!-- Right Section -->
        <div class="hidden md:flex md:w-1/2 w-full flex-col px-8 py-8 h-full min-h-screen bg-cover bg-center"
             style="background: url('{{ asset('images/group.svg') }}') no-repeat center; background-size: cover;">

            <div class="z-10 w-full pb-[90px] pt-12">
                <h3 class="text-2xl font-bold text-red-500 mb-6 mt-4">Start Your Tribe365 Journey</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div><p class="font-semibold text-[#222] text-base">Log your emotions,<br>empower your team,<br>and align your mission.</p></div>
                    <div><p class="font-semibold text-[#222] text-base">Create your space for<br>clarity, connection, and<br>culture.</p></div>
                    <div><p class="font-semibold text-[#222] text-base">Because great cultures<br>don’t just happen — they’re<br>built, one day at a time.</p></div>
                </div>
            </div>

            <div class="absolute bottom-10 right-10 z-20 w-[90%] md:w-[85%] max-w-xl">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white shadow-lg rounded-xl px-6 py-4">
                    <span class="text-[#22294F] font-bold text-base md:text-xl text-center sm:text-left">Want to know how it works?</span>
                    <button class="bg-[#454B60] text-white font-medium rounded-lg px-5 py-2 text-sm md:text-base hover:bg-[#252A3A]">
                        Explore Features
                    </button>
                </div>
            </div>

       
    </div>


    <!-- JS -->
    <script>
        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector("i");
            input.type = input.type === "password" ? "text" : "password";
            icon.classList.toggle("fa-eye");
            icon.classList.toggle("fa-eye-slash");
        }

        /* === Your existing working validation script below === */
        const password = document.getElementById("password");
        const confirmPassword = document.getElementById("confirmPassword");
        const passwordMessage = document.getElementById("passwordMessage");
        const confirmMessage = document.getElementById("confirmMessage");
        const submitBtn = document.getElementById("submitBtn");
        const countdownEl = document.getElementById("redirectCountdown");
        const statusMessage = @json(session('status'));
        
        let passwordFirstBlur = false;
        let confirmFirstBlur = false;
        let isStrong = false;
        let isMatch = false;

        function checkStrength(pwd) {
            let score = 0;
            if (pwd.length >= 8) score++;
            if (/[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;
            return score >= 4 ? "strong" : score >= 2 ? "medium" : "weak";
        }

        function updateButtonState() {
            submitBtn.disabled = !(isStrong && isMatch);
            if (isStrong && isMatch) {
                submitBtn.className = "w-full text-white font-semibold py-3 px-4 rounded-full transition-all duration-200 bg-red-500 cursor-pointer hover:bg-red-600 opacity-100";
                submitBtn.style.backgroundColor = "#ef4444";
                submitBtn.style.cursor = "pointer";
            } else {
                submitBtn.className = "w-full text-white font-semibold py-3 px-4 rounded-full transition-all duration-200 bg-red-300 cursor-not-allowed opacity-100";
                submitBtn.style.backgroundColor = "#fca5a5";
                submitBtn.style.cursor = "not-allowed";
            }
        }

        function triggerPasswordCheck() {
            const val = password.value.trim();
            
            // Don't show message if field is empty
            if (val.length === 0) {
                passwordMessage.textContent = "";
                passwordMessage.className = "";
                isStrong = false;
                triggerConfirmCheck();
                updateButtonState();
                return;
            }
            
            const strength = checkStrength(val);
            isStrong = strength === "strong";
            passwordMessage.textContent =
                strength === "weak" ? "Weak password — add uppercase, numbers & symbols." :
                strength === "medium" ? "Medium strength — try adding more characters or symbols." :
                "Strong password";

            passwordMessage.className =
                strength === "weak" ? "text-sm font-semibold text-red-500" :
                strength === "medium" ? "text-sm font-semibold text-yellow-600" :
                "text-sm font-semibold text-green-600";

            triggerConfirmCheck();
            updateButtonState();
        }

        function triggerConfirmCheck() {
            const confirmVal = confirmPassword.value.trim();
            
            // Don't show message if confirm field is empty
            if (confirmVal.length === 0) {
                confirmMessage.textContent = "";
                confirmMessage.className = "";
                isMatch = false;
                updateButtonState();
                return;
            }
            
            isMatch = (password.value === confirmPassword.value);
            confirmMessage.textContent = isMatch ? "Password matched" : "Password does not match";
            confirmMessage.className = isMatch ? "text-sm font-semibold text-green-600" : "text-sm font-semibold text-red-500";
            updateButtonState();
        }

        password.addEventListener("blur", () => { passwordFirstBlur = true; triggerPasswordCheck(); });
        password.addEventListener("input", () => { if (passwordFirstBlur) triggerPasswordCheck(); });
        confirmPassword.addEventListener("blur", () => { confirmFirstBlur = true; triggerConfirmCheck(); });
        confirmPassword.addEventListener("input", () => { if (confirmFirstBlur) triggerConfirmCheck(); });

        if (statusMessage) {
            let remaining = 3;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Redirecting...";
                submitBtn.className = "w-full text-white font-semibold py-3 px-4 rounded-full transition-all duration-200 bg-green-500 cursor-wait opacity-100";
                submitBtn.style.backgroundColor = "#22c55e";
                submitBtn.style.minHeight = "44px";
            }

            const countdownTimer = setInterval(() => {
                remaining -= 1;
                if (countdownEl && remaining > 0) {
                    countdownEl.textContent = remaining;
                }

                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                }
            }, 1000);

            setTimeout(() => {
                window.location.href = "{{ route('login') }}";
            }, 3000);
        }

    </script>

</x-guest-layout>
