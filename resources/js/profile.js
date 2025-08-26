import React from 'react';
import { createRoot } from 'react-dom/client';
import Profile from './components/Profile.jsx';

const container = document.getElementById('profile-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(Profile));
}
