import React, { useState, useRef } from 'react';

const VideoConverter = () => {
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [selectedTool, setSelectedTool] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [settings, setSettings] = useState({
        quality: 'high',
        resolution: '1080p',
        bitrate: 'auto',
        fps: '30',
        codec: 'h264',
        format: 'mp4',
        compress: false
    });
    
    const fileInputRef = useRef(null);

    const videoTools = [
        {
            id: 'mp4-to-avi',
            name: 'MP4 to AVI',
            description: 'Convert MP4 videos to AVI format',
            category: 'Format Conversion'
        },
        {
            id: 'avi-to-mp4',
            name: 'AVI to MP4',
            description: 'Convert AVI videos to MP4 format',
            category: 'Format Conversion'
        },
        {
            id: 'mkv-to-mp4',
            name: 'MKV to MP4',
            description: 'Convert MKV videos to MP4 format',
            category: 'Format Conversion'
        },
        {
            id: 'mov-to-mp4',
            name: 'MOV to MP4',
            description: 'Convert MOV videos to MP4 format',
            category: 'Format Conversion'
        },
        {
            id: 'compress-video',
            name: 'Compress Video',
            description: 'Reduce video file size',
            category: 'Optimization'
        },
        {
            id: 'resize-video',
            name: 'Resize Video',
            description: 'Change video resolution',
            category: 'Editing'
        },
        {
            id: 'trim-video',
            name: 'Trim Video',
            description: 'Cut video to specific duration',
            category: 'Editing'
        },
        {
            id: 'merge-video',
            name: 'Merge Videos',
            description: 'Combine multiple video files',
            category: 'Editing'
        }
    ];

    const handleFileSelect = (event) => {
        const files = Array.from(event.target.files);
        setSelectedFiles(files);
    };

    const handleDragOver = (event) => {
        event.preventDefault();
    };

    const handleDrop = (event) => {
        event.preventDefault();
        const files = Array.from(event.dataTransfer.files);
        setSelectedFiles(files);
    };

    const handleProcess = async () => {
        if (!selectedTool || selectedFiles.length === 0) return;

        setIsProcessing(true);
        setResults([]);

        try {
            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            formData.append('tool', selectedTool);
            formData.append('settings', JSON.stringify(settings));

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch('/api/video-tools/process', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                setResults(data.results);
            } else {
                alert('Processing failed: ' + data.message);
            }
        } catch (error) {
            console.error('Processing error:', error);
            alert('Processing failed: ' + error.message);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleDownload = async (resultId, filename) => {
        try {
            const response = await fetch(`/api/video-tools/download/${resultId}`);
            
            if (!response.ok) {
                throw new Error(`Download failed: ${response.status} ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename || 'converted-video';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Download error:', error);
            alert('Download failed: ' + error.message);
        }
    };

    const groupedTools = videoTools.reduce((acc, tool) => {
        if (!acc[tool.category]) {
            acc[tool.category] = [];
        }
        acc[tool.category].push(tool);
        return acc;
    }, {});

    return (
        <div className="min-h-screen bg-gradient-to-br from-red-50 to-orange-100 p-6">
            <div className="max-w-7xl mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-gray-900 mb-4">
                        Video Converter
                    </h1>
                    <p className="text-lg text-gray-600">
                        Convert, compress, and edit your video files with professional tools
                    </p>
                </div>

                {/* Tool Selection */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Choose Video Tool</h2>
                    
                    {Object.entries(groupedTools).map(([category, tools]) => (
                        <div key={category} className="mb-6">
                            <h3 className="text-lg font-medium text-gray-700 mb-3">{category}</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                {tools.map((tool) => (
                                    <div
                                        key={tool.id}
                                        onClick={() => setSelectedTool(tool.id)}
                                        className={`p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 ${
                                            selectedTool === tool.id
                                                ? 'border-red-500 bg-red-50'
                                                : 'border-gray-200 hover:border-red-300 hover:bg-gray-50'
                                        }`}
                                    >
                                        <h4 className="font-medium text-gray-900">{tool.name}</h4>
                                        <p className="text-sm text-gray-600 mt-1">{tool.description}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Settings Panel */}
                {selectedTool && (
                    <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h2 className="text-2xl font-semibold text-gray-800 mb-6">Video Settings</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Quality
                                </label>
                                <select
                                    value={settings.quality}
                                    onChange={(e) => setSettings({...settings, quality: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="ultra">Ultra</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Resolution
                                </label>
                                <select
                                    value={settings.resolution}
                                    onChange={(e) => setSettings({...settings, resolution: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <option value="480p">480p</option>
                                    <option value="720p">720p</option>
                                    <option value="1080p">1080p</option>
                                    <option value="1440p">1440p</option>
                                    <option value="4k">4K</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Frame Rate (FPS)
                                </label>
                                <select
                                    value={settings.fps}
                                    onChange={(e) => setSettings({...settings, fps: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <option value="24">24</option>
                                    <option value="30">30</option>
                                    <option value="60">60</option>
                                    <option value="120">120</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Codec
                                </label>
                                <select
                                    value={settings.codec}
                                    onChange={(e) => setSettings({...settings, codec: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <option value="h264">H.264</option>
                                    <option value="h265">H.265</option>
                                    <option value="vp9">VP9</option>
                                    <option value="av1">AV1</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Bitrate
                                </label>
                                <select
                                    value={settings.bitrate}
                                    onChange={(e) => setSettings({...settings, bitrate: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <option value="auto">Auto</option>
                                    <option value="1000k">1 Mbps</option>
                                    <option value="2000k">2 Mbps</option>
                                    <option value="5000k">5 Mbps</option>
                                    <option value="10000k">10 Mbps</option>
                                </select>
                            </div>

                            <div>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={settings.compress}
                                        onChange={(e) => setSettings({...settings, compress: e.target.checked})}
                                        className="mr-2"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Compress for Web</span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {/* File Upload */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Upload Video Files</h2>
                    
                    <div
                        onDragOver={handleDragOver}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                        className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-red-400 transition-colors"
                    >
                        <div className="text-gray-600">
                            <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <p className="text-lg mb-2">Drop video files here or click to browse</p>
                            <p className="text-sm">Supports MP4, AVI, MKV, MOV, WMV formats</p>
                        </div>
                    </div>

                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept="video/*"
                        onChange={handleFileSelect}
                        className="hidden"
                    />

                    {selectedFiles.length > 0 && (
                        <div className="mt-6">
                            <h3 className="text-lg font-medium text-gray-800 mb-3">Selected Files:</h3>
                            <div className="space-y-2">
                                {selectedFiles.map((file, index) => (
                                    <div key={index} className="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                                        <span className="text-sm text-gray-700">{file.name}</span>
                                        <span className="text-xs text-gray-500">{(file.size / 1024 / 1024).toFixed(2)} MB</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Process Button */}
                {selectedTool && selectedFiles.length > 0 && (
                    <div className="text-center mb-8">
                        <button
                            onClick={handleProcess}
                            disabled={isProcessing}
                            className="bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white font-semibold py-3 px-8 rounded-lg transition-colors duration-200"
                        >
                            {isProcessing ? 'Processing...' : 'Convert Video'}
                        </button>
                    </div>
                )}

                {/* Results */}
                {results.length > 0 && (
                    <div className="bg-white rounded-xl shadow-lg p-6">
                        <h2 className="text-2xl font-semibold text-gray-800 mb-6">Conversion Results</h2>
                        
                        <div className="space-y-4">
                            {results.map((result, index) => (
                                <div key={index} className="flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                                    <div>
                                        <h3 className="font-medium text-gray-900">{result.filename}</h3>
                                        <p className="text-sm text-gray-600">Status: {result.status}</p>
                                    </div>
                                    
                                    {result.status === 'completed' && (
                                        <button
                                            onClick={() => handleDownload(result.id, result.filename)}
                                            className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors duration-200"
                                        >
                                            Download
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default VideoConverter;
