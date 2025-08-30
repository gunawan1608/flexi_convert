import React, { useState, useRef, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

const DocumentConverter = () => {
    const [selectedTool, setSelectedTool] = useState(null);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState([]);
    const [toolSettings, setToolSettings] = useState({});
    const [activeCategory, setActiveCategory] = useState('convert-to');
    const [processingStatus, setProcessingStatus] = useState('');
    const [downloadUrl, setDownloadUrl] = useState(null);
    const [outputFilename, setOutputFilename] = useState(null);
    const [errorMessage, setErrorMessage] = useState(null);

    // Enhanced tool categories with better organization and animations
    const toolCategories = [
        {
            id: 'convert-to',
            title: 'Convert to PDF',
            icon: '📄',
            gradient: 'from-emerald-400 via-emerald-500 to-emerald-600',
            description: 'Transform any document into PDF format',
            tools: [
                { 
                    id: 'word-to-pdf', 
                    name: 'Word → PDF', 
                    description: 'Convert Word documents to PDF with perfect formatting', 
                    icon: '📝',
                    color: 'from-blue-500 to-blue-600',
                    formats: ['.doc', '.docx']
                },
                { 
                    id: 'excel-to-pdf', 
                    name: 'Excel → PDF', 
                    description: 'Convert spreadsheets to PDF while preserving layout', 
                    icon: '📊',
                    color: 'from-green-500 to-green-600',
                    formats: ['.xls', '.xlsx']
                },
                { 
                    id: 'ppt-to-pdf', 
                    name: 'PowerPoint → PDF', 
                    description: 'Convert presentations to PDF format', 
                    icon: '📈',
                    color: 'from-orange-500 to-orange-600',
                    formats: ['.ppt', '.pptx']
                },
                { 
                    id: 'jpg-to-pdf', 
                    name: 'Image → PDF', 
                    description: 'Convert images to PDF documents', 
                    icon: '🖼️',
                    color: 'from-purple-500 to-purple-600',
                    formats: ['.jpg', '.jpeg', '.png', '.gif', '.bmp']
                },
                { 
                    id: 'html-to-pdf', 
                    name: 'HTML → PDF', 
                    description: 'Convert web pages to PDF format', 
                    icon: '🌐',
                    color: 'from-cyan-500 to-cyan-600',
                    formats: ['.html', '.htm']
                }
            ]
        },
        {
            id: 'convert-from',
            title: 'Convert from PDF',
            icon: '📋',
            gradient: 'from-rose-400 via-rose-500 to-rose-600',
            description: 'Extract content from PDF files',
            tools: [
                { 
                    id: 'pdf-to-word', 
                    name: 'PDF → Word', 
                    description: 'Convert PDF to editable Word document', 
                    icon: '📝',
                    color: 'from-blue-500 to-blue-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'pdf-to-excel', 
                    name: 'PDF → Excel', 
                    description: 'Extract tables from PDF to Excel', 
                    icon: '📊',
                    color: 'from-green-500 to-green-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'pdf-to-ppt', 
                    name: 'PDF → PowerPoint', 
                    description: 'Convert PDF to PowerPoint presentation', 
                    icon: '📈',
                    color: 'from-orange-500 to-orange-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'pdf-to-jpg', 
                    name: 'PDF → Image', 
                    description: 'Convert PDF pages to high-quality images', 
                    icon: '🖼️',
                    color: 'from-purple-500 to-purple-600',
                    formats: ['.pdf']
                }
            ]
        },
        {
            id: 'pdf-tools',
            title: 'PDF Tools',
            icon: '🛠️',
            gradient: 'from-indigo-400 via-indigo-500 to-indigo-600',
            description: 'Advanced PDF manipulation tools',
            tools: [
                { 
                    id: 'merge-pdf', 
                    name: 'Merge PDF', 
                    description: 'Combine multiple PDFs into one document', 
                    icon: '🔗',
                    color: 'from-teal-500 to-teal-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'split-pdf', 
                    name: 'Split PDF', 
                    description: 'Split PDF into separate pages or ranges', 
                    icon: '✂️',
                    color: 'from-yellow-500 to-yellow-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'compress-pdf', 
                    name: 'Compress PDF', 
                    description: 'Reduce PDF file size without quality loss', 
                    icon: '🗜️',
                    color: 'from-red-500 to-red-600',
                    formats: ['.pdf']
                },
                { 
                    id: 'rotate-pdf', 
                    name: 'Rotate PDF', 
                    description: 'Rotate PDF pages to correct orientation', 
                    icon: '🔄',
                    color: 'from-pink-500 to-pink-600',
                    formats: ['.pdf']
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
            
            return tool?.formats.includes(fileExtension) && file.size <= 100 * 1024 * 1024; // 100MB limit
        });
        
        setSelectedFiles(prev => [...prev, ...validFiles]);
    }, [selectedTool]);

    // Get file accept types based on selected tool
    const getAcceptTypes = () => {
        if (!selectedTool) return {};
        
        const toolAcceptMap = {
            'word-to-pdf': { 'application/msword': ['.doc'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'] },
            'excel-to-pdf': { 'application/vnd.ms-excel': ['.xls'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'] },
            'ppt-to-pdf': { 'application/vnd.ms-powerpoint': ['.ppt'], 'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'] },
            'jpg-to-pdf': { 'image/jpeg': ['.jpg', '.jpeg'], 'image/png': ['.png'], 'image/gif': ['.gif'], 'image/bmp': ['.bmp'] },
            'image-to-pdf': { 'image/jpeg': ['.jpg', '.jpeg'], 'image/png': ['.png'], 'image/gif': ['.gif'], 'image/bmp': ['.bmp'] },
            'html-to-pdf': { 'text/html': ['.html', '.htm'] },
            'pdf-to-word': { 'application/pdf': ['.pdf'] },
            'pdf-to-excel': { 'application/pdf': ['.pdf'] },
            'pdf-to-ppt': { 'application/pdf': ['.pdf'] },
            'pdf-to-jpg': { 'application/pdf': ['.pdf'] },
            'pdf-to-image': { 'application/pdf': ['.pdf'] },
            'merge-pdf': { 'application/pdf': ['.pdf'] },
            'split-pdf': { 'application/pdf': ['.pdf'] },
            'compress-pdf': { 'application/pdf': ['.pdf'] },
            'rotate-pdf': { 'application/pdf': ['.pdf'] }
        };
        
        return toolAcceptMap[selectedTool.id] || {};
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: getAcceptTypes(),
        multiple: selectedTool?.id === 'merge-pdf' || selectedTool?.id === 'image-to-pdf' || selectedTool?.id === 'jpg-to-pdf',
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
            selectedFiles.forEach(file => {
                formData.append('files[]', file);
                // Add original filenames to track them
                formData.append('original_filenames[]', file.name);
            });
            formData.append('tool', selectedTool.id);
            
            // Determine output format based on tool
            let outputFormat = 'pdf'; // default
            if (selectedTool.id.startsWith('pdf-to-')) {
                outputFormat = selectedTool.id.split('-').pop();
                if (outputFormat === 'jpg') outputFormat = 'jpeg'; // Fix for jpg vs jpeg
            }
            
            // Add output format to settings
            const settingsWithFormat = {
                ...toolSettings,
                format: outputFormat
            };
            
            formData.append('settings', JSON.stringify(settingsWithFormat));

            setProcessingStatus('Processing conversion...');

            const response = await window.apiRequest('/api/pdf-tools/process', {
                method: 'POST',
                body: formData
            });
            
            if (response.success) {
                setProcessingStatus('Conversion completed!');
                setDownloadUrl(response.download_url);
                
                // Generate a meaningful output filename
                let outputFilename = '';
                if (selectedFiles.length === 1) {
                    // For single file, use original name with new extension
                    const originalName = selectedFiles[0].name.replace(/\.[^/.]+$/, '');
                    outputFilename = `${originalName}.${outputFormat}`;
                } else {
                    // For multiple files, use a generic name with timestamp
                    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                    outputFilename = `converted_documents_${timestamp}.${outputFormat}`;
                }
                
                setOutputFilename(outputFilename);
                setResults([{
                    filename: outputFilename,
                    status: 'completed',
                    download_url: response.download_url,
                    processing_id: response.processing_id
                }]);
            } else {
                throw new Error(response.message || 'Conversion failed');
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
            'merge-pdf': 'Gabungkan PDF',
            'split-pdf': 'Pisahkan PDF', 
            'compress-pdf': 'Kompres PDF',
            'rotate-pdf': 'Putar PDF',
            'word-to-pdf': 'Konversi ke PDF',
            'excel-to-pdf': 'Konversi ke PDF',
            'ppt-to-pdf': 'Konversi ke PDF',
            'jpg-to-pdf': 'Konversi ke PDF',
            'html-to-pdf': 'Konversi ke PDF',
            'pdf-to-word': 'Konversi ke Word',
            'pdf-to-excel': 'Konversi ke Excel',
            'pdf-to-ppt': 'Konversi ke PowerPoint',
            'pdf-to-jpg': 'Konversi ke Gambar'
        };
        return actionMap[toolId] || 'Proses File';
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

            // Get the content disposition header to check for filename
            const contentDisposition = response.headers.get('content-disposition');
            let downloadFilename = filename || 'download';
            
            // Extract filename from content disposition if available
            if (contentDisposition) {
                console.log('Content-Disposition header:', contentDisposition);
                
                // Try to extract filename from filename*= parameter first (UTF-8 encoded)
                let filenameMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/);
                if (filenameMatch && filenameMatch[1]) {
                    downloadFilename = decodeURIComponent(filenameMatch[1]);
                    console.log('Extracted UTF-8 filename:', downloadFilename);
                } else {
                    // Fallback to regular filename parameter
                    filenameMatch = contentDisposition.match(/filename="([^"]+)"/);
                    if (filenameMatch && filenameMatch[1]) {
                        downloadFilename = filenameMatch[1];
                        console.log('Extracted regular filename:', downloadFilename);
                    }
                }
            }

            // Ensure the filename has the correct extension based on the tool
            if (selectedTool) {
                let fileExtension = 'pdf'; // default
                
                if (selectedTool.id.startsWith('pdf-to-')) {
                    fileExtension = selectedTool.id.split('-').pop();
                    if (fileExtension === 'jpg') fileExtension = 'jpeg'; // Fix for jpg vs jpeg
                } else if (selectedTool.id.endsWith('-to-pdf')) {
                    fileExtension = 'pdf';
                }
                
                // Remove any existing extension and add the correct one
                if (!downloadFilename.endsWith(`.${fileExtension}`)) {
                    downloadFilename = downloadFilename.replace(/\.[^/.]+$/, '') + `.${fileExtension}`;
                }
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
            
            console.log('Download completed successfully');
        } catch (error) {
            console.error('Download error details:', error);
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 p-4 lg:p-8">
            <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="text-center mb-8 lg:mb-12">
                    <div className="inline-flex items-center justify-center w-16 h-16 lg:w-20 lg:h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl lg:rounded-3xl shadow-lg mb-4 lg:mb-6 transform hover:scale-105 transition-transform duration-300">
                        <span className="text-2xl lg:text-3xl">📄</span>
                    </div>
                    <h1 className="text-2xl sm:text-3xl lg:text-4xl font-bold bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 bg-clip-text text-transparent mb-3 lg:mb-4 px-4">
                        Document Converter
                    </h1>
                    <p className="text-sm sm:text-base lg:text-xl text-gray-600 max-w-2xl mx-auto px-4">
                        Transform your documents with professional-grade conversion tools. 
                        Convert between PDF, Word, Excel, PowerPoint and more.
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4 lg:gap-6 mb-8 lg:mb-12">
                    <div className="bg-white/70 backdrop-blur-sm rounded-xl lg:rounded-2xl px-4 lg:px-6 py-3 lg:py-4 shadow-lg text-center">
                        <div className="text-lg lg:text-2xl font-bold text-blue-600">{selectedFiles.length}</div>
                        <div className="text-xs lg:text-sm text-gray-600">Files Selected</div>
                    </div>
                    <div className="bg-white/70 backdrop-blur-sm rounded-xl lg:rounded-2xl px-4 lg:px-6 py-3 lg:py-4 shadow-lg text-center">
                        <div className="text-lg lg:text-2xl font-bold text-green-600">{results.filter(r => r.status === 'completed').length}</div>
                        <div className="text-xs lg:text-sm text-gray-600">Completed</div>
                    </div>
                    <div className="bg-white/70 backdrop-blur-sm rounded-xl lg:rounded-2xl px-4 lg:px-6 py-3 lg:py-4 shadow-lg text-center">
                        <div className="text-lg lg:text-2xl font-bold text-purple-600">∞</div>
                        <div className="text-xs lg:text-sm text-gray-600">Free Usage</div>
                    </div>
                </div>

                {/* Category Navigation */}
                <div className="flex justify-center mb-8 lg:mb-12">
                    <div className="bg-white/80 backdrop-blur-sm rounded-2xl p-2 shadow-lg border border-white/20">
                        <div className="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                            {toolCategories.map((category) => (
                                <button
                                    key={category.id}
                                    onClick={() => setActiveCategory(category.id)}
                                    className={`px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-semibold transition-all duration-300 text-sm lg:text-base ${
                                        activeCategory === category.id
                                            ? `bg-gradient-to-r ${category.gradient} text-white shadow-lg transform scale-105`
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-white/50'
                                    }`}
                                >
                                    <span className="mr-2">{category.icon}</span>
                                    <span className="hidden sm:inline">{category.title}</span>
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
                            
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 lg:gap-6">
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
                                            
                                            <div className="relative p-4 lg:p-6 text-center space-y-3 lg:space-y-4">
                                                <div className={`inline-flex items-center justify-center w-12 h-12 lg:w-16 lg:h-16 rounded-xl lg:rounded-2xl ${
                                                    selectedTool?.id === tool.id 
                                                        ? 'bg-white/20' 
                                                        : `bg-gradient-to-br ${tool.color}`
                                                } shadow-lg`}>
                                                    <span className={`text-xl lg:text-2xl ${
                                                        selectedTool?.id === tool.id ? 'text-white' : 'text-white'
                                                    }`}>
                                                        {tool.icon}
                                                    </span>
                                                </div>
                                                
                                                <div>
                                                    <h3 className={`font-bold text-base lg:text-lg ${
                                                        selectedTool?.id === tool.id ? 'text-white' : 'text-gray-900'
                                                    }`}>
                                                        {tool.name}
                                                    </h3>
                                                    <p className={`text-xs lg:text-sm ${
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
                        <div className="p-4 lg:p-8 space-y-4 lg:space-y-6">
                            <div className="text-center">
                                <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${selectedTool.color} shadow-lg mb-4`}>
                                    <span className="text-2xl text-white">{selectedTool.icon}</span>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-900">{selectedTool.name}</h3>
                                <p className="text-gray-600">{selectedTool.description}</p>
                            </div>
                            
                            {/* Tool-Specific Settings - Only show for PDF tools, not convert tools */}
                            {selectedTool && ['rotate-pdf', 'split-pdf', 'compress-pdf', 'merge-pdf'].includes(selectedTool.id) && (
                                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-200">
                                    <h4 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <span className="mr-2">⚙️</span>
                                        Settings for {selectedTool.name}
                                    </h4>
                                    
                                    {/* Rotate PDF Settings */}
                                    {selectedTool.id === 'rotate-pdf' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                                    Pilih Derajat Rotasi:
                                                </label>
                                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                    {[
                                                        { value: '90', label: '90° (Putar Kanan)', icon: '↻' },
                                                        { value: '180', label: '180° (Putar Balik)', icon: '↺' },
                                                        { value: '270', label: '270° (Putar Kiri)', icon: '↻' },
                                                        { value: '-90', label: '-90° (Putar Kiri)', icon: '↺' }
                                                    ].map((option) => (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() => setToolSettings({ ...toolSettings, rotation: option.value })}
                                                            className={`p-4 rounded-xl border-2 transition-all duration-200 text-center ${
                                                                toolSettings.rotation === option.value
                                                                    ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-md'
                                                                    : 'border-gray-200 bg-white hover:border-blue-300 hover:bg-blue-50'
                                                            }`}
                                                        >
                                                            <div className="text-2xl mb-2">{option.icon}</div>
                                                            <div className="text-sm font-medium">{option.label}</div>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="bg-blue-100 border border-blue-300 rounded-lg p-3">
                                                <p className="text-sm text-blue-800">
                                                    <span className="font-medium">💡 Tips:</span> Pilih derajat rotasi yang sesuai untuk memperbaiki orientasi halaman PDF Anda.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    
                                    {/* Split PDF Settings */}
                                    {selectedTool.id === 'split-pdf' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                                    Mode Pemisahan:
                                                </label>
                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    {[
                                                        { value: 'all', label: 'Pisah Semua Halaman', desc: 'Setiap halaman jadi file terpisah' },
                                                        { value: 'range', label: 'Range Halaman', desc: 'Pisah berdasarkan range tertentu' },
                                                        { value: 'interval', label: 'Interval Halaman', desc: 'Pisah setiap N halaman' }
                                                    ].map((option) => (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() => setToolSettings({ ...toolSettings, splitMode: option.value })}
                                                            className={`p-4 rounded-xl border-2 transition-all duration-200 text-left ${
                                                                toolSettings.splitMode === option.value
                                                                    ? 'border-yellow-500 bg-yellow-50 text-yellow-700 shadow-md'
                                                                    : 'border-gray-200 bg-white hover:border-yellow-300 hover:bg-yellow-50'
                                                            }`}
                                                        >
                                                            <div className="font-medium mb-1">{option.label}</div>
                                                            <div className="text-sm text-gray-600">{option.desc}</div>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            
                                            {/* Range Input */}
                                            {toolSettings.splitMode === 'range' && (
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                                            Halaman Mulai:
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            value={toolSettings.startPage || ''}
                                                            onChange={(e) => setToolSettings({ ...toolSettings, startPage: e.target.value })}
                                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                                            placeholder="1"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                                            Halaman Akhir:
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            value={toolSettings.endPage || ''}
                                                            onChange={(e) => setToolSettings({ ...toolSettings, endPage: e.target.value })}
                                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                                            placeholder="10"
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                            
                                            {/* Interval Input */}
                                            {toolSettings.splitMode === 'interval' && (
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Interval Halaman:
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={toolSettings.interval || ''}
                                                        onChange={(e) => setToolSettings({ ...toolSettings, interval: e.target.value })}
                                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                                        placeholder="5"
                                                    />
                                                    <p className="text-sm text-gray-600 mt-1">Contoh: 5 = setiap 5 halaman jadi 1 file</p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    
                                    {/* Merge PDF Settings */}
                                    {selectedTool.id === 'merge-pdf' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                                    Urutan File:
                                                </label>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    {[
                                                        { value: 'upload', label: 'Sesuai Urutan Upload', desc: 'File digabung sesuai urutan upload' },
                                                        { value: 'name', label: 'Sesuai Nama File', desc: 'File diurutkan berdasarkan nama (A-Z)' }
                                                    ].map((option) => (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() => setToolSettings({ ...toolSettings, mergeOrder: option.value })}
                                                            className={`p-4 rounded-xl border-2 transition-all duration-200 text-left ${
                                                                toolSettings.mergeOrder === option.value
                                                                    ? 'border-teal-500 bg-teal-50 text-teal-700 shadow-md'
                                                                    : 'border-gray-200 bg-white hover:border-teal-300 hover:bg-teal-50'
                                                            }`}
                                                        >
                                                            <div className="font-medium mb-1">{option.label}</div>
                                                            <div className="text-sm text-gray-600">{option.desc}</div>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="bg-teal-100 border border-teal-300 rounded-lg p-3">
                                                <p className="text-sm text-teal-800">
                                                    <span className="font-medium">📋 Info:</span> Anda dapat mengatur ulang urutan file dengan drag & drop setelah upload.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    
                                    {/* Compress PDF Settings */}
                                    {selectedTool.id === 'compress-pdf' && (
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                                    Level Kompresi:
                                                </label>
                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    {[
                                                        { value: 'low', label: 'Ringan', desc: 'Kualitas tinggi, ukuran sedang', reduction: '~30%' },
                                                        { value: 'medium', label: 'Sedang', desc: 'Kualitas baik, ukuran kecil', reduction: '~50%' },
                                                        { value: 'high', label: 'Tinggi', desc: 'Kualitas cukup, ukuran sangat kecil', reduction: '~70%' }
                                                    ].map((option) => (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            onClick={() => setToolSettings({ ...toolSettings, compressionLevel: option.value })}
                                                            className={`p-4 rounded-xl border-2 transition-all duration-200 text-center ${
                                                                toolSettings.compressionLevel === option.value
                                                                    ? 'border-red-500 bg-red-50 text-red-700 shadow-md'
                                                                    : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50'
                                                            }`}
                                                        >
                                                            <div className="font-medium mb-1">{option.label}</div>
                                                            <div className="text-sm text-gray-600 mb-1">{option.desc}</div>
                                                            <div className="text-xs font-medium text-red-600">{option.reduction}</div>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                            <div className="bg-red-100 border border-red-300 rounded-lg p-3">
                                                <p className="text-sm text-red-800">
                                                    <span className="font-medium">⚠️ Perhatian:</span> Kompresi tinggi dapat mengurangi kualitas gambar dan teks dalam PDF.
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                            
                            <div
                                {...getRootProps()}
                                className={`relative border-2 border-dashed rounded-3xl p-12 text-center transition-all duration-300 cursor-pointer ${
                                    isDragActive 
                                        ? 'border-blue-400 bg-blue-50/50 scale-105' 
                                        : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/30'
                                }`}
                            >
                                <input {...getInputProps()} />
                                <div className="space-y-6">
                                    <div className={`w-24 h-24 mx-auto rounded-full flex items-center justify-center transition-all duration-300 ${
                                        isDragActive 
                                            ? 'bg-gradient-to-br from-blue-400 to-blue-600 scale-110' 
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
                                            {isDragActive ? 'Drop files here!' : 'Drag & drop your files'}
                                        </p>
                                        <p className="text-gray-600">
                                            or <span className="text-blue-600 font-semibold">click to browse</span>
                                        </p>
                                    </div>
                                    <div className="text-sm text-gray-500 space-y-1">
                                        <p>Supported formats: {selectedTool.formats.join(', ')}</p>
                                        <p>Maximum file size: 100MB per file</p>
                                        {selectedTool.id === 'merge-pdf' || selectedTool.id === 'image-to-pdf' || selectedTool.id === 'jpg-to-pdf' ? 
                                            <p className="text-blue-600 font-medium">Multiple files allowed</p> : 
                                            <p className="text-orange-600 font-medium">Single file only</p>
                                        }
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
                                        setProcessingStatus('');
                                        setDownloadUrl(null);
                                        setOutputFilename(null);
                                        setErrorMessage(null);
                                    }}
                                    disabled={isProcessing}
                                    className="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
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

export default DocumentConverter;
