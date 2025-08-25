import './bootstrap';
import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import DocumentConverter from './components/DocumentConverter.jsx';

// Mount React Home App
const container = document.getElementById('react-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(App));
}

// Mount Document Converter App
const documentContainer = document.getElementById('document-converter-app');
if (documentContainer) {
    const root = createRoot(documentContainer);
    root.render(React.createElement(DocumentConverter));
}
