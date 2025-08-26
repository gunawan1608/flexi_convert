import React, { useState, useRef } from 'react';

const ImageConverter = () => {
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [selectedTool, setSelectedTool] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [settings, setSettings] = useState({
        quality: 'high',
        format: 'auto',
        resize: false,
        width: '',
        height: '',
        compression: 80
    });
    
    const fileInputRef = useRef(null);

    const imageTools = [
        {
            id: 'jpg-to-png',
            name: 'JPG to PNG',
            description: 'Convert JPG images to PNG format',
            category: 'Format Conversion'
        },
        {
            id: 'png-to-jpg',
            name: 'PNG to JPG',
            description: 'Convert PNG images to JPG format',
            category: 'Format Conversion'
        },
        {
            id: 'webp-to-jpg',
            name: 'WebP to JPG',
            description: 'Convert WebP images to JPG format',
            category: 'Format Conversion'
        },
        {
            id: 'jpg-to-webp',
            name: 'JPG to WebP',
            description: 'Convert JPG images to WebP format',
            category: 'Format Conversion'
        },
        {
            id: 'resize-image',
            name: 'Resize Image',
            description: 'Resize images to specific dimensions',
            category: 'Image Editing'
        },
        {
            id: 'compress-image',
            name: 'Compress Image',
            description: 'Reduce image file size',
            category: 'Optimization'
        },
        {
            id: 'crop-image',
            name: 'Crop Image',
            description: 'Crop images to specific area',
            category: 'Image Editing'
        },
        {
            id: 'rotate-image',
            name: 'Rotate Image',
            description: 'Rotate images by degrees',
            category: 'Image Editing'
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

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch('/api/image-tools/process', {
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
            const response = await fetch(`/api/image-tools/download/${resultId}`);
            
            if (!response.ok) {
                throw new Error(`Download failed: ${response.status} ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename || 'converted-image';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Download error:', error);
            alert('Download failed: ' + error.message);
        }
    };

    const groupedTools = imageTools.reduce((acc, tool) => {
        if (!acc[tool.category]) {
            acc[tool.category] = [];
        }
        acc[tool.category].push(tool);
        return acc;
    }, {});

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-6">
            <div className="max-w-7xl mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-gray-900 mb-4">
                        Image Converter
                    </h1>
                    <p className="text-lg text-gray-600">
                        Convert, resize, compress, and edit your images with professional tools
                    </p>
                </div>

                {/* Tool Selection */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Choose Image Tool</h2>
                    
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
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50'
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
                        <h2 className="text-2xl font-semibold text-gray-800 mb-6">Conversion Settings</h2>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Quality
                                </label>
                                <select
                                    value={settings.quality}
                                    onChange={(e) => setSettings({...settings, quality: e.target.value})}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="maximum">Maximum</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Compression (%)
                                </label>
                                <input
                                    type="range"
                                    min="10"
                                    max="100"
                                    value={settings.compression}
                                    onChange={(e) => setSettings({...settings, compression: e.target.value})}
                                    className="w-full"
                                />
                                <div className="text-center text-sm text-gray-600">{settings.compression}%</div>
                            </div>

                            <div>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={settings.resize}
                                        onChange={(e) => setSettings({...settings, resize: e.target.checked})}
                                        className="mr-2"
                                    />
                                    <span className="text-sm font-medium text-gray-700">Custom Size</span>
                                </label>
                                
                                {settings.resize && (
                                    <div className="mt-2 flex gap-2">
                                        <input
                                            type="number"
                                            placeholder="Width"
                                            value={settings.width}
                                            onChange={(e) => setSettings({...settings, width: e.target.value})}
                                            className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        <input
                                            type="number"
                                            placeholder="Height"
                                            value={settings.height}
                                            onChange={(e) => setSettings({...settings, height: e.target.value})}
                                            className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* File Upload */}
                <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 className="text-2xl font-semibold text-gray-800 mb-6">Upload Images</h2>
                    
                    <div
                        onDragOver={handleDragOver}
                        onDrop={handleDrop}
                        onClick={() => fileInputRef.current?.click()}
                        className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-blue-400 transition-colors"
                    >
                        <div className="text-gray-600">
                            <svg className="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p className="text-lg mb-2">Drop image files here or click to browse</p>
                            <p className="text-sm">Supports JPG, PNG, WebP, GIF, BMP formats</p>
                        </div>
                    </div>

                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept="image/*"
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
                            className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-semibold py-3 px-8 rounded-lg transition-colors duration-200"
                        >
                            {isProcessing ? 'Processing...' : 'Convert Images'}
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

export default ImageConverter;
