import React, { useState, useRef } from 'react';

const AudioConverter = () => {
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [selectedTool, setSelectedTool] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [settings, setSettings] = useState({
        quality: 'high',
        bitrate: '320',
        sampleRate: '44100',
        channels: 'stereo',
        normalize: false,
        fadeIn: 0,
        fadeOut: 0
    });
    
    const fileInputRef = useRef(null);

    const audioTools = [
        {
            id: 'mp3-to-wav',
            name: 'MP3 to WAV',
            description: 'Convert MP3 audio to WAV format',
            category: 'Format Conversion'
        },
        {
            id: 'wav-to-mp3',
            name: 'WAV to MP3',
            description: 'Convert WAV audio to MP3 format',
            category: 'Format Conversion'
        },
        {
            id: 'flac-to-mp3',
            name: 'FLAC to MP3',
            description: 'Convert FLAC audio to MP3 format',
            category: 'Format Conversion'
        },
        {
            id: 'mp3-to-aac',
            name: 'MP3 to AAC',
            description: 'Convert MP3 audio to AAC format',
            category: 'Format Conversion'
        },
        {
            id: 'compress-audio',
            name: 'Compress Audio',
            description: 'Reduce audio file size',
            category: 'Optimization'
        },
        {
            id: 'normalize-audio',
            name: 'Normalize Audio',
            description: 'Normalize audio levels',
            category: 'Enhancement'
        },
        {
            id: 'trim-audio',
            name: 'Trim Audio',
            description: 'Cut audio to specific duration',
            category: 'Editing'
        },
        {
            id: 'merge-audio',
            name: 'Merge Audio',
            description: 'Combine multiple audio files',
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
            
            const response = await fetch('/api/audio-tools/process', {
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
            const response = await fetch(`/api/audio-tools/download/${resultId}`);
            
            if (!response.ok) {
                throw new Error(`Download failed: ${response.status} ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename || 'converted-audio';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Download error:', error);
            alert('Download failed: ' + error.message);
        }
    };

    const groupedTools = audioTools.reduce((acc, tool) => {
        if (!acc[tool.category]) {
            acc[tool.category] = [];
        }
        acc[tool.category].push(tool);
        return acc;
    }, {});

    return (
        <div className="min-h-screen bg-gradient-to-br from-purple-50 to-pink-100 p-6">
            <div className="max-w-7xl mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-gray-900 mb-4">
                        Audio Converter
                    </h1>
                    <p className="text-lg text-gray-600">
                        Convert, compress, and enhance your audio files with professional tools
                    </p>
                </div>

                {/* Tool Selection */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Choose Audio Tool</h2>
                    
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
                                                ? 'border-purple-500 bg-purple-50'
                                                : 'border-gray-200 hover:border-purple-300 hover:bg-gray-50'
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
                        <h2 className="text-2xl font-semibold text-gray-800 mb-6">Audio Settings</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Quality
                                </label>
                                <select
                                    value={settings.quality}
                                    onChange={(e) => setSettings({...settings, quality: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="lossless">Lossless</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Bitrate (kbps)
                                </label>
                                <select
                                    value={settings.bitrate}
                                    onChange={(e) => setSettings({...settings, bitrate: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="128">128</option>
                                    <option value="192">192</option>
                                    <option value="256">256</option>
                                    <option value="320">320</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Sample Rate (Hz)
                                </label>
                                <select
                                    value={settings.sampleRate}
                                    onChange={(e) => setSettings({...settings, sampleRate: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="22050">22050</option>
                                    <option value="44100">44100</option>
                                    <option value="48000">48000</option>
                                    <option value="96000">96000</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Channels
                                </label>
                                <select
                                    value={settings.channels}
                                    onChange={(e) => setSettings({...settings, channels: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                >
                                    <option value="mono">Mono</option>
                                    <option value="stereo">Stereo</option>
                                </select>
                            </div>

                            <div>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={settings.normalize}
                                        onChange={(e) => setSettings({...settings, normalize: e.target.checked})}
                                        className="mr-2"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Normalize Audio</span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {/* File Upload */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Upload Audio Files</h2>
                    
                    <div
                        onDragOver={handleDragOver}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                        className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-purple-400 transition-colors"
                    >
                        <div className="text-gray-600">
                            <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                            <p className="text-lg mb-2">Drop audio files here or click to browse</p>
                            <p className="text-sm">Supports MP3, WAV, FLAC, AAC, OGG formats</p>
                        </div>
                    </div>

                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept="audio/*"
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
                            className="bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white font-semibold py-3 px-8 rounded-lg transition-colors duration-200"
                        >
                            {isProcessing ? 'Processing...' : 'Convert Audio'}
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

export default AudioConverter;
