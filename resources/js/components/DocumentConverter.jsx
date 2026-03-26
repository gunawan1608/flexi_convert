import React, { useEffect, useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';

const toolCategories = [
    {
        id: 'convert-to',
        title: 'Convert to PDF',
        description: 'Gotenberg-backed document conversions with the strongest support today.',
        tools: [
            {
                id: 'word-to-pdf',
                name: 'Word -> PDF',
                description: 'Convert DOC or DOCX files into PDF.',
                icon: 'DOC',
                color: 'from-blue-500 to-blue-600',
                formats: ['.doc', '.docx', '.odt'],
            },
            {
                id: 'excel-to-pdf',
                name: 'Excel -> PDF',
                description: 'Convert spreadsheets into PDF while preserving layout as much as possible.',
                icon: 'XLS',
                color: 'from-emerald-500 to-emerald-600',
                formats: ['.xls', '.xlsx', '.ods'],
            },
            {
                id: 'ppt-to-pdf',
                name: 'PowerPoint -> PDF',
                description: 'Convert presentation decks into PDF.',
                icon: 'PPT',
                color: 'from-orange-500 to-orange-600',
                formats: ['.ppt', '.pptx', '.odp'],
            },
            {
                id: 'images-to-pdf',
                name: 'Images -> PDF',
                description: 'Combine multiple images into one PDF using Chromium rendering.',
                icon: 'IMG',
                color: 'from-fuchsia-500 to-fuchsia-600',
                formats: ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.tiff'],
                multiFile: true,
                settings: {
                    pageSize: 'A4',
                    orientation: 'portrait',
                    margin: '20',
                    imageSize: 'fit',
                },
            },
            {
                id: 'html-to-pdf',
                name: 'HTML -> PDF',
                description: 'Convert HTML files into PDF through Chromium.',
                icon: 'HTML',
                color: 'from-cyan-500 to-cyan-600',
                formats: ['.html', '.htm'],
            },
        ],
    },
    {
        id: 'pdf-tools',
        title: 'PDF Tools',
        description: 'PDF operations officially supported in Gotenberg-only mode.',
        tools: [
            {
                id: 'merge-pdf',
                name: 'Merge PDF',
                description: 'Combine multiple PDFs into a single document.',
                icon: 'PDF',
                color: 'from-teal-500 to-teal-600',
                formats: ['.pdf'],
                multiFile: true,
            },
            {
                id: 'split-pdf',
                name: 'Split PDF',
                description: 'Split a PDF by page range or interval.',
                icon: 'CUT',
                color: 'from-amber-500 to-amber-600',
                formats: ['.pdf'],
                settings: {
                    splitMode: 'interval',
                    interval: '1',
                },
            },
        ],
    },
];

const toolAcceptMap = {
    'word-to-pdf': {
        'application/msword': ['.doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
        'application/vnd.oasis.opendocument.text': ['.odt'],
    },
    'excel-to-pdf': {
        'application/vnd.ms-excel': ['.xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
        'application/vnd.oasis.opendocument.spreadsheet': ['.ods'],
    },
    'ppt-to-pdf': {
        'application/vnd.ms-powerpoint': ['.ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': ['.pptx'],
        'application/vnd.oasis.opendocument.presentation': ['.odp'],
    },
    'images-to-pdf': {
        'image/jpeg': ['.jpg', '.jpeg'],
        'image/png': ['.png'],
        'image/gif': ['.gif'],
        'image/bmp': ['.bmp'],
        'image/webp': ['.webp'],
        'image/tiff': ['.tiff'],
    },
    'html-to-pdf': {
        'text/html': ['.html', '.htm'],
    },
    'merge-pdf': {
        'application/pdf': ['.pdf'],
    },
    'split-pdf': {
        'application/pdf': ['.pdf'],
    },
};

const findTool = (toolId) =>
    toolCategories.flatMap((category) => category.tools).find((tool) => tool.id === toolId);

const DocumentConverter = () => {
    const [activeCategory, setActiveCategory] = useState(toolCategories[0].id);
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

    const applyHealthState = useCallback((healthResult) => {
        setEngineStatus(healthResult.ok ? 'up' : 'down');
        setEngineMessage(healthResult.message);
    }, []);

    const fetchHealth = useCallback(async () => {
        try {
            const response = await fetch('/api/pdf-tools/health', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (response.ok && data.success) {
                return {
                    ok: true,
                    message: 'Gotenberg is healthy and ready.',
                };
            }

            return {
                ok: false,
                message: data.message || 'Gotenberg is not ready.',
            };
        } catch (error) {
            return {
                ok: false,
                message: 'The health endpoint could not be reached.',
            };
        }
    }, []);

    useEffect(() => {
        let active = true;

        const runHealthCheck = async () => {
            const healthResult = await fetchHealth();

            if (!active) {
                return;
            }

            applyHealthState(healthResult);
        };

        runHealthCheck();
        const intervalId = window.setInterval(runHealthCheck, 15000);

        return () => {
            active = false;
            window.clearInterval(intervalId);
        };
    }, [applyHealthState, fetchHealth]);

    const onDrop = useCallback((acceptedFiles) => {
        if (!selectedTool) {
            return;
        }

        const validFiles = acceptedFiles.filter((file) => {
            const extension = '.' + file.name.split('.').pop().toLowerCase();
            return selectedTool.formats.includes(extension) && file.size <= 100 * 1024 * 1024;
        });

        setSelectedFiles((previous) => {
            if (!selectedTool.multiFile) {
                return validFiles.slice(0, 1);
            }

            return [...previous, ...validFiles];
        });
    }, [selectedTool]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: selectedTool ? toolAcceptMap[selectedTool.id] || {} : {},
        multiple: !!selectedTool?.multiFile,
        maxSize: 100 * 1024 * 1024,
        disabled: !selectedTool || engineStatus !== 'up',
    });

    const handleToolSelect = (tool) => {
        setSelectedTool(tool);
        setSelectedFiles([]);
        setToolSettings(tool.settings || {});
        setProcessingStatus('');
        setDownloadUrl(null);
        setOutputFilename(null);
        setErrorMessage(null);
    };

    const removeFile = (indexToRemove) => {
        setSelectedFiles((previous) => previous.filter((_, index) => index !== indexToRemove));
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) {
            return '0 Bytes';
        }

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const processFiles = async () => {
        if (!selectedTool || selectedFiles.length === 0) {
            return;
        }

        const healthResult = await fetchHealth();
        applyHealthState(healthResult);

        if (!healthResult.ok) {
            setErrorMessage(healthResult.message);
            setProcessingStatus('Conversion failed');
            return;
        }

        setIsProcessing(true);
        setProcessingStatus('Uploading files...');
        setErrorMessage(null);
        setDownloadUrl(null);

        try {
            const formData = new FormData();
            selectedFiles.forEach((file) => {
                formData.append('files[]', file);
            });
            formData.append('tool', selectedTool.id);

            let outputFormat = 'pdf';
            if (selectedTool.id === 'split-pdf') {
                outputFormat = toolSettings.splitMode === 'range' ? 'pdf' : 'zip';
            }

            formData.append('settings', JSON.stringify({
                ...toolSettings,
                format: outputFormat,
            }));

            setProcessingStatus('Processing conversion...');

            const response = await window.apiRequest('/api/pdf-tools/process', {
                method: 'POST',
                body: formData,
            });

            if (!response.success) {
                throw new Error(response.message || 'Conversion failed');
            }

            setProcessingStatus('Conversion completed!');
            setDownloadUrl(response.download_url);

            if (selectedFiles.length === 1) {
                const originalName = selectedFiles[0].name.replace(/\.[^/.]+$/, '');
                setOutputFilename(`${originalName}.${outputFormat}`);
            } else {
                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                setOutputFilename(`converted_documents_${timestamp}.${outputFormat}`);
            }
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
        if (!selectedTool) {
            return null;
        }

        if (selectedTool.id === 'split-pdf') {
            return (
                <div className="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <h4 className="font-semibold text-amber-900">Split Settings</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-amber-900">Mode</label>
                            <select
                                value={toolSettings.splitMode || 'interval'}
                                onChange={(event) => setToolSettings((previous) => ({
                                    ...previous,
                                    splitMode: event.target.value,
                                }))}
                                className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2"
                            >
                                <option value="interval">Interval pages</option>
                                <option value="range">Page range</option>
                            </select>
                        </div>

                        {toolSettings.splitMode === 'range' ? (
                            <>
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-amber-900">Start page</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={toolSettings.startPage || ''}
                                        onChange={(event) => setToolSettings((previous) => ({
                                            ...previous,
                                            startPage: event.target.value,
                                        }))}
                                        className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2"
                                    />
                                </div>
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-amber-900">End page</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={toolSettings.endPage || ''}
                                        onChange={(event) => setToolSettings((previous) => ({
                                            ...previous,
                                            endPage: event.target.value,
                                        }))}
                                        className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2"
                                    />
                                </div>
                            </>
                        ) : (
                            <div>
                                <label className="mb-2 block text-sm font-medium text-amber-900">Split every N pages</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={toolSettings.interval || '1'}
                                    onChange={(event) => setToolSettings((previous) => ({
                                        ...previous,
                                        interval: event.target.value,
                                    }))}
                                    className="w-full rounded-xl border border-amber-200 bg-white px-3 py-2"
                                />
                            </div>
                        )}
                    </div>
                </div>
            );
        }

        if (selectedTool.id === 'images-to-pdf') {
            return (
                <div className="space-y-4 rounded-2xl border border-fuchsia-200 bg-fuchsia-50 p-5">
                    <h4 className="font-semibold text-fuchsia-900">Image PDF Settings</h4>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-fuchsia-900">Page size</label>
                            <select
                                value={toolSettings.pageSize || 'A4'}
                                onChange={(event) => setToolSettings((previous) => ({
                                    ...previous,
                                    pageSize: event.target.value,
                                }))}
                                className="w-full rounded-xl border border-fuchsia-200 bg-white px-3 py-2"
                            >
                                <option value="A4">A4</option>
                                <option value="A3">A3</option>
                                <option value="A5">A5</option>
                                <option value="Letter">Letter</option>
                                <option value="Legal">Legal</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-fuchsia-900">Orientation</label>
                            <select
                                value={toolSettings.orientation || 'portrait'}
                                onChange={(event) => setToolSettings((previous) => ({
                                    ...previous,
                                    orientation: event.target.value,
                                }))}
                                className="w-full rounded-xl border border-fuchsia-200 bg-white px-3 py-2"
                            >
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-fuchsia-900">Margin (mm)</label>
                            <select
                                value={toolSettings.margin || '20'}
                                onChange={(event) => setToolSettings((previous) => ({
                                    ...previous,
                                    margin: event.target.value,
                                }))}
                                className="w-full rounded-xl border border-fuchsia-200 bg-white px-3 py-2"
                            >
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-fuchsia-900">Image fit</label>
                            <select
                                value={toolSettings.imageSize || 'fit'}
                                onChange={(event) => setToolSettings((previous) => ({
                                    ...previous,
                                    imageSize: event.target.value,
                                }))}
                                className="w-full rounded-xl border border-fuchsia-200 bg-white px-3 py-2"
                            >
                                <option value="fit">Fit to page</option>
                                <option value="fill">Fill page</option>
                            </select>
                        </div>
                    </div>
                </div>
            );
        }

        return null;
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
            <div className="container mx-auto space-y-8 px-4 py-8">
                <div className="space-y-4 text-center">
                    <div className="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 text-2xl font-bold text-white shadow-lg">
                        PDF
                    </div>
                    <div>
                        <h1 className="text-4xl font-bold text-slate-900">Document Converter</h1>
                        <p className="mt-3 text-lg text-slate-600">
                            This workspace now runs in Gotenberg-only mode for the document flows it officially supports.
                        </p>
                    </div>
                </div>

                <div className={`rounded-2xl border p-4 ${engineStatus === 'up' ? 'border-emerald-200 bg-emerald-50' : engineStatus === 'checking' ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50'}`}>
                    <p className={`text-sm font-semibold ${engineStatus === 'up' ? 'text-emerald-900' : engineStatus === 'checking' ? 'text-amber-900' : 'text-red-900'}`}>
                        Gotenberg status: {engineStatus === 'up' ? 'Ready' : engineStatus === 'checking' ? 'Checking' : 'Unavailable'}
                    </p>
                    <p className={`mt-1 text-sm ${engineStatus === 'up' ? 'text-emerald-800' : engineStatus === 'checking' ? 'text-amber-800' : 'text-red-800'}`}>
                        {engineMessage}
                    </p>
                    <p className="mt-2 text-sm text-slate-600">
                        Available here: Office to PDF, Images to PDF, HTML to PDF, Merge PDF, and Split PDF.
                    </p>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    {toolCategories.map((category) => (
                        <button
                            key={category.id}
                            type="button"
                            onClick={() => setActiveCategory(category.id)}
                            className={`rounded-2xl border p-5 text-left transition ${activeCategory === category.id ? 'border-blue-500 bg-white shadow-md' : 'border-slate-200 bg-white/70 hover:border-blue-300'}`}
                        >
                            <div className="text-lg font-semibold text-slate-900">{category.title}</div>
                            <div className="mt-1 text-sm text-slate-600">{category.description}</div>
                        </button>
                    ))}
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {toolCategories
                        .filter((category) => category.id === activeCategory)
                        .flatMap((category) => category.tools)
                        .map((tool) => (
                            <button
                                key={tool.id}
                                type="button"
                                onClick={() => handleToolSelect(tool)}
                                className={`rounded-3xl border p-5 text-left transition ${selectedTool?.id === tool.id ? 'border-blue-500 bg-white shadow-lg' : 'border-slate-200 bg-white/80 hover:border-blue-300 hover:shadow-md'}`}
                            >
                                <div className={`inline-flex rounded-xl bg-gradient-to-br px-3 py-2 text-sm font-bold text-white ${tool.color}`}>
                                    {tool.icon}
                                </div>
                                <h3 className="mt-4 text-lg font-semibold text-slate-900">{tool.name}</h3>
                                <p className="mt-2 text-sm text-slate-600">{tool.description}</p>
                                <p className="mt-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                                    {tool.formats.join(', ')}
                                </p>
                            </button>
                        ))}
                </div>

                {selectedTool && (
                    <div className="space-y-6 rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-lg">
                        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div>
                                <p className="text-sm font-semibold uppercase tracking-wide text-blue-600">Selected tool</p>
                                <h2 className="text-2xl font-bold text-slate-900">{selectedTool.name}</h2>
                                <p className="mt-1 text-slate-600">{selectedTool.description}</p>
                            </div>
                            <p className="text-sm text-slate-500">
                                {selectedTool.multiFile ? 'Multiple files allowed' : 'Single file only'}
                            </p>
                        </div>

                        {renderSettings()}

                        <div
                            {...getRootProps()}
                            className={`rounded-3xl border-2 border-dashed p-10 text-center transition ${
                                !selectedTool || engineStatus !== 'up'
                                    ? 'cursor-not-allowed border-slate-200 bg-slate-50'
                                    : isDragActive
                                        ? 'border-blue-400 bg-blue-50'
                                        : 'cursor-pointer border-slate-300 bg-slate-50 hover:border-blue-400 hover:bg-blue-50'
                            }`}
                        >
                            <input {...getInputProps()} />
                            <p className="text-2xl font-bold text-slate-900">
                                {isDragActive ? 'Drop files here' : 'Drag & drop your files'}
                            </p>
                            <p className="mt-2 text-slate-600">
                                or click to browse
                            </p>
                            <p className="mt-4 text-sm text-slate-500">
                                Supported formats: {selectedTool.formats.join(', ')}
                            </p>
                            <p className="text-sm text-slate-500">Maximum file size: 100MB per file</p>
                        </div>

                        {selectedFiles.length > 0 && (
                            <div className="space-y-3">
                                <h3 className="text-lg font-semibold text-slate-900">Selected files</h3>
                                <div className="grid gap-3 md:grid-cols-2">
                                    {selectedFiles.map((file, index) => (
                                        <div key={`${file.name}-${index}`} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <div>
                                                <p className="font-medium text-slate-900">{file.name}</p>
                                                <p className="text-sm text-slate-500">{formatFileSize(file.size)}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeFile(index)}
                                                disabled={isProcessing}
                                                className="rounded-xl px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {processingStatus && (
                            <div className={`rounded-2xl border p-4 ${errorMessage ? 'border-red-200 bg-red-50' : isProcessing ? 'border-blue-200 bg-blue-50' : 'border-emerald-200 bg-emerald-50'}`}>
                                <p className={`font-medium ${errorMessage ? 'text-red-800' : isProcessing ? 'text-blue-800' : 'text-emerald-800'}`}>
                                    {errorMessage || processingStatus}
                                </p>
                            </div>
                        )}

                        {downloadUrl && outputFilename && !isProcessing && (
                            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                <p className="font-semibold text-emerald-900">File ready for download</p>
                                <p className="mt-1 text-sm text-emerald-800">{outputFilename}</p>
                                <a
                                    href={downloadUrl}
                                    download={outputFilename}
                                    className={`mt-4 inline-flex rounded-xl bg-gradient-to-r px-5 py-3 text-sm font-semibold text-white shadow ${selectedTool.color}`}
                                >
                                    Download file
                                </a>
                            </div>
                        )}

                        <div className="flex flex-col gap-3 md:flex-row">
                            <button
                                type="button"
                                onClick={processFiles}
                                disabled={selectedFiles.length === 0 || isProcessing || engineStatus !== 'up'}
                                className={`flex-1 rounded-2xl px-6 py-4 font-semibold transition ${
                                    selectedFiles.length === 0 || isProcessing || engineStatus !== 'up'
                                        ? 'cursor-not-allowed bg-slate-200 text-slate-500'
                                        : `bg-gradient-to-r text-white shadow hover:opacity-95 ${selectedTool.color}`
                                }`}
                            >
                                {isProcessing ? 'Processing...' : 'Start conversion'}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setSelectedFiles([]);
                                    setToolSettings(selectedTool.settings || {});
                                    setProcessingStatus('');
                                    setDownloadUrl(null);
                                    setOutputFilename(null);
                                    setErrorMessage(null);
                                }}
                                disabled={isProcessing}
                                className="rounded-2xl border border-slate-200 bg-white px-6 py-4 font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default DocumentConverter;
