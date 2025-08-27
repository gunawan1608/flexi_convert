<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Tools Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for FlexiConvert PDF processing tools
    |
    */

    // Primary engine for Office to PDF conversion
    'office_to_pdf_engine' => env('ENGINE_OFFICE_TO_PDF', 'libreoffice'),

    // LibreOffice configuration
    'libreoffice' => [
        'path' => env('LIBREOFFICE_PATH', null), // Override auto-detection
        'timeout' => env('LIBREOFFICE_TIMEOUT', 120), // seconds
    ],

    // Ghostscript configuration
    'ghostscript' => [
        'path' => env('GHOSTSCRIPT_PATH', null), // Override auto-detection
        'quality_presets' => [
            'high' => ['-dPDFSETTINGS=/prepress', '-dColorImageResolution=300'],
            'medium' => ['-dPDFSETTINGS=/printer', '-dColorImageResolution=150'],
            'low' => ['-dPDFSETTINGS=/ebook', '-dColorImageResolution=72'],
        ],
        'timeout' => env('GHOSTSCRIPT_TIMEOUT', 60),
    ],

    // File storage configuration
    'storage' => [
        'uploads_path' => 'pdf-tools/uploads',
        'outputs_path' => 'pdf-tools/outputs',
        'cleanup_retention_hours' => env('CLEANUP_RETENTION_HOURS', 24),
    ],

    // Image processing
    'image' => [
        'pdf_dpi' => 300,
        'max_dimension' => 2048,
        'quality' => 85,
    ],

    // Validation limits
    'validation' => [
        'max_file_size' => 100 * 1024 * 1024, // 100MB
        'max_files_merge' => 50,
        'allowed_extensions' => [
            'pdf' => ['pdf'],
            'word' => ['doc', 'docx', 'odt'],
            'excel' => ['xls', 'xlsx', 'ods'],
            'powerpoint' => ['ppt', 'pptx', 'odp'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
            'html' => ['html', 'htm'],
        ],
    ],

    // Logging and monitoring
    'logging' => [
        'log_conversions' => env('LOG_PDF_CONVERSIONS', true),
        'log_performance' => env('LOG_PDF_PERFORMANCE', true),
    ],
];
