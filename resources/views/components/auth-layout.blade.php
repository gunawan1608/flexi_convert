<x-layout>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-blue-50 via-white to-blue-100 relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-br from-blue-400/20 to-blue-600/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-gradient-to-br from-blue-300/20 to-blue-500/20 rounded-full blur-3xl"></div>
        </div>
        
        <div class="relative z-10 w-full sm:max-w-md mt-6 px-8 py-8 bg-white/80 backdrop-blur-xl shadow-2xl rounded-2xl border border-white/20">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-blue-700 bg-clip-text text-transparent">FlexiConvert</h1>
                <p class="text-gray-600 mt-2 font-medium">File Conversion Made Easy</p>
            </div>
            
            {{ $slot }}
        </div>
        
        <!-- Back to home link -->
        <div class="relative z-20 mt-6 text-center">
            <a href="{{ route('home') }}" class="inline-flex items-center justify-center gap-2 text-blue-600 hover:text-blue-700 font-medium transition-colors duration-200 px-4 py-2 rounded-lg hover:bg-blue-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Home
            </a>
        </div>
    </div>
</x-layout>
