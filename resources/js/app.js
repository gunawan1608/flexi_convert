import './bootstrap';
import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';

// Mount React Home App
const container = document.getElementById('react-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(App));
}
