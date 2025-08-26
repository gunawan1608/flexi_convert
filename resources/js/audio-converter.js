import React from 'react';
import { createRoot } from 'react-dom/client';
import AudioConverter from './components/AudioConverter.jsx';

const container = document.getElementById('audio-converter-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(AudioConverter));
}
