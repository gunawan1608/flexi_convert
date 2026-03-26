import React, { useCallback, useEffect, useState } from 'react';
import { useDropzone } from 'react-dropzone';

const categories = [
    {
        id: 'to-pdf',
        title: 'Convert to PDF',
        icon: 'PDF',
        gradient: 'from-blue-400 via-blue-500 to-blue-600',
        description: 'Convert office files, images, and HTML into PDF.',
        tools: [
            { id: 'word-to-pdf', name: 'Word to PDF', description: 'DOC, DOCX, ODT to PDF', icon: 'DOC', color: 'from-blue-500 to-blue-600', formats: ['.doc', '.docx', '.odt'], requiresEngine: true },
            { id: 'excel-to-pdf', name: 'Excel to PDF', description: 'XLS, XLSX, ODS to PDF', icon: 'XLS', color: 'from-emerald-500 to-emerald-600', formats: ['.xls', '.xlsx', '.ods'], requiresEngine: true },
            { id: 'ppt-to-pdf', name: 'PowerPoint to PDF', description: 'PPT, PPTX, ODP to PDF', icon: 'PPT', color: 'from-orange-500 to-orange-600', formats: ['.ppt', '.pptx', '.odp'], requiresEngine: true },
            { id: 'images-to-pdf', name: 'Images to PDF', description: 'Combine multiple images into one PDF', icon: 'IMG', color: 'from-fuchsia-500 to-fuchsia-600', formats: ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.tiff'], multiFile: true, requiresEngine: true, settings: { pageSize: 'A4', orientation: 'portrait', margin: '20', imageSize: 'fit' } },
            { id: 'html-to-pdf', name: 'HTML to PDF', description: 'Render HTML files into PDF', icon: 'HTML', color: 'from-cyan-500 to-cyan-600', formats: ['.html', '.htm'], requiresEngine: true },
        ],
    },
    {
        id: 'from-pdf',
        title: 'Convert from PDF',
        icon: 'DOC',
        gradient: 'from-emerald-400 via-emerald-500 to-emerald-600',
        description: 'Convert PDF files into editable or extracted formats.',
        tools: [
            { id: 'pdf-to-word', name: 'PDF to Word', description: 'Convert PDF into DOCX or RTF', icon: 'DOCX', color: 'from-emerald-500 to-emerald-600', formats: ['.pdf'] },
            { id: 'pdf-to-excel', name: 'PDF to Excel', description: 'Extract spreadsheet-friendly data', icon: 'XLSX', color: 'from-teal-500 to-teal-600', formats: ['.pdf'] },
            { id: 'pdf-to-ppt', name: 'PDF to PowerPoint', description: 'Turn PDF pages into slides', icon: 'PPTX', color: 'from-amber-500 to-orange-600', formats: ['.pdf'] },
            { id: 'pdf-to-image', name: 'PDF to Images', description: 'Export pages as an image archive', icon: 'ZIP', color: 'from-pink-500 to-rose-600', formats: ['.pdf'] },
        ],
    },
    {
        id: 'pdf-tools',
        title: 'PDF Tools',
        icon: 'KIT',
        gradient: 'from-purple-400 via-purple-500 to-purple-600',
        description: 'Merge and split PDF documents.',
        tools: [
            { id: 'merge-pdf', name: 'Merge PDF', description: 'Combine multiple PDFs', icon: 'MRG', color: 'from-violet-500 to-violet-600', formats: ['.pdf'], multiFile: true, requiresEngine: true },
            { id: 'split-pdf', name: 'Split PDF', description: 'Split by interval or range', icon: 'CUT', color: 'from-sky-500 to-sky-600', formats: ['.pdf'], requiresEngine: true, settings: { splitMode: 'interval', interval: '1' } },
        ],
    },
];

const acceptMap = {
    'word-to-pdf': { 'application/msword': ['.doc'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'], 'application/vnd.oasis.opendocument.text': ['.odt'] },
    'excel-to-pdf': { 'application/vnd.ms-excel': ['.xls'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'], 'application/vnd.oasis.opendocument.spreadsheet': ['.ods'] },
    'ppt-to-pdf': { 'application/vnd.ms-powerpoint': ['.ppt'], 'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'], 'application/vnd.oasis.opendocument.presentation': ['.odp'] },
    'images-to-pdf': { 'image/jpeg': ['.jpg', '.jpeg'], 'image/png': ['.png'], 'image/gif': ['.gif'], 'image/bmp': ['.bmp'], 'image/webp': ['.webp'], 'image/tiff': ['.tiff'] },
    'html-to-pdf': { 'text/html': ['.html', '.htm'] },
    'pdf-to-word': { 'application/pdf': ['.pdf'] },
    'pdf-to-excel': { 'application/pdf': ['.pdf'] },
    'pdf-to-ppt': { 'application/pdf': ['.pdf'] },
    'pdf-to-image': { 'application/pdf': ['.pdf'] },
    'merge-pdf': { 'application/pdf': ['.pdf'] },
    'split-pdf': { 'application/pdf': ['.pdf'] },
};

const getOutputExt = (toolId, settings = {}, serverName = null) => {
    const serverExt = serverName?.includes('.') ? serverName.split('.').pop().toLowerCase() : null;
    if (serverExt) return serverExt;
    if (['word-to-pdf', 'excel-to-pdf', 'ppt-to-pdf', 'images-to-pdf', 'html-to-pdf', 'merge-pdf'].includes(toolId)) return 'pdf';
    if (toolId === 'split-pdf') return settings.splitMode === 'range' ? 'pdf' : 'zip';
    if (toolId === 'pdf-to-word') return 'docx';
    if (toolId === 'pdf-to-excel') return 'xlsx';
    if (toolId === 'pdf-to-ppt') return 'pptx';
    if (toolId === 'pdf-to-image') return 'zip';
    return 'pdf';
};

const getOutputName = (tool, files, settings, serverName) => {
    if (!tool || files.length === 0) return serverName || null;
    const ext = getOutputExt(tool.id, settings, serverName);
    const base = files[0].name.replace(/\.[^/.]+$/, '');
    if (tool.id === 'merge-pdf') return 'merged_document.pdf';
    if (tool.id === 'split-pdf') return `${base}_split.${ext}`;
    if (tool.id === 'pdf-to-image') return `${base}_images.${ext}`;
    if (files.length === 1) return `${base}.${ext}`;
    const stamp = new Date().toISOString().replace(/[:.]/g, '-');
    return `converted_documents_${stamp}.${ext}`;
};

const actionMap = {
    'word-to-pdf': 'Convert to PDF',
    'excel-to-pdf': 'Convert to PDF',
    'ppt-to-pdf': 'Convert to PDF',
    'images-to-pdf': 'Build PDF',
    'html-to-pdf': 'Render PDF',
    'pdf-to-word': 'Convert to Word',
    'pdf-to-excel': 'Convert to Excel',
    'pdf-to-ppt': 'Convert to PowerPoint',
    'pdf-to-image': 'Extract Images',
    'merge-pdf': 'Merge PDF',
    'split-pdf': 'Split PDF',
};

const DocumentConverter = () => {
    const [activeCategory, setActiveCategory] = useState('to-pdf');
    const [selectedTool, setSelectedTool] = useState(null);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [toolSettings, setToolSettings] = useState({});
    const [isProcessing, setIsProcessing] = useState(false);
    const [processingStatus, setProcessingStatus] = useState('');
    const [downloadUrl, setDownloadUrl] = useState(null);
    const [outputFilename, setOutputFilename] = useState(null);
    const [errorMessage, setErrorMessage] = useState(null);
    const [engineStatus, setEngineStatus] = useState('checking');
    const [engineMessage, setEngineMessage] = useState('Checking Gotenberg service...');

    const fetchHealth = useCallback(async () => {
        try {
            const response = await fetch('/api/pdf-tools/health', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json();
            if (response.ok && data.success) return { ok: true, message: 'Gotenberg is healthy and ready for PDF engine tasks.' };
            return { ok: false, message: data.message || 'Gotenberg is not ready.' };
        } catch {
            return { ok: false, message: 'The health endpoint could not be reached.' };
        }
    }, []);

    useEffect(() => {
        let active = true;
        const run = async () => {
            const result = await fetchHealth();
            if (!active) return;
            setEngineStatus(result.ok ? 'up' : 'down');
            setEngineMessage(result.message);
        };
        run();
        const intervalId = window.setInterval(run, 15000);
        return () => {
            active = false;
            window.clearInterval(intervalId);
        };
    }, [fetchHealth]);

    const onDrop = useCallback((acceptedFiles) => {
        if (!selectedTool) return;
        const valid = acceptedFiles.filter((file) => {
            const ext = '.' + file.name.split('.').pop().toLowerCase();
            return selectedTool.formats.includes(ext) && file.size <= 100 * 1024 * 1024;
        });
        setSelectedFiles((prev) => (selectedTool.multiFile ? [...prev, ...valid] : valid.slice(0, 1)));
    }, [selectedTool]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: selectedTool ? acceptMap[selectedTool.id] || {} : {},
        multiple: !!selectedTool?.multiFile,
        maxSize: 100 * 1024 * 1024,
        disabled: !selectedTool,
    });

    const resetState = (tool = selectedTool) => {
        setSelectedFiles([]);
        setToolSettings(tool?.settings || {});
        setProcessingStatus('');
        setDownloadUrl(null);
        setOutputFilename(null);
        setErrorMessage(null);
    };

    const handleToolSelect = (tool) => {
        setSelectedTool(tool);
        resetState(tool);
    };

    const processFiles = async () => {
        if (!selectedTool || selectedFiles.length === 0) return;
        if (selectedTool.requiresEngine) {
            const result = await fetchHealth();
            setEngineStatus(result.ok ? 'up' : 'down');
            setEngineMessage(result.message);
            if (!result.ok) {
                setErrorMessage(result.message);
                setProcessingStatus('Conversion failed');
                return;
            }
        }
        setIsProcessing(true);
        setProcessingStatus('Uploading files...');
        setErrorMessage(null);
        setDownloadUrl(null);
        setOutputFilename(null);
        try {
            const formData = new FormData();
            selectedFiles.forEach((file) => formData.append('files[]', file));
            formData.append('tool', selectedTool.id);
            formData.append('settings', JSON.stringify(toolSettings));
            setProcessingStatus('Processing conversion...');
            const response = await window.apiRequest('/api/pdf-tools/process', { method: 'POST', body: formData });
            setProcessingStatus('Conversion completed!');
            setDownloadUrl(response.download_url);
            setOutputFilename(getOutputName(selectedTool, selectedFiles, toolSettings, response.output_filename));
        } catch (error) {
            if (/gotenberg/i.test(error.message)) {
                setEngineStatus('down');
                setEngineMessage(error.message);
            }
            setErrorMessage(error.message || 'An error occurred during processing');
            setProcessingStatus('Conversion failed');
        } finally {
            setIsProcessing(false);
        }
    };

    const renderSettings = () => {
        if (selectedTool?.id === 'split-pdf') {
            return (
                <div className="space-y-4 rounded-2xl border border-sky-200 bg-sky-50 p-6">
                    <h4 className="font-semibold text-sky-900">Split Settings</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-sky-900">Mode</label>
                            <select value={toolSettings.splitMode || 'interval'} onChange={(e) => setToolSettings((p) => ({ ...p, splitMode: e.target.value }))} className="w-full rounded-xl border border-sky-200 bg-white px-4 py-2">
                                <option value="interval">Interval pages</option>
                                <option value="range">Page range</option>
                            </select>
                        </div>
                        {toolSettings.splitMode === 'range' ? (
                            <>
                                <input type="number" min="1" value={toolSettings.startPage || ''} onChange={(e) => setToolSettings((p) => ({ ...p, startPage: e.target.value }))} className="w-full rounded-xl border border-sky-200 bg-white px-4 py-2" placeholder="Start page" />
                                <input type="number" min="1" value={toolSettings.endPage || ''} onChange={(e) => setToolSettings((p) => ({ ...p, endPage: e.target.value }))} className="w-full rounded-xl border border-sky-200 bg-white px-4 py-2" placeholder="End page" />
                            </>
                        ) : (
                            <input type="number" min="1" value={toolSettings.interval || '1'} onChange={(e) => setToolSettings((p) => ({ ...p, interval: e.target.value }))} className="w-full rounded-xl border border-sky-200 bg-white px-4 py-2" placeholder="Split every N pages" />
                        )}
                    </div>
                </div>
            );
        }
        if (selectedTool?.id === 'images-to-pdf') {
            return (
                <div className="space-y-4 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 p-6">
                    <h4 className="font-semibold text-fuchsia-900">Image PDF Settings</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <select value={toolSettings.pageSize || 'A4'} onChange={(e) => setToolSettings((p) => ({ ...p, pageSize: e.target.value }))} className="w-full rounded-xl border border-fuchsia-200 bg-white px-4 py-2"><option value="A4">A4</option><option value="A3">A3</option><option value="A5">A5</option><option value="Letter">Letter</option><option value="Legal">Legal</option></select>
                        <select value={toolSettings.orientation || 'portrait'} onChange={(e) => setToolSettings((p) => ({ ...p, orientation: e.target.value }))} className="w-full rounded-xl border border-fuchsia-200 bg-white px-4 py-2"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select>
                        <select value={toolSettings.margin || '20'} onChange={(e) => setToolSettings((p) => ({ ...p, margin: e.target.value }))} className="w-full rounded-xl border border-fuchsia-200 bg-white px-4 py-2"><option value="10">10</option><option value="20">20</option><option value="30">30</option><option value="40">40</option></select>
                        <select value={toolSettings.imageSize || 'fit'} onChange={(e) => setToolSettings((p) => ({ ...p, imageSize: e.target.value }))} className="w-full rounded-xl border border-fuchsia-200 bg-white px-4 py-2"><option value="fit">Fit to page</option><option value="fill">Fill page</option></select>
                    </div>
                </div>
            );
        }
        return null;
    };

    const completedCount = downloadUrl && outputFilename && !isProcessing ? 1 : 0;

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-cyan-50">
            <div className="container mx-auto space-y-8 px-4 py-8">
                <div className="space-y-6 text-center">
                    <div className="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg"><span className="text-2xl font-bold text-white">PDF</span></div>
                    <div>
                        <h1 className="mb-4 bg-gradient-to-r from-gray-900 via-blue-900 to-cyan-900 bg-clip-text text-4xl font-bold text-transparent lg:text-6xl">Document Converter</h1>
                        <p className="mx-auto max-w-3xl text-xl text-gray-600">Convert documents to PDF, convert PDFs back into editable formats, and handle merge or split workflows from one place.</p>
                    </div>
                    <div className="flex justify-center space-x-8 text-center">
                        <div className="rounded-2xl bg-white/70 px-6 py-4 shadow-lg backdrop-blur-sm"><div className="text-2xl font-bold text-blue-600">{selectedFiles.length}</div><div className="text-sm text-gray-600">Files Selected</div></div>
                        <div className="rounded-2xl bg-white/70 px-6 py-4 shadow-lg backdrop-blur-sm"><div className="text-2xl font-bold text-emerald-600">{completedCount}</div><div className="text-sm text-gray-600">Completed</div></div>
                        <div className="rounded-2xl bg-white/70 px-6 py-4 shadow-lg backdrop-blur-sm"><div className={`text-2xl font-bold ${engineStatus === 'up' ? 'text-cyan-600' : engineStatus === 'checking' ? 'text-amber-600' : 'text-red-600'}`}>{engineStatus === 'up' ? 'ON' : engineStatus === 'checking' ? '...' : 'OFF'}</div><div className="text-sm text-gray-600">PDF Engine</div></div>
                    </div>
                </div>

                <div className={`rounded-2xl border p-5 ${engineStatus === 'up' ? 'border-emerald-200 bg-emerald-50' : engineStatus === 'checking' ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50'}`}>
                    <p className={`text-sm font-semibold ${engineStatus === 'up' ? 'text-emerald-900' : engineStatus === 'checking' ? 'text-amber-900' : 'text-red-900'}`}>Gotenberg status: {engineStatus === 'up' ? 'Ready' : engineStatus === 'checking' ? 'Checking' : 'Unavailable'}</p>
                    <p className={`mt-1 text-sm ${engineStatus === 'up' ? 'text-emerald-800' : engineStatus === 'checking' ? 'text-amber-800' : 'text-red-800'}`}>{engineMessage}</p>
                    <p className="mt-2 text-sm text-slate-600">Word/Excel/PPT to PDF, Images to PDF, HTML to PDF, Merge PDF, and Split PDF rely on this engine. The from-PDF tools use their own backend flow.</p>
                </div>

                <div className="flex justify-center"><div className="rounded-2xl border border-white/20 bg-white/80 p-2 shadow-lg backdrop-blur-sm"><div className="flex flex-wrap justify-center gap-2">{categories.map((category) => <button key={category.id} onClick={() => setActiveCategory(category.id)} className={`rounded-xl px-6 py-3 font-semibold transition-all duration-300 ${activeCategory === category.id ? `bg-gradient-to-r ${category.gradient} text-white shadow-lg` : 'text-gray-600 hover:bg-white/50 hover:text-gray-900'}`}><span className="mr-2">{category.icon}</span>{category.title}</button>)}</div></div></div>

                {categories.map((category) => activeCategory === category.id && (
                    <div key={category.id} className="space-y-6">
                        <div className="text-center"><h2 className="mb-2 text-3xl font-bold text-gray-900">{category.title}</h2><p className="text-gray-600">{category.description}</p></div>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                            {category.tools.map((tool, index) => (
                                <div key={tool.id} className={`group cursor-pointer transform transition-all duration-300 hover:scale-105 ${selectedTool?.id === tool.id ? 'scale-105' : ''}`} style={{ animationDelay: `${index * 100}ms` }} onClick={() => handleToolSelect(tool)}>
                                    <div className={`relative overflow-hidden rounded-2xl shadow-lg transition-all duration-300 hover:shadow-2xl ${selectedTool?.id === tool.id ? `bg-gradient-to-br ${tool.color} text-white ring-4 ring-blue-300` : 'bg-white hover:bg-gray-50'}`}>
                                        <div className="absolute inset-0 opacity-5"><div className="absolute inset-0 rotate-45 bg-gradient-to-br from-transparent via-white to-transparent" /></div>
                                        <div className="relative space-y-4 p-6 text-center">
                                            <div className={`inline-flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg ${selectedTool?.id === tool.id ? 'bg-white/20' : `bg-gradient-to-br ${tool.color}`}`}><span className="text-sm font-bold text-white">{tool.icon}</span></div>
                                            <div><h3 className={`text-lg font-bold ${selectedTool?.id === tool.id ? 'text-white' : 'text-gray-900'}`}>{tool.name}</h3><p className={`text-sm ${selectedTool?.id === tool.id ? 'text-white/80' : 'text-gray-600'}`}>{tool.description}</p></div>
                                            <div className={`text-xs ${selectedTool?.id === tool.id ? 'text-white/60' : 'text-gray-400'}`}>{tool.formats.join(', ')}</div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                {selectedTool && (
                    <div className="overflow-hidden rounded-3xl border border-white/20 bg-white/80 shadow-xl backdrop-blur-sm">
                        <div className="space-y-6 p-8">
                            <div className="text-center">
                                <div className={`mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br ${selectedTool.color} shadow-lg`}><span className="text-sm font-bold text-white">{selectedTool.icon}</span></div>
                                <h3 className="text-2xl font-bold text-gray-900">{selectedTool.name}</h3>
                                <p className="text-gray-600">{selectedTool.description}</p>
                                <p className="mt-2 text-sm text-gray-500">{selectedTool.multiFile ? 'Multiple files allowed' : 'Single file only'}{selectedTool.requiresEngine ? ' • Uses Gotenberg engine' : ' • Uses local PDF processing'}</p>
                            </div>

                            {renderSettings()}

                            <div {...getRootProps()} className={`relative cursor-pointer rounded-3xl border-2 border-dashed p-12 text-center transition-all duration-300 ${isDragActive ? 'scale-105 border-blue-400 bg-blue-50/50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/30'}`}>
                                <input {...getInputProps()} />
                                <div className="space-y-6">
                                    <div className={`mx-auto flex h-24 w-24 items-center justify-center rounded-full transition-all duration-300 ${isDragActive ? 'scale-110 bg-gradient-to-br from-blue-400 to-cyan-600' : 'bg-gradient-to-br from-gray-100 to-gray-200'}`}><svg className={`h-12 w-12 transition-colors duration-300 ${isDragActive ? 'text-white' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg></div>
                                    <div><p className="mb-2 text-2xl font-bold text-gray-900">{isDragActive ? 'Drop files here!' : 'Drag & drop your document files'}</p><p className="text-gray-600">or <span className="font-semibold text-blue-600">click to browse</span></p></div>
                                    <div className="space-y-1 text-sm text-gray-500"><p>Supported formats: {selectedTool.formats.join(', ')}</p><p>Maximum file size: 100MB per file</p>{selectedTool.requiresEngine && engineStatus !== 'up' && <p className="font-medium text-amber-600">Engine status will be checked before processing.</p>}</div>
                                </div>
                            </div>

                            {selectedFiles.length > 0 && <div className="space-y-4"><h4 className="font-semibold text-gray-900">Selected Files ({selectedFiles.length})</h4><div className="grid max-h-64 grid-cols-1 gap-4 overflow-y-auto md:grid-cols-2">{selectedFiles.map((file, index) => <div key={`${file.name}-${index}`} className="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-shadow duration-200 hover:shadow-md"><div className="flex items-center space-x-3"><div className={`flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br ${selectedTool.color}`}><span className="text-xs font-bold text-white">{file.name.split('.').pop().toUpperCase()}</span></div><div><p className="max-w-48 truncate font-medium text-gray-900" title={file.name}>{file.name}</p><p className="text-sm text-gray-500">{formatFileSize(file.size)}</p></div></div><button onClick={() => setSelectedFiles((prev) => prev.filter((_, i) => i !== index))} disabled={isProcessing} className="rounded-lg p-2 text-red-500 transition-colors duration-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50"><svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" /></svg></button></div>)}</div></div>}

                            {processingStatus && <div className={`rounded-xl border p-4 ${isProcessing ? 'border-blue-200 bg-blue-50' : errorMessage ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50'}`}><div className="flex items-center space-x-3">{isProcessing && <svg className="h-5 w-5 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>}{!isProcessing && !errorMessage && <svg className="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" /></svg>}{errorMessage && <svg className="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" /></svg>}<span className={`font-medium ${isProcessing ? 'text-blue-800' : errorMessage ? 'text-red-800' : 'text-green-800'}`}>{errorMessage || processingStatus}</span></div></div>}

                            {downloadUrl && outputFilename && !isProcessing && <div className="rounded-xl border border-green-200 bg-green-50 p-6"><div className="space-y-4 text-center"><div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100"><svg className="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2" /></svg></div><div><h4 className="mb-2 text-lg font-semibold text-green-800">File Ready for Download</h4><p className="text-green-700">Your processed file: <span className="font-medium">{outputFilename}</span></p></div><a href={downloadUrl} download={outputFilename} className={`inline-flex items-center justify-center rounded-xl bg-gradient-to-r px-6 py-3 font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg ${selectedTool.color}`}><svg className="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2" /></svg>Download File</a></div></div>}

                            <div className="flex flex-col gap-4 sm:flex-row">
                                <button onClick={processFiles} disabled={selectedFiles.length === 0 || isProcessing || (selectedTool.requiresEngine && engineStatus !== 'up')} className={`flex-1 rounded-xl px-8 py-4 font-semibold transition-all duration-300 ${selectedFiles.length === 0 || isProcessing || (selectedTool.requiresEngine && engineStatus !== 'up') ? 'cursor-not-allowed bg-gray-300 text-gray-500' : `bg-gradient-to-r ${selectedTool.color} text-white hover:scale-105 hover:shadow-lg active:scale-95`}`}>{isProcessing ? <span className="flex items-center justify-center"><svg className="-ml-1 mr-3 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" /><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>Processing...</span> : <span className="flex items-center justify-center"><svg className="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>{actionMap[selectedTool.id] || 'Process Document'}</span>}</button>
                                <button onClick={() => resetState()} className="rounded-xl border-2 border-gray-300 px-8 py-4 font-semibold text-gray-700 transition-all duration-200 hover:border-gray-400 hover:bg-gray-50">Clear All</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DocumentConverter;
