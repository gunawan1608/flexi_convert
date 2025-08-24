<x-auth-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Verify Your Email</h2>
        <p class="text-gray-600 text-center text-sm">Check your inbox to complete registration</p>
    </div>

    <div class="mb-6 p-6 bg-blue-50 border border-blue-200 rounded-xl text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-blue-900 mb-2">Email Sent!</h3>
        <p class="text-sm text-blue-800 mb-4">
            We've sent a verification link to your email address. Click the link in the email to verify your account and start using FlexiConvert.
        </p>
        <div class="text-xs text-blue-700 bg-blue-100 rounded-lg p-3">
            <strong>Didn't receive the email?</strong> Check your spam folder or click the resend button below.
        </div>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-sm font-medium text-green-800">Verification email sent successfully! Please check your inbox.</span>
            </div>
        </div>
    @endif

    <div class="space-y-4">
        <!-- Resend Email Button -->
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-xl font-semibold hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-[1.02] shadow-lg hover:shadow-xl">
                Resend Verification Email
            </button>
        </form>

        <!-- Alternative Actions -->
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ route('dashboard') }}" class="flex-1 bg-gray-100 text-gray-700 py-3 px-4 rounded-xl font-medium hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 text-center">
                Continue to Dashboard
            </a>
            
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full bg-white border border-gray-300 text-gray-700 py-3 px-4 rounded-xl font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200">
                    Sign Out
                </button>
            </form>
        </div>
    </div>

    <!-- Help Text -->
    <div class="mt-6 text-center pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-500 mb-2">Having trouble?</p>
        <p class="text-sm text-gray-600">
            Contact our support team at 
            <a href="mailto:support@flexiconvert.com" class="font-semibold text-blue-600 hover:text-blue-700 transition-colors duration-200">
                support@flexiconvert.com
            </a>
        </p>
    </div>
</x-auth-layout>
