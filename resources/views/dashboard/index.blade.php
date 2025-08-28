<x-dashboard-layout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
        <div class="container mx-auto px-4 py-8 space-y-8">
            <!-- Email Verification Notice -->
            @if($stats['show_email_notice'])
                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-yellow-900 mb-1">Email Verification Required</h3>
                            <p class="text-sm text-yellow-800">
                                Please check your inbox and verify your email to unlock all features.
                            </p>
                        </div>
                        <div class="ml-4">
                            <a href="{{ route('verification.notice') }}" class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-xl transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Verify Now
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Hero Header -->
            <div class="text-center space-y-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-3xl shadow-lg transform rotate-3 hover:rotate-0 transition-transform duration-300">
                    <span class="text-3xl">🚀</span>
                </div>
                <div>
                    <h1 class="text-4xl lg:text-6xl font-bold bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 bg-clip-text text-transparent mb-4">
                        Welcome back, {{ auth()->user()->name }}!
                    </h1>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                        Transform your files with professional-grade conversion tools. 
                        Fast, secure, and high-quality results every time.
                    </p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Individual Type Statistics -->
                <div class="col-span-full mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Conversion Statistics by Type</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <!-- Documents -->
                        <div class="bg-white/60 backdrop-blur-sm rounded-xl p-4 text-center border border-white/20">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl mx-auto mb-2 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-blue-600">{{ number_format($stats['pdf_processings']) }}</p>
                            <p class="text-xs text-gray-600">Documents</p>
                            <p class="text-xs text-green-600">+{{ $stats['pdf_today'] }} today</p>
                        </div>
                        
                        <!-- Images -->
                        <div class="bg-white/60 backdrop-blur-sm rounded-xl p-4 text-center border border-white/20">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl mx-auto mb-2 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-purple-600">{{ number_format($stats['image_processings']) }}</p>
                            <p class="text-xs text-gray-600">Images</p>
                            <p class="text-xs text-green-600">+{{ $stats['image_today'] }} today</p>
                        </div>
                        
                        <!-- Videos -->
                        <div class="bg-white/60 backdrop-blur-sm rounded-xl p-4 text-center border border-white/20">
                            <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-red-600 rounded-xl mx-auto mb-2 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-red-600">{{ number_format($stats['video_processings']) }}</p>
                            <p class="text-xs text-gray-600">Videos</p>
                            <p class="text-xs text-green-600">+{{ $stats['video_today'] }} today</p>
                        </div>
                        
                        <!-- Audio -->
                        <div class="bg-white/60 backdrop-blur-sm rounded-xl p-4 text-center border border-white/20">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-xl mx-auto mb-2 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                </svg>
                            </div>
                            <p class="text-2xl font-bold text-green-600">{{ number_format($stats['audio_processings']) }}</p>
                            <p class="text-xs text-gray-600">Audio</p>
                            <p class="text-xs text-green-600">+{{ $stats['audio_today'] }} today</p>
                        </div>
                    </div>
                </div>
                <!-- Total Conversions Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Conversions</p>
                            <p class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">{{ number_format($stats['total_processings']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Today's Activity Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 mb-1">Completed Today</p>
                            <p class="text-3xl font-bold bg-gradient-to-r from-green-600 to-green-800 bg-clip-text text-transparent">{{ number_format($stats['today_processings']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Storage Used Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 mb-1">Storage Used</p>
                            <p class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-purple-800 bg-clip-text text-transparent">{{ $stats['storage_used'] }}</p>
                        </div>
                    </div>
                </div>

                <!-- Success Rate Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20 p-6 hover:shadow-xl transition-all duration-300 group">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 mb-1">Success Rate</p>
                            <p class="text-3xl font-bold bg-gradient-to-r from-orange-600 to-orange-800 bg-clip-text text-transparent">98.5%</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Recent Activity -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border border-white/20">
                <div class="px-8 py-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-gray-900">Recent Activity</h2>
                        <a href="{{ route('history') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium px-4 py-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors duration-200">View All</a>
                    </div>
                </div>
            
                <div class="p-8">
                    @if($stats['recent_conversions']->count() > 0)
                        <div class="space-y-4">
                            @foreach($stats['recent_conversions'] as $conversion)
                                @php
                                    // Use the conversion_type set in controller
                                    $conversionType = $conversion->conversion_type;
                                    
                                    switch ($conversionType) {
                                        case 'image':
                                            $iconBg = 'bg-gradient-to-br from-purple-500 to-pink-600';
                                            $iconSvg = '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>';
                                            $displayType = 'Image';
                                            break;
                                        case 'video':
                                            $iconBg = 'bg-gradient-to-br from-red-500 to-red-600';
                                            $iconSvg = '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                        </svg>';
                                            $displayType = 'Video';
                                            break;
                                        case 'audio':
                                            $iconBg = 'bg-gradient-to-br from-green-500 to-green-600';
                                            $iconSvg = '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                                        </svg>';
                                            $displayType = 'Audio';
                                            break;
                                        default: // document
                                            $iconBg = 'bg-gradient-to-br from-blue-500 to-indigo-600';
                                            $iconSvg = '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>';
                                            $displayType = 'Document';
                                            break;
                                    }

                                    // Get proper extension info
                                    $originalExt = $conversion->original_extension ?? pathinfo($conversion->original_filename, PATHINFO_EXTENSION);
                                    $targetExt = $conversion->target_extension ?? $originalExt;
                                    
                                    // For resize/rotate operations, target is same as original
                                    if (in_array($conversion->tool_name, ['resize-image', 'rotate-image'])) {
                                        $targetExt = $originalExt;
                                    }
                                @endphp
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors duration-200">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 {{ $iconBg }} rounded-xl flex items-center justify-center">
                                            {!! $iconSvg !!}
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ $conversion->original_filename }}</h4>
                                            <div class="flex items-center space-x-2 mt-1">
                                                @if($targetExt && $targetExt !== $originalExt)
                                                    <span class="text-xs text-gray-500">{{ $displayType }} Conversion:</span>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-gray-200 text-gray-700">
                                                        {{ strtoupper($originalExt) }}
                                                    </span>
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-blue-100 text-blue-700">
                                                        {{ strtoupper($targetExt) }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-500">{{ $displayType }} Processing:</span>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-blue-100 text-blue-700">
                                                        {{ ucfirst(str_replace('-', ' ', $conversion->tool_name)) }}
                                                    </span>
                                                @endif
                                                <span class="text-xs text-gray-500">{{ $conversion->file_size_human ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        @php
                                            $statusConfig = [
                                                'completed' => ['bg-green-100 text-green-800', '✓'],
                                                'processing' => ['bg-yellow-100 text-yellow-800', '⏳'],
                                                'failed' => ['bg-red-100 text-red-800', '✗'],
                                                'pending' => ['bg-gray-100 text-gray-800', '⏸'],
                                            ];
                                            $config = $statusConfig[$conversion->status] ?? $statusConfig['pending'];
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $config[0] }}">
                                            {{ $config[1] }} {{ ucfirst($conversion->status) }}
                                        </span>
                                        @if($conversion->status === 'completed')
                                            @switch($conversionType)
                                                @case('image')
                                                    <a href="{{ route('image-tools.download', $conversion->id) }}" 
                                                       class="inline-flex items-center px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"></path>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    @break
                                                @case('video')
                                                    <a href="{{ route('video-tools.download', $conversion->id) }}" 
                                                       class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"></path>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    @break
                                                @case('audio')
                                                    <a href="{{ route('audio-tools.download', $conversion->id) }}" 
                                                       class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"></path>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    @break
                                                @default
                                                    <a href="{{ route('documents.download', $conversion->id) }}" 
                                                       class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors duration-200">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"></path>
                                                        </svg>
                                                        Download
                                                    </a>
                                            @endswitch
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-16">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-3xl mb-6">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">No conversions yet</h3>
                            <p class="text-gray-600 mb-8">Start your first conversion to see your activity here</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                                <a href="{{ route('tools.documents') }}" class="inline-flex flex-col items-center px-4 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-medium rounded-xl hover:from-blue-600 hover:to-indigo-700 transition-all duration-200">
                                    <svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span class="text-sm">Documents</span>
                                </a>
                                <a href="{{ route('tools.images') }}" class="inline-flex flex-col items-center px-4 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-medium rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all duration-200">
                                    <svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm">Images</span>
                                </a>
                                <a href="{{ route('tools.video') }}" class="inline-flex flex-col items-center px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-medium rounded-xl hover:from-red-600 hover:to-red-700 transition-all duration-200">
                                    <svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm">Videos</span>
                                </a>
                                <a href="{{ route('tools.audio') }}" class="inline-flex flex-col items-center px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-xl hover:from-green-600 hover:to-green-700 transition-all duration-200">
                                    <svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                    </svg>
                                    <span class="text-sm">Audio</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
</x-dashboard-layout>
