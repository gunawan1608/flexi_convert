<x-layout>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-blue-50 to-indigo-100">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-xl rounded-lg">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">FlexiConvert</h1>
            <p class="text-gray-600 mt-2">File Conversion Made Easy</p>
        </div>
            
            {{ $slot }}
        </div>
    </div>
</x-layout>
