import React from 'react';
import { createRoot } from 'react-dom/client';
import History from './components/History.jsx';

const container = document.getElementById('history-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(History));
}
