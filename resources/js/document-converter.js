import React from 'react';
import { createRoot } from 'react-dom/client';
import DocumentConverter from './components/DocumentConverter.jsx';

const container = document.getElementById('document-converter-app');
if (container) {
    const root = createRoot(container);
    root.render(React.createElement(DocumentConverter));
}

// Enhanced error handling for fetch requests
window.handleApiResponse = async function(response) {
    try {
        const data = await response.json();
        
        // Check for error responses
        if (data.error || !data.success) {
            throw new Error(data.message || 'Unknown error occurred');
        }
        
        return data;
    } catch (error) {
        if (error.name === 'SyntaxError') {
            // JSON parsing failed - likely HTML error page
            throw new Error('Server returned invalid response. Please check server logs.');
        }
        throw error;
    }
};

// Global error handler for unhandled promise rejections
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    
    // Show user-friendly error message
    if (typeof window.showErrorMessage === 'function') {
        window.showErrorMessage('An unexpected error occurred. Please try again.');
    }
});

// Enhanced fetch wrapper with proper error handling
window.apiRequest = async function(url, options = {}) {
    try {
        // Ensure CSRF token is included
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const defaultOptions = {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                ...options.headers
            },
            ...options
        };
        
        const response = await fetch(url, defaultOptions);

        const parseErrorMessage = async () => {
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                try {
                    const errorData = await response.json();
                    return errorData.message || errorData.error || null;
                } catch {
                    return null;
                }
            }

            try {
                const text = await response.text();
                return text.trim() || null;
            } catch {
                return null;
            }
        };
        
        // Handle non-200 status codes
        if (!response.ok) {
            const serverMessage = await parseErrorMessage();

            if (response.status === 419) {
                throw new Error(serverMessage || 'Session expired. Please refresh the page.');
            } else if (response.status === 422) {
                throw new Error(serverMessage || 'Validation failed');
            } else if (response.status === 503) {
                throw new Error(serverMessage || 'Gotenberg is not reachable. Start it first with Docker.');
            } else if (response.status >= 500) {
                throw new Error(serverMessage || 'Server error. Please try again later.');
            } else {
                throw new Error(serverMessage || `Request failed with status ${response.status}`);
            }
        }
        
        return await window.handleApiResponse(response);
    } catch (error) {
        console.error('API Request failed:', error);
        throw error;
    }
};
