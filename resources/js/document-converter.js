import React from 'react';
import { createRoot } from 'react-dom/client';
import DocumentConverter from './components/DocumentConverter.jsx';

const container = document.getElementById('document-converter-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(DocumentConverter));
}
