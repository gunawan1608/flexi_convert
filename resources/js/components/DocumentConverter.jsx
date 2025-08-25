import React, { useState, useRef, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

const DocumentConverter = () => {
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [selectedFormat, setSelectedFormat] = useState('pdf');
    const [isConverting, setIsConverting] = useState(false);
    const [conversions, setConversions] = useState([]);
    const [settings, setSettings] = useState({
        quality: 'medium',
        pageRange: '',
        preserveFormatting: true,
        optimizeImages: false,
        embedFonts: false
    });

    const formatOptions = [
        { value: 'pdf', label: 'PDF', color: 'from-blue-500 to-blue-600', icon: '📄', description: 'Portable Document Format' },
        { value: 'docx', label: 'Word', color: 'from-blue-500 to-blue-600', icon: '📝', description: 'Microsoft Word Document' },
        { value: 'xlsx', label: 'Excel', color: 'from-blue-500 to-blue-600', icon: '📊', description: 'Microsoft Excel Spreadsheet' },
        { value: 'pptx', label: 'PowerPoint', color: 'from-blue-500 to-blue-600', icon: '📋', description: 'Microsoft PowerPoint Presentation' },
        { value: 'odt', label: 'OpenDocument Text', color: 'from-blue-500 to-blue-600', icon: '📄', description: 'Open Document Format' },
        { value: 'ods', label: 'OpenDocument Spreadsheet', color: 'from-blue-500 to-blue-600', icon: '📊', description: 'Open Document Spreadsheet' },
        { value: 'odp', label: 'OpenDocument Presentation', color: 'from-blue-500 to-blue-600', icon: '📋', description: 'Open Document Presentation' },
        { value: 'epub', label: 'EPUB', color: 'from-blue-500 to-blue-600', icon: '📚', description: 'Electronic Publication' },
        { value: 'mobi', label: 'MOBI', color: 'from-blue-500 to-blue-600', icon: '📖', description: 'Mobipocket eBook' },
        { value: 'html', label: 'HTML', color: 'from-blue-500 to-blue-600', icon: '🌐', description: 'HyperText Markup Language' },
        { value: 'markdown', label: 'Markdown', color: 'from-blue-500 to-blue-600', icon: '📝', description: 'Markdown Format' },
        { value: 'txt', label: 'Plain Text', color: 'from-blue-500 to-blue-600', icon: '📃', description: 'Plain Text File' },
        { value: 'rtf', label: 'Rich Text', color: 'from-blue-500 to-blue-600', icon: '📄', description: 'Rich Text Format' },
        { value: 'csv', label: 'CSV', color: 'from-blue-500 to-blue-600', icon: '📊', description: 'Comma Separated Values' },
        { value: 'json', label: 'JSON', color: 'from-blue-500 to-blue-600', icon: '🔧', description: 'JavaScript Object Notation' },
        { value: 'xml', label: 'XML', color: 'from-blue-500 to-blue-600', icon: '🔧', description: 'Extensible Markup Language' }
    ];

    const onDrop = useCallback((acceptedFiles) => {
        const validFiles = acceptedFiles.filter(file => {
            const validTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'epub', 'mobi', 'html', 'htm', 'md', 'txt', 'rtf', 'csv', 'json', 'xml'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            return validTypes.includes(fileExtension) && file.size <= 100 * 1024 * 1024; // Increased to 100MB
        });

        setSelectedFiles(prev => [...prev, ...validFiles]);
    }, []);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            'application/pdf': ['.pdf'],
            'application/msword': ['.doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
            'application/vnd.ms-excel': ['.xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
            'application/vnd.ms-powerpoint': ['.ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'],
            'application/vnd.oasis.opendocument.text': ['.odt'],
            'application/vnd.oasis.opendocument.spreadsheet': ['.ods'],
            'application/vnd.oasis.opendocument.presentation': ['.odp'],
            'application/epub+zip': ['.epub'],
            'application/x-mobipocket-ebook': ['.mobi'],
            'text/html': ['.html', '.htm'],
            'text/markdown': ['.md', '.markdown'],
            'text/plain': ['.txt'],
            'application/rtf': ['.rtf'],
            'text/csv': ['.csv'],
            'application/json': ['.json'],
            'application/xml': ['.xml'],
            'text/xml': ['.xml']
        },
        multiple: true,
        maxSize: 100 * 1024 * 1024 // 100MB
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

    const uploadFiles = async () => {
        if (!selectedFiles || selectedFiles.length === 0) {
            console.error('No files selected for upload');
            return { success: false, message: 'No files selected' };
        }

        const formData = new FormData();
        
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });
        
        formData.append('target_format', selectedFormat);
        formData.append('quality', settings.quality);
        formData.append('page_range', settings.pageRange);
        formData.append('preserve_formatting', settings.preserveFormatting ? 1 : 0);
        formData.append('optimize_images', settings.optimizeImages ? 1 : 0);
        formData.append('embed_fonts', settings.embedFonts ? 1 : 0);
        
        // Debug: Log what we're sending
        console.log('Files being sent:', selectedFiles);
        console.log('Settings object:', settings);
        console.log('Boolean conversions:');
        console.log('preserve_formatting:', settings.preserveFormatting, '->', settings.preserveFormatting ? 1 : 0);
        console.log('optimize_images:', settings.optimizeImages, '->', settings.optimizeImages ? 1 : 0);
        console.log('embed_fonts:', settings.embedFonts, '->', settings.embedFonts ? 1 : 0);
        console.log('FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0], ':', pair[1], '(type:', typeof pair[1], ')');
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        formData.append('_token', csrfToken);
        
        const response = await fetch('/api/documents/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const result = await response.json();
        console.log('Parsed response:', result);
        
        return result;
    };

    const startConversion = async (conversionIds) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await fetch('/api/documents/convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                conversion_ids: conversionIds
            })
        });
        
        return await response.json();
    };

    const monitorConversion = async (conversionId) => {
        const checkStatus = async () => {
            try {
                const response = await fetch(`/api/documents/status/${conversionId}`);
                const result = await response.json();
                
                if (result.success) {
                    setConversions(prev => prev.map(conv => 
                        conv.id === conversionId ? { ...conv, ...result.conversion } : conv
                    ));
                    
                    if (result.conversion.status === 'completed' || result.conversion.status === 'failed') {
                        return;
                    } else {
                        setTimeout(checkStatus, 2000);
                    }
                }
            } catch (error) {
                console.error('Status check error:', error);
            }
        };
        
        checkStatus();
    };

    const handleConvert = async () => {
        if (selectedFiles.length === 0) return;
        
        setIsConverting(true);
        
        try {
            const uploadResult = await uploadFiles();
            
            if (uploadResult.success) {
                // Check if conversions array exists and has items
                if (uploadResult.conversions && uploadResult.conversions.length > 0) {
                    const conversionIds = uploadResult.conversions.map(c => c.id);
                    await startConversion(conversionIds);
                    
                    const initialConversions = uploadResult.conversions.map(conv => ({
                        ...conv,
                        status: 'processing'
                    }));
                    setConversions(initialConversions);
                } else {
                    // Handle case where no conversions are returned (debugging mode)
                    console.log('Upload successful but no conversions returned:', uploadResult);
                    alert('Upload successful! Check console for details.');
                }
            } else {
                console.error("Upload failed - Full response:", uploadResult);
                console.error("Response keys:", Object.keys(uploadResult));
                console.error("Response success:", uploadResult.success);
                console.error("Response message:", uploadResult.message);
                console.error("Validation errors:", uploadResult.errors);
                console.error("Debug info:", uploadResult.debug);
                console.error("Full JSON:", JSON.stringify(uploadResult, null, 2));
                alert('Upload failed: ' + (uploadResult.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Conversion error:', error);
            alert('An error occurred during conversion. Please try again.');
        } finally {
            setIsConverting(false);
        }
    };

    const clearAll = () => {
        setSelectedFiles([]);
        setConversions([]);
    };

    return (
        <div className="space-y-8">
            {/* Header */}
            <div className="bg-gradient-to-br from-blue-50 via-blue-25 to-blue-50 rounded-3xl p-8 border border-blue-100">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div className="mb-6 lg:mb-0">
                        <h1 className="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">
                            Document Converter
                        </h1>
                        <p className="text-lg text-gray-600 mb-4">
                            Convert between 16+ modern document formats including PDF, Office, OpenDocument, eBooks, and web formats
                        </p>
                        <div className="flex items-center space-x-4 text-sm text-gray-500">
                            <div className="flex items-center space-x-2">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <span>Secure & Private</span>
                            </div>
                            <div className="flex items-center space-x-2">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <span>Fast Processing</span>
                            </div>
                            <div className="flex items-center space-x-2">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                <span>High Quality</span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center space-x-3">
                        <div className="text-right">
                            <div className="text-2xl font-bold text-gray-900">{selectedFiles.length}</div>
                            <div className="text-sm text-gray-500">Files Selected</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* File Upload */}
            <div className="bg-white rounded-3xl shadow-lg border border-gray-100">
                <div className="p-8">
                    <h2 className="text-2xl font-semibold text-gray-900 mb-6">Upload Your Documents</h2>
                    
                    <div
                        {...getRootProps()}
                        className={`relative border-2 border-dashed rounded-2xl p-12 text-center transition-all duration-300 cursor-pointer ${
                            isDragActive 
                                ? 'border-blue-400 bg-blue-50' 
                                : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                        }`}
                    >
                        <input {...getInputProps()} />
                        <div className="space-y-6">
                            <div className="w-20 h-20 mx-auto bg-gradient-to-br from-blue-100 to-blue-200 rounded-full flex items-center justify-center">
                                <svg className="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>
                            <div>
                                <p className="text-xl font-semibold text-gray-900">
                                    {isDragActive ? 'Drop your files here' : 'Drop your files here'}
                                </p>
                                <p className="text-gray-500 mt-2">
                                    or <span className="text-blue-600 font-medium">browse files</span>
                                </p>
                            </div>
                            <div className="text-sm text-gray-400 space-y-1">
                                <p>Supported formats: PDF, Office (DOC/DOCX/XLS/XLSX/PPT/PPTX), OpenDocument (ODT/ODS/ODP), eBooks (EPUB/MOBI), Web (HTML/Markdown), Data (CSV/JSON/XML), Text (TXT/RTF)</p>
                                <p>Maximum file size: 100MB per file</p>
                            </div>
                        </div>
                    </div>

                    {/* Selected Files */}
                    {selectedFiles.length > 0 && (
                        <div className="mt-8">
                            <h3 className="font-semibold text-gray-900 mb-4">Selected Files ({selectedFiles.length})</h3>
                            <div className="space-y-3 max-h-64 overflow-y-auto">
                                {selectedFiles.map((file, index) => (
                                    <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200">
                                        <div className="flex items-center space-x-4">
                                            <div className="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                                                <svg className="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                                    <path d="M14 2v6h6"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900">{file.name}</p>
                                                <p className="text-sm text-gray-500">{formatFileSize(file.size)}</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => removeFile(index)}
                                            className="text-blue-600 hover:text-blue-800 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200"
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
                </div>
            </div>

            {/* Conversion Options */}
            <div className="bg-white rounded-3xl shadow-lg border border-gray-100">
                <div className="p-8">
                    <h2 className="text-2xl font-semibold text-gray-900 mb-6">Conversion Options</h2>
                    
                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        {/* Target Format */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-4">Convert To</label>
                            <div className="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-3 max-h-96 overflow-y-auto">
                                {formatOptions.map((format) => (
                                    <button
                                        key={format.value}
                                        onClick={() => setSelectedFormat(format.value)}
                                        className={`p-3 rounded-xl border-2 transition-all duration-200 ${
                                            selectedFormat === format.value
                                                ? `border-transparent bg-gradient-to-r ${format.color} text-white shadow-lg`
                                                : 'border-gray-200 hover:border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                        }`}
                                        title={format.description}
                                    >
                                        <div className="text-center">
                                            <div className="text-xl mb-1">{format.icon}</div>
                                            <div className="font-medium text-xs">{format.label}</div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Advanced Settings */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-4">Advanced Settings</label>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Output Quality</label>
                                    <select 
                                        value={settings.quality}
                                        onChange={(e) => setSettings(prev => ({ ...prev, quality: e.target.value }))}
                                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                    >
                                        <option value="maximum">Maximum Quality (Largest file)</option>
                                        <option value="high">High Quality (Large file)</option>
                                        <option value="medium">Medium Quality (Balanced)</option>
                                        <option value="low">Low Quality (Small file)</option>
                                        <option value="minimum">Minimum Quality (Smallest file)</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Page/Sheet Range</label>
                                    <input 
                                        type="text" 
                                        placeholder="e.g., 1-5, 10, 15-20 or all"
                                        value={settings.pageRange}
                                        onChange={(e) => setSettings(prev => ({ ...prev, pageRange: e.target.value }))}
                                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                    />
                                </div>
                                
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="preserveFormatting"
                                            checked={settings.preserveFormatting}
                                            onChange={(e) => setSettings(prev => ({ ...prev, preserveFormatting: e.target.checked }))}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="preserveFormatting" className="ml-3 text-sm text-gray-700">
                                            Preserve original formatting
                                        </label>
                                    </div>
                                    
                                    <div className="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="optimizeImages"
                                            checked={settings.optimizeImages || false}
                                            onChange={(e) => setSettings(prev => ({ ...prev, optimizeImages: e.target.checked }))}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="optimizeImages" className="ml-3 text-sm text-gray-700">
                                            Optimize embedded images
                                        </label>
                                    </div>
                                    
                                    <div className="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            id="embedFonts"
                                            checked={settings.embedFonts || false}
                                            onChange={(e) => setSettings(prev => ({ ...prev, embedFonts: e.target.checked }))}
                                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        />
                                        <label htmlFor="embedFonts" className="ml-3 text-sm text-gray-700">
                                            Embed fonts (PDF output)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="mt-8 flex flex-col sm:flex-row gap-4">
                        <button
                            onClick={handleConvert}
                            disabled={selectedFiles.length === 0 || isConverting}
                            className="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-4 rounded-xl font-semibold transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none shadow-lg"
                        >
                            {isConverting ? (
                                <span className="flex items-center justify-center">
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Converting...
                                </span>
                            ) : (
                                <span className="flex items-center justify-center">
                                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                    Convert Documents
                                </span>
                            )}
                        </button>
                        
                        <button
                            onClick={clearAll}
                            className="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all duration-200"
                        >
                            Clear All
                        </button>
                    </div>
                </div>
            </div>

            {/* Conversion Progress */}
            {conversions.length > 0 && (
                <div className="bg-white rounded-3xl shadow-lg border border-gray-100">
                    <div className="p-8">
                        <h2 className="text-2xl font-semibold text-gray-900 mb-6">Conversion Progress</h2>
                        <div className="space-y-4">
                            {conversions.map((conversion) => (
                                <div key={conversion.id} className="border border-gray-200 rounded-xl p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <span className="font-medium text-gray-900">{conversion.filename}</span>
                                        <span className="text-sm text-gray-500">
                                            Converting to {selectedFormat.toUpperCase()}
                                        </span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-3 mb-3">
                                        <div 
                                            className="bg-gradient-to-r from-blue-600 to-blue-700 h-3 rounded-full transition-all duration-300"
                                            style={{ width: `${conversion.progress || 0}%` }}
                                        ></div>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className={`text-sm font-medium ${
                                            conversion.status === 'completed' ? 'text-green-600' :
                                            conversion.status === 'failed' ? 'text-red-600' :
                                            'text-blue-600'
                                        }`}>
                                            {conversion.status === 'completed' ? 'Completed' :
                                             conversion.status === 'failed' ? 'Failed' :
                                             `${conversion.status}... ${conversion.progress || 0}%`}
                                        </span>
                                        {conversion.status === 'completed' && conversion.download_url && (
                                            <a
                                                href={conversion.download_url}
                                                download
                                                className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200"
                                            >
                                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Download
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default DocumentConverter;
