import React, { useState, useRef, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

const AudioConverter = () => {
    const [selectedTool, setSelectedTool] = useState(null);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [toolSettings, setToolSettings] = useState({
        quality: 'medium',
        bitrate: '192',
        sampleRate: '44100',
        channels: 'stereo'
    });
    const [activeCategory, setActiveCategory] = useState('conversion');

    // Simplified tool categories - only Format Conversion and Audio Enhancement
    const toolCategories = [
        {
            id: 'conversion',
            title: 'Format Conversion',
            icon: '🔄',
            gradient: 'from-purple-400 via-purple-500 to-purple-600',
            description: 'Convert between different audio formats',
            tools: [
                { 
                    id: 'mp3-to-wav', 
                    name: 'MP3 → WAV', 
                    description: 'Convert MP3 to high-quality WAV format', 
                    icon: '🎵',
                    color: 'from-blue-500 to-blue-600',
                    formats: ['.mp3']
                },
                { 
                    id: 'wav-to-mp3', 
                    name: 'WAV → MP3', 
                    description: 'Compress WAV files to MP3', 
                    icon: '🎶',
                    color: 'from-green-500 to-green-600',
                    formats: ['.wav']
                },
                { 
                    id: 'flac-to-mp3', 
                    name: 'FLAC → MP3', 
                    description: 'Convert lossless FLAC to MP3', 
                    icon: '🎼',
                    color: 'from-orange-500 to-orange-600',
                    formats: ['.flac']
                },
                { 
                    id: 'aac-to-mp3', 
                    name: 'AAC → MP3', 
                    description: 'Convert AAC files to MP3', 
                    icon: '🎧',
                    color: 'from-purple-500 to-purple-600',
                    formats: ['.aac', '.m4a']
                },
                { 
                    id: 'ogg-to-mp3', 
                    name: 'OGG → MP3', 
                    description: 'Convert OGG files to MP3', 
                    icon: '🔊',
                    color: 'from-teal-500 to-teal-600',
                    formats: ['.ogg']
                },
                { 
                    id: 'mp3-to-flac', 
                    name: 'MP3 → FLAC', 
                    description: 'Convert MP3 to lossless FLAC', 
                    icon: '💿',
                    color: 'from-indigo-500 to-indigo-600',
                    formats: ['.mp3']
                },
                { 
                    id: 'mp3-to-aac', 
                    name: 'MP3 → AAC', 
                    description: 'Convert MP3 to AAC format', 
                    icon: '🎯',
                    color: 'from-red-500 to-red-600',
                    formats: ['.mp3']
                },
                { 
                    id: 'wav-to-flac', 
                    name: 'WAV → FLAC', 
                    description: 'Convert WAV to lossless FLAC', 
                    icon: '💽',
                    color: 'from-emerald-500 to-emerald-600',
                    formats: ['.wav']
                }
            ]
        },
        {
            id: 'enhancement',
            title: 'Audio Enhancement',
            icon: '🎚️',
            gradient: 'from-blue-400 via-blue-500 to-blue-600',
            description: 'Enhance and optimize audio quality',
            tools: [
                { 
                    id: 'compress-audio', 
                    name: 'Compress Audio', 
                    description: 'Reduce audio file size while maintaining quality', 
                    icon: '🗜️',
                    color: 'from-cyan-500 to-cyan-600',
                    formats: ['.mp3', '.wav', '.flac', '.aac', '.m4a', '.ogg']
                },
                { 
                    id: 'noise-reduction', 
                    name: 'Noise Reduction', 
                    description: 'Remove background noise and improve clarity', 
                    icon: '🔇',
                    color: 'from-pink-500 to-pink-600',
                    formats: ['.mp3', '.wav', '.flac', '.aac', '.m4a', '.ogg']
                }
            ]
        }
    ];

    // File upload handling with enhanced validation
    const onDrop = useCallback((acceptedFiles) => {
        const validFiles = [];
        const rejectedFiles = [];
        
        acceptedFiles.forEach(file => {
            if (!selectedTool) {
                rejectedFiles.push({ file, reason: 'Pilih tool terlebih dahulu' });
                return;
            }
            
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            const tool = toolCategories
                .flatMap(cat => cat.tools)
                .find(t => t.id === selectedTool.id);
            
            if (!tool?.formats.includes(fileExtension)) {
                rejectedFiles.push({ 
                    file, 
                    reason: `Format ${fileExtension} tidak didukung untuk ${selectedTool.name}. Format yang didukung: ${tool?.formats.join(', ')}` 
                });
                return;
            }
            
            if (file.size > 100 * 1024 * 1024) {
                rejectedFiles.push({ file, reason: 'File terlalu besar (maksimal 100MB)' });
                return;
            }
            
            validFiles.push(file);
        });
        
        if (rejectedFiles.length > 0) {
            const errorMessages = rejectedFiles.map(r => `${r.file.name}: ${r.reason}`).join('\n');
            alert('Beberapa file tidak dapat diupload:\n\n' + errorMessages);
        }
        
        setSelectedFiles(prev => [...prev, ...validFiles]);
    }, [selectedTool, toolCategories]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: selectedTool ? {
            'audio/mpeg': ['.mp3'],
            'audio/wav': ['.wav'],
            'audio/flac': ['.flac'],
            'audio/aac': ['.aac'],
            'audio/ogg': ['.ogg'],
            'audio/x-ms-wma': ['.wma']
        } : {},
        multiple: true,
        maxSize: 100 * 1024 * 1024,
        disabled: !selectedTool
    });

    const removeFile = (index) => {
        setSelectedFiles(prev => prev.filter((_, i) => i !== index));
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handleToolSelect = (tool) => {
        setSelectedTool(tool);
        setSelectedFiles([]);
        setResults([]);
        setToolSettings({});
    };

    const processFiles = async () => {
        if (!selectedTool || selectedFiles.length === 0) return;

        setIsProcessing(true);
        
        try {
            const formData = new FormData();
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });
            formData.append('tool', selectedTool.id);
            
            // Send settings as individual form fields instead of JSON string
            Object.keys(toolSettings).forEach(key => {
                formData.append(`settings[${key}]`, toolSettings[key]);
            });
            
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));

            const response = await fetch('/api/audio-tools/process', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });

            const result = await response.json();
            
            if (result.success) {
                setResults(result.results || []);
            } else {
                alert('Processing failed: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Processing error:', error);
            alert('An error occurred during processing. Please try again.');
        } finally {
            setIsProcessing(false);
        }
    };

    const getActionText = (toolId) => {
        const actionMap = {
            'mp3-to-wav': 'Convert to WAV',
            'wav-to-mp3': 'Convert to MP3',
            'flac-to-mp3': 'Convert to MP3',
            'aac-to-mp3': 'Convert to MP3',
            'ogg-to-mp3': 'Convert to MP3',
            'mp3-to-flac': 'Convert to FLAC',
            'mp3-to-aac': 'Convert to AAC',
            'wav-to-flac': 'Convert to FLAC',
            'compress-audio': 'Compress Audio',
            'noise-reduction': 'Reduce Noise'
        };
        return actionMap[toolId] || 'Process Audio';
    };

    const handleDownload = async (downloadUrl, filename) => {
        try {
            console.log('Attempting download:', downloadUrl);
            
            const response = await fetch(downloadUrl, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/octet-stream',
                },
                credentials: 'same-origin'
            });

            console.log('Download response status:', response.status);
            console.log('Download response headers:', Object.fromEntries(response.headers.entries()));

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Download failed with response:', errorText);
                throw new Error(`Download failed: ${response.status} ${response.statusText}`);
            }

            const blob = await response.blob();
            console.log('Downloaded blob size:', blob.size);
            
            if (blob.size === 0) {
                throw new Error('Downloaded file is empty');
            }

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename || 'download';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            console.log('Download completed successfully');
        } catch (error) {
            console.error('Download error details:', error);
            alert(`Download failed: ${error.message}. Check console for details.`);
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50">
            <div className="container mx-auto px-4 py-8 space-y-8">
                {/* Enhanced Header */}
                <div className="text-center space-y-6">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-500 to-pink-600 rounded-3xl shadow-lg transform rotate-3 hover:rotate-0 transition-transform duration-300">
                        <span className="text-3xl">🎵</span>
                    </div>
                    <div>
                        <h1 className="text-4xl lg:text-6xl font-bold bg-gradient-to-r from-gray-900 via-purple-900 to-pink-900 bg-clip-text text-transparent mb-4">
                            Audio Converter
                        </h1>
                        <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                            Transform your audio files with professional-grade conversion tools. 
                            Fast, secure, and high-quality results every time.
                        </p>
                    </div>
                    
                    {/* Stats */}
                    <div className="flex justify-center space-x-8 text-center">
                        <div className="bg-white/70 backdrop-blur-sm rounded-2xl px-6 py-4 shadow-lg">
                            <div className="text-2xl font-bold text-purple-600">{selectedFiles.length}</div>
                            <div className="text-sm text-gray-600">Files Selected</div>
                        </div>
                        <div className="bg-white/70 backdrop-blur-sm rounded-2xl px-6 py-4 shadow-lg">
                            <div className="text-2xl font-bold text-green-600">{results.filter(r => r.status === 'completed').length}</div>
                            <div className="text-sm text-gray-600">Completed</div>
                        </div>
                        <div className="bg-white/70 backdrop-blur-sm rounded-2xl px-6 py-4 shadow-lg">
                            <div className="text-2xl font-bold text-pink-600">∞</div>
                            <div className="text-sm text-gray-600">Free Usage</div>
                        </div>
                    </div>
                </div>

                {/* Category Navigation */}
                <div className="flex justify-center">
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-2 shadow-lg border border-white/20">
                        <div className="flex space-x-2">
                            {toolCategories.map((category) => (
                                <button
                                    key={category.id}
                                    onClick={() => setActiveCategory(category.id)}
                                    className={`px-6 py-3 rounded-xl font-semibold transition-all duration-300 ${
                                        activeCategory === category.id
                                            ? `bg-gradient-to-r ${category.gradient} text-white shadow-lg transform scale-105`
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-white/50'
                                    }`}
                                >
                                    <span className="mr-2">{category.icon}</span>
                                    {category.title}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Active Category Tools */}
                {toolCategories.map((category) => (
                    activeCategory === category.id && (
                        <div key={category.id} className="space-y-6">
                            <div className="text-center">
                                <h2 className="text-3xl font-bold text-gray-900 mb-2">{category.title}</h2>
                                <p className="text-gray-600">{category.description}</p>
                            </div>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                                {category.tools.map((tool, index) => (
                                    <div
                                        key={tool.id}
                                        className={`group cursor-pointer transform transition-all duration-300 hover:scale-105 ${
                                            selectedTool?.id === tool.id ? 'scale-105' : ''
                                        }`}
                                        style={{ animationDelay: `${index * 100}ms` }}
                                        onClick={() => handleToolSelect(tool)}
                                    >
                                        <div className={`relative overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 ${
                                            selectedTool?.id === tool.id 
                                                ? `bg-gradient-to-br ${tool.color} text-white ring-4 ring-purple-300` 
                                                : 'bg-white hover:bg-gray-50'
                                        }`}>
                                            {/* Background Pattern */}
                                            <div className="absolute inset-0 opacity-5">
                                                <div className="absolute inset-0 bg-gradient-to-br from-transparent via-white to-transparent transform rotate-45"></div>
                                            </div>
                                            
                                            <div className="relative p-6 text-center space-y-4">
                                                <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl ${
                                                    selectedTool?.id === tool.id 
                                                        ? 'bg-white/20' 
                                                        : `bg-gradient-to-br ${tool.color}`
                                                } shadow-lg`}>
                                                    <span className={`text-2xl ${
                                                        selectedTool?.id === tool.id ? 'text-white' : 'text-white'
                                                    }`}>
                                                        {tool.icon}
                                                    </span>
                                                </div>
                                                
                                                <div>
                                                    <h3 className={`font-bold text-lg ${
                                                        selectedTool?.id === tool.id ? 'text-white' : 'text-gray-900'
                                                    }`}>
                                                        {tool.name}
                                                    </h3>
                                                    <p className={`text-sm ${
                                                        selectedTool?.id === tool.id ? 'text-white/80' : 'text-gray-600'
                                                    }`}>
                                                        {tool.description}
                                                    </p>
                                                </div>
                                                
                                                <div className={`text-xs ${
                                                    selectedTool?.id === tool.id ? 'text-white/60' : 'text-gray-400'
                                                }`}>
                                                    {tool.formats.join(', ')}
                                                </div>
                                            </div>
                                            
                                            {selectedTool?.id === tool.id && (
                                                <div className="absolute top-2 right-2">
                                                    <div className="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                                        <svg className="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )
                ))}

                {/* File Upload Section */}
                {selectedTool && (
                    <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-white/20 overflow-hidden">
                        <div className="p-8 space-y-6">
                            <div className="text-center">
                                <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${selectedTool.color} shadow-lg mb-4`}>
                                    <span className="text-2xl text-white">{selectedTool.icon}</span>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-900">{selectedTool.name}</h3>
                                <p className="text-gray-600">{selectedTool.description}</p>
                            </div>
                            
                            <div
                                {...getRootProps()}
                                className={`relative border-2 border-dashed rounded-3xl p-12 text-center transition-all duration-300 cursor-pointer ${
                                    isDragActive 
                                        ? 'border-purple-400 bg-purple-50/50 scale-105' 
                                        : 'border-gray-300 hover:border-purple-400 hover:bg-purple-50/30'
                                }`}
                            >
                                <input {...getInputProps()} />
                                <div className="space-y-6">
                                    <div className={`w-24 h-24 mx-auto rounded-full flex items-center justify-center transition-all duration-300 ${
                                        isDragActive 
                                            ? 'bg-gradient-to-br from-purple-400 to-purple-600 scale-110' 
                                            : 'bg-gradient-to-br from-gray-100 to-gray-200'
                                    }`}>
                                        <svg className={`w-12 h-12 transition-colors duration-300 ${
                                            isDragActive ? 'text-white' : 'text-gray-400'
                                        }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-gray-900 mb-2">
                                            {isDragActive ? 'Drop files here!' : 'Drag & drop your audio files'}
                                        </p>
                                        <p className="text-gray-600">
                                            or <span className="text-purple-600 font-semibold">click to browse</span>
                                        </p>
                                    </div>
                                    <div className="text-sm text-gray-500 space-y-1">
                                        <p>Supported formats: {selectedTool.formats.join(', ')}</p>
                                        <p>Maximum file size: 100MB per file</p>
                                    </div>
                                </div>
                            </div>

                            {/* Selected Files */}
                            {selectedFiles.length > 0 && (
                                <div className="space-y-4">
                                    <h4 className="font-semibold text-gray-900">Selected Files ({selectedFiles.length})</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-64 overflow-y-auto">
                                        {selectedFiles.map((file, index) => (
                                            <div key={index} className="flex items-center justify-between p-4 bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200">
                                                <div className="flex items-center space-x-3">
                                                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center bg-gradient-to-br ${selectedTool.color}`}>
                                                        <span className="text-white text-sm font-bold">
                                                            {file.name.split('.').pop().toUpperCase()}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-gray-900 truncate max-w-48">{file.name}</p>
                                                        <p className="text-sm text-gray-500">{formatFileSize(file.size)}</p>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => removeFile(index)}
                                                    className="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200"
                                                >
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="flex flex-col sm:flex-row gap-4">
                                <button
                                    onClick={processFiles}
                                    disabled={selectedFiles.length === 0 || isProcessing}
                                    className={`flex-1 px-8 py-4 rounded-xl font-semibold transition-all duration-300 transform ${
                                        selectedFiles.length === 0 || isProcessing
                                            ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                            : `bg-gradient-to-r ${selectedTool.color} text-white hover:shadow-lg hover:scale-105 active:scale-95`
                                    }`}
                                >
                                    {isProcessing ? (
                                        <span className="flex items-center justify-center">
                                            <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Processing...
                                        </span>
                                    ) : (
                                        <span className="flex items-center justify-center">
                                            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                            </svg>
                                            {getActionText(selectedTool.id)}
                                        </span>
                                    )}
                                </button>
                                
                                <button
                                    onClick={() => {
                                        setSelectedFiles([]);
                                        setResults([]);
                                    }}
                                    className="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200"
                                >
                                    Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Results Section */}
                {results.length > 0 && (
                    <div className="bg-white/80 backdrop-blur-sm rounded-3xl shadow-xl border border-white/20">
                        <div className="p-8 space-y-6">
                            <h3 className="text-2xl font-bold text-gray-900">Conversion Results</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {results.map((result, index) => (
                                    <div key={index} className="border border-gray-200 rounded-xl p-6 bg-white shadow-sm hover:shadow-md transition-shadow duration-200">
                                        <div className="flex items-center justify-between mb-4">
                                            <span className="font-medium text-gray-900 truncate">{result.filename}</span>
                                            <span className={`text-sm font-medium px-3 py-1 rounded-full ${
                                                result.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                result.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                'bg-blue-100 text-blue-800'
                                            }`}>
                                                {result.status === 'completed' ? 'Complete' :
                                                 result.status === 'failed' ? 'Failed' :
                                                 'Processing...'}
                                            </span>
                                        </div>
                                        {result.status === 'completed' && result.download_url && (
                                            <button
                                                onClick={() => handleDownload(result.download_url, result.filename)}
                                                className={`w-full inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r ${selectedTool.color} text-white font-medium rounded-lg hover:shadow-lg transition-all duration-200 transform hover:scale-105`}
                                            >
                                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Download File
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default AudioConverter;
