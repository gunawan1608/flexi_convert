<?php

// Simple debug script to test Word to PDF conversion
echo "Testing LibreOffice conversion directly...\n";

$inputFile = "C:\\Users\\tamag\\Desktop\\test.docx"; // Change this to your test file
$outputDir = "C:\\Users\\tamag\\Desktop\\";
$outputFile = $outputDir . "test_output.pdf";

// Test LibreOffice command directly
$command = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe" --headless --invisible --nodefault --nolockcheck --nologo --norestore --convert-to pdf --outdir "' . $outputDir . '" "' . $inputFile . '"';

echo "Running command: " . $command . "\n";

$output = [];
$returnVar = 0;
exec($command . ' 2>&1', $output, $returnVar);

echo "Return code: " . $returnVar . "\n";
echo "Output: " . implode("\n", $output) . "\n";

if (file_exists($outputFile)) {
    echo "SUCCESS: PDF created at " . $outputFile . "\n";
    echo "File size: " . filesize($outputFile) . " bytes\n";
} else {
    echo "FAILED: No PDF file created\n";
}
