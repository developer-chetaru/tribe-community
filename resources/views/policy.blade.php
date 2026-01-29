<x-guest-layout>
    <div class="min-h-screen bg-gradient-to-br from-[#FFF7F7] via-white to-gray-50">
        <div class="container mx-auto  py-8">
            <!-- Header with Logo -->
            <div class="text-center mb-8">
                <a href="{{ url('/login') }}" class="inline-block mb-6 hover:opacity-80 transition-opacity">
                    <img src="{{ asset('images/logo-tribe.svg') }}" 
                         alt="Tribe365 Logo" 
                         class="h-16 md:h-20 w-auto mx-auto"
                         onerror="this.onerror=null; this.src='{{ asset('images/logo-tribe.png') }}';">
                </a>
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-2">
                    Privacy <span class="text-[#EB1C24]">Policy</span>
                </h1>
                <p class="text-gray-600 text-lg">Your privacy is important to us</p>
            </div>

            <!-- Content Card -->
            <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Top Accent Bar -->
                <div class="h-2 bg-gradient-to-r from-[#EB1C24] to-red-600"></div>
                
                <!-- Content -->
                <div class="p-8 md:p-12 prose prose-lg max-w-none" style="margin: 0 auto; padding: 0 20px 30px; max-width: 900px;">
                    {!! $policy !!}
                </div>
            </div>

            <!-- Call to Action Section -->
            <div class="max-w-4xl mx-auto mt-12">
                <div class="rounded-2xl shadow-xl p-8 md:p-12 text-center" style="background: linear-gradient(to right, #EB1C24, #DC2626);">
                    <h2 class="text-3xl md:text-4xl font-bold mb-4" style="color: #FFFFFF;">Ready to Get Started?</h2>
                    <p class="text-xl mb-8" style="color: #FFFFFF; opacity: 0.95;">Join Tribe365 today and start building better teams together</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" 
                           class="inline-block bg-white text-[#EB1C24] font-bold py-4 px-8 rounded-full hover:bg-gray-50 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl">
                            Go to Sign Up
                        </a>
                        <a href="{{ url('/login') }}" 
                           class="inline-block bg-transparent border-2 border-white font-bold py-4 px-8 rounded-full hover:bg-white transition-all duration-300 transform hover:scale-105"
                           style="color: #FFFFFF; border-color: #FFFFFF;"
                           onmouseover="this.style.backgroundColor='#FFFFFF'; this.style.color='#EB1C24';"
                           onmouseout="this.style.backgroundColor='transparent'; this.style.color='#FFFFFF';">
                            Already have an account? Sign In
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer Links -->
            <div class="text-center mt-8 text-gray-700">
                <p class="mb-2">
                    <a href="{{ route('terms.show') }}" class="text-[#EB1C24] hover:text-red-700 underline font-medium transition-colors">Terms of Service</a>
                    <span class="mx-2 text-gray-400">•</span>
                    <a href="{{ route('policy.show') }}" class="text-[#EB1C24] hover:text-red-700 underline font-medium transition-colors">Privacy Policy</a>
                </p>
                <p class="text-sm text-gray-600">© {{ date('Y') }} TRIBE365® Ltd. All rights reserved.</p>
            </div>
        </div>
    </div>

    <style>
        .prose h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
            margin-top: 2rem;
        }
        .prose h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
            margin-top: 1.5rem;
        }
        .prose p {
            color: #374151;
            margin-bottom: 1rem;
            line-height: 1.75;
        }
        .prose ul {
            list-style-type: disc;
            list-style-position: inside;
            margin-bottom: 1rem;
        }
        .prose ul li {
            margin-top: 0.5rem;
        }
        .prose li {
            color: #374151;
        }
        .prose strong {
            font-weight: 700;
            color: #111827;
        }
    </style>
</x-guest-layout>
