import React, { useState, useRef, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

const ImageConverter = () => {
    const [selectedTool, setSelectedTool] = useState(null);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [toolSettings, setToolSettings] = useState({});
    const [customSettings, setCustomSettings] = useState({
        width: 800,
        height: 600,
        angle: 90,
        maintainAspectRatio: true
    });
    const [activeCategory, setActiveCategory] = useState('image-conversion');
    const [processingStatus, setProcessingStatus] = useState('');
    const [downloadUrl, setDownloadUrl] = useState(null);
    const [outputFilename, setOutputFilename] = useState(null);
    const [errorMessage, setErrorMessage] = useState(null);

    // Tool categories with format conversion and image tools
    const toolCategories = [
        {
            id: 'image-conversion',
            title: 'Image Format Conversion',
            icon: '🖼️',
            gradient: 'from-blue-400 via-blue-500 to-blue-600',
            description: 'Convert between different image formats',
            tools: [
                { 
                    id: 'png-to-jpg',
                    name: 'PNG → JPG', 
                    description: 'Convert PNG images to JPG format', 
                    icon: '🔄',
                    color: 'from-blue-500 to-blue-600',
                    formats: ['.png']
                },
                { 
                    id: 'jpg-to-png', 
                    name: 'JPG → PNG', 
                    description: 'Convert JPG images to PNG format', 
                    icon: '🔄',
                    color: 'from-green-500 to-green-600',
                    formats: ['.jpg', '.jpeg']
                },
                { 
                    id: 'webp-to-png', 
                    name: 'WebP → PNG', 
                    description: 'Convert WebP images to PNG format', 
                    icon: '🔄',
                    color: 'from-purple-500 to-purple-600',
                    formats: ['.webp']
                },
                { 
                    id: 'png-to-webp', 
                    name: 'PNG → WebP', 
                    description: 'Convert PNG images to WebP format', 
                    icon: '🔄',
                    color: 'from-orange-500 to-orange-600',
                    formats: ['.png']
                }
            ]
        },
        {
            id: 'image-tools',
            title: 'Image Tools',
            icon: '🛠️',
            gradient: 'from-indigo-400 via-indigo-500 to-indigo-600',
            description: 'Advanced image manipulation tools',
            tools: [
                { 
                    id: 'resize-image', 
                    name: 'Resize Image', 
                    description: 'Resize images to custom dimensions', 
                    icon: '📏',
                    color: 'from-teal-500 to-teal-600',
                    formats: ['.jpg', '.jpeg', '.png', '.webp']
                },
                { 
                    id: 'rotate-image', 
                    name: 'Rotate Image', 
                    description: 'Rotate images to correct orientation', 
                    icon: '🔄',
                    color: 'from-pink-500 to-pink-600',
                    formats: ['.jpg', '.jpeg', '.png', '.webp']
                }
            ]
        }
    ];

    // File upload handling with enhanced validation
    const onDrop = useCallback((acceptedFiles) => {
        const validFiles = acceptedFiles.filter(file => {
            if (!selectedTool) return false;
            
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            const tool = toolCategories
                .flatMap(cat => cat.tools)
                .find(t => t.id === selectedTool.id);
            
            return tool?.formats.includes(fileExtension) && file.size <= 50 * 1024 * 1024; // 50MB limit
        });
        
        setSelectedFiles(prev => [...prev, ...validFiles]);
    }, [selectedTool]);

    // Get file accept types based on selected tool
    const getAcceptTypes = () => {
        if (!selectedTool) return {};
        
        const toolAcceptMap = {
            'png-to-jpg': { 'image/png': ['.png'] },
            'jpg-to-png': { 'image/jpeg': ['.jpg', '.jpeg'] },
            'webp-to-png': { 'image/webp': ['.webp'] },
            'png-to-webp': { 'image/png': ['.png'] },
            'resize-image': { 'image/*': ['.jpg', '.jpeg', '.png', '.webp'] },
            'rotate-image': { 'image/*': ['.jpg', '.jpeg', '.png', '.webp'] }
        };
        
        return toolAcceptMap[selectedTool.id] || {};
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: getAcceptTypes(),
        multiple: false,
        maxSize: 50 * 1024 * 1024,
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
        setProcessingStatus('');
        setDownloadUrl(null);
        setOutputFilename(null);
        setErrorMessage(null);
    };

    const processFiles = async () => {
        if (!selectedTool || selectedFiles.length === 0) return;

        setIsProcessing(true);
        setProcessingStatus('Uploading files...');
        setErrorMessage(null);
        setDownloadUrl(null);
        
        try {
            const formData = new FormData();
            // Store original filenames
            const originalFilenames = [];
            
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
                originalFilenames.push(file.name);
            });
            
            // Determine output format based on tool
            let outputFormat = '';
            if (selectedTool.id.includes('to-')) {
                outputFormat = selectedTool.id.split('to-')[1];
                if (outputFormat === 'jpg') outputFormat = 'jpeg'; // Standardize jpg to jpeg
            } else if (selectedTool.id === 'resize-image' || selectedTool.id === 'rotate-image') {
                // For tools that don't change format, keep original format
                const fileExt = selectedFiles[0].name.split('.').pop().toLowerCase();
                outputFormat = fileExt === 'jpg' ? 'jpeg' : fileExt;
            }
            
            formData.append('tool', selectedTool.id);
            formData.append('output_format', outputFormat);
            formData.append('original_filenames', JSON.stringify(originalFilenames));
            
            // Add custom settings for resize and rotate tools
            if (selectedTool.id === 'resize-image') {
                formData.append('settings[width]', customSettings.width);
                formData.append('settings[height]', customSettings.height);
                formData.append('settings[maintainAspectRatio]', customSettings.maintainAspectRatio);
            } else if (selectedTool.id === 'rotate-image') {
                formData.append('settings[angle]', customSettings.angle);
            }

            setProcessingStatus('Processing conversion...');

            const response = await fetch('/api/image-tools/process', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                setProcessingStatus('Conversion completed!');
                
                // Handle multiple files results or single file result
                if (result.results && Array.isArray(result.results) && result.results.length > 0) {
                    // Multiple files processing
                    const firstResult = result.results[0];
                    if (firstResult.status === 'completed') {
                        setDownloadUrl(firstResult.download_url);
                        setOutputFilename(firstResult.output_filename);
                        setResults(result.results);
                    }
                } else {
                    // Single file processing (fallback)
                    setDownloadUrl(result.download_url || `/storage/${result.output_path}`);
                    setOutputFilename(result.output_filename);
                    setResults([{
                        filename: result.output_filename,
                        status: 'completed',
                        download_url: result.download_url || `/storage/${result.output_path}`,
                        processing_id: result.processing_id
                    }]);
                }
            } else {
                throw new Error(result.message || 'Conversion failed');
            }
        } catch (error) {
            console.error('Processing error:', error);
            setErrorMessage(error.message || 'An error occurred during processing');
            setProcessingStatus('Conversion failed');
        } finally {
            setIsProcessing(false);
        }
    };

    const getActionText = (toolId) => {
        const actionMap = {
            'png-to-jpg': 'Konversi ke JPG',
            'jpg-to-png': 'Konversi ke PNG',
            'webp-to-png': 'Konversi ke PNG',
            'png-to-webp': 'Konversi ke WebP',
            'resize-image': 'Resize Gambar',
            'rotate-image': 'Putar Gambar'
        };
        return actionMap[toolId] || 'Proses Gambar';
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

            // Get the content disposition header to extract filename if available
            const contentDisposition = response.headers.get('content-disposition');
            let downloadFilename = filename || 'download';
            
            // Try to extract filename from content-disposition header
            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                if (filenameMatch && filenameMatch[1]) {
                    downloadFilename = filenameMatch[1].replace(/['"]/g, '');
                }
            }
            
            // If we have a selected tool and no filename from server, generate one
            if ((!downloadFilename || downloadFilename === 'download') && selectedTool && selectedFiles.length > 0) {
                const inputFilename = selectedFiles[0].name;
                const baseName = inputFilename.includes('.') 
                    ? inputFilename.substring(0, inputFilename.lastIndexOf('.')) 
                    : inputFilename;
                    
                // Determine the correct extension based on tool
                let extension = '';
                if (selectedTool.id.includes('to-')) {
                    extension = selectedTool.id.split('to-')[1];
                } else {
                    // For tools that don't change format, keep original extension
                    extension = inputFilename.split('.').pop().toLowerCase();
                }
                
                downloadFilename = `${baseName}.${extension}`;
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
            a.download = downloadFilename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            // Update the output filename in state for the UI
            setOutputFilename(downloadFilename);
            
            console.log('Download completed successfully as:', downloadFilename);
        } catch (error) {
            console.error('Download error details:', error);
            setErrorMessage(`Download failed: ${error.message}`);
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-purple-50 to-pink-50">
            <div className="container mx-auto px-4 py-8 space-y-8">
                {/* Enhanced Header */}
                <div className="text-center space-y-6">
                    <div className="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-500 to-pink-600 rounded-3xl shadow-lg transform rotate-3 hover:rotate-0 transition-transform duration-300">
                        <span className="text-3xl">🖼️</span>
                    </div>
                    <div>
                        <h1 className="text-4xl lg:text-6xl font-bold bg-gradient-to-r from-gray-900 via-purple-900 to-pink-900 bg-clip-text text-transparent mb-4">
                            Image Converter
                        </h1>
                        <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                            Transform your images with professional-grade conversion tools. 
                            Convert between JPG, PNG, WebP, GIF and more formats.
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
                                                ? `bg-gradient-to-br ${tool.color} text-white ring-4 ring-blue-300` 
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
                                                    <span className="text-2xl text-white">
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
                                        ? 'border-blue-400 bg-blue-50/50 scale-105'
                                        : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/30'
                                }`}
                            >
                                <input {...getInputProps()} />
                                <div className="space-y-4 lg:space-y-6">
                                    <div className={`w-16 h-16 lg:w-24 lg:h-24 mx-auto rounded-full flex items-center justify-center transition-all duration-300 ${
                                        isDragActive 
                                            ? 'bg-gradient-to-br from-blue-400 to-blue-600 scale-110' 
                                            : 'bg-gradient-to-br from-gray-100 to-gray-200'
                                    }`}>
                                        <svg className={`w-8 h-8 lg:w-12 lg:h-12 transition-colors duration-300 ${
                                            isDragActive ? 'text-white' : 'text-gray-400'
                                        }`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="text-lg lg:text-2xl font-bold text-gray-900 mb-2">
                                            {isDragActive ? 'Drop files here!' : 'Drag & drop your files'}
                                        </p>
                                        <p className="text-sm lg:text-base text-gray-600">
                                            or <span className="text-blue-600 font-semibold">click to browse</span>
                                        </p>
                                    </div>
                                    <div className="text-xs lg:text-sm text-gray-500 space-y-1">
                                        <p>Supported formats: {selectedTool.formats.join(', ')}</p>
                                        <p>Maximum file size: 50MB per file</p>
                                        <p className="text-orange-600 font-medium">Single file only</p>
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
                                                        <p className="font-medium text-gray-900 truncate max-w-48" title={file.name}>{file.name}</p>
                                                        <p className="text-sm text-gray-500">{formatFileSize(file.size)}</p>
                                                    </div>
                                                </div>
                                                <button
                                                    onClick={() => removeFile(index)}
                                                    disabled={isProcessing}
                                                    className="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
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

                            {/* Custom Settings for Resize and Rotate */}
                            {selectedTool && (selectedTool.id === 'resize-image' || selectedTool.id === 'rotate-image') && (
                                <div className="space-y-4 p-6 bg-gray-50 rounded-xl border border-gray-200">
                                    <h4 className="font-semibold text-gray-900 flex items-center space-x-2">
                                        <span className="text-lg">{selectedTool.id === 'resize-image' ? '📏' : '🔄'}</span>
                                        <span>{selectedTool.id === 'resize-image' ? 'Resize Settings' : 'Rotate Settings'}</span>
                                    </h4>
                                    
                                    {selectedTool.id === 'resize-image' && (
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">Width (px)</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="5000"
                                                    value={customSettings.width}
                                                    onChange={(e) => setCustomSettings(prev => ({...prev, width: parseInt(e.target.value) || 800}))}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="800"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">Height (px)</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="5000"
                                                    value={customSettings.height}
                                                    onChange={(e) => setCustomSettings(prev => ({...prev, height: parseInt(e.target.value) || 600}))}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="600"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="flex items-center space-x-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={customSettings.maintainAspectRatio}
                                                        onChange={(e) => setCustomSettings(prev => ({...prev, maintainAspectRatio: e.target.checked}))}
                                                        className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                    />
                                                    <span className="text-sm text-gray-700">Maintain aspect ratio</span>
                                                </label>
                                            </div>
                                        </div>
                                    )}
                                    
                                    {selectedTool.id === 'rotate-image' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">Rotation Angle (degrees)</label>
                                                <input
                                                    type="number"
                                                    min="-360"
                                                    max="360"
                                                    step="1"
                                                    value={customSettings.angle}
                                                    onChange={(e) => setCustomSettings(prev => ({...prev, angle: parseInt(e.target.value) || 90}))}
                                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    placeholder="90"
                                                />
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <span className="text-sm font-medium text-gray-700 w-full mb-1">Quick Presets:</span>
                                                {[90, 180, 270, -90].map(angle => (
                                                    <button
                                                        key={angle}
                                                        onClick={() => setCustomSettings(prev => ({...prev, angle}))}
                                                        className={`px-3 py-1 rounded-lg text-sm font-medium transition-colors duration-200 ${
                                                            customSettings.angle === angle
                                                                ? 'bg-blue-500 text-white'
                                                                : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        {angle > 0 ? `+${angle}°` : `${angle}°`}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Processing Status */}
                            {processingStatus && (
                                <div className={`p-4 rounded-xl border ${isProcessing ? 'bg-blue-50 border-blue-200' : errorMessage ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'}`}>
                                    <div className="flex items-center space-x-3">
                                        {isProcessing && (
                                            <svg className="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        )}
                                        {!isProcessing && !errorMessage && (
                                            <svg className="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        )}
                                        {errorMessage && (
                                            <svg className="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        )}
                                        <span className={`font-medium ${isProcessing ? 'text-blue-800' : errorMessage ? 'text-red-800' : 'text-green-800'}`}>
                                            {errorMessage || processingStatus}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Download Button */}
                            {downloadUrl && outputFilename && !isProcessing && (
                                <div className="bg-green-50 border border-green-200 rounded-xl p-6">
                                    <div className="text-center space-y-4">
                                        <div className="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mx-auto">
                                            <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="text-lg font-semibold text-green-800 mb-2">File Ready for Download!</h4>
                                            <p className="text-green-700 mb-4">Your converted file: <span className="font-medium">{outputFilename}</span></p>
                                            <a
                                                href={downloadUrl}
                                                download={outputFilename}
                                                className={`inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r ${selectedTool.color} text-white font-semibold rounded-xl hover:shadow-lg transition-all duration-200 transform hover:scale-105`}
                                            >
                                                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Download File
                                            </a>
                                        </div>
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
                                        setErrorMessage(null);
                                        setProcessingStatus('');
                                        setDownloadUrl(null);
                                        setOutputFilename(null);
                                    }}
                                    className="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200"
                                >
                                    Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ImageConverter;
