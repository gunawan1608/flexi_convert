import React from 'react';
import { createRoot } from 'react-dom/client';
import ImageConverter from './components/ImageConverter.jsx';

const container = document.getElementById('image-converter-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(ImageConverter));
}
