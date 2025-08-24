// CSRF Token Handler for FlexiConvert
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh CSRF token every 10 minutes
    setInterval(refreshCsrfToken, 600000);
    
    // Refresh CSRF token before form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.method.toLowerCase() === 'post') {
            refreshCsrfToken();
        }
    });

    // Handle AJAX requests with fresh CSRF token
    if (window.axios) {
        window.axios.interceptors.request.use(function (config) {
            const token = document.querySelector('meta[name="csrf-token"]');
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
            }
            return config;
        });

        // Handle 419 errors by refreshing token and retrying
        window.axios.interceptors.response.use(
            response => response,
            error => {
                if (error.response && error.response.status === 419) {
                    return refreshCsrfToken().then(() => {
                        error.config.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        return window.axios.request(error.config);
                    });
                }
                return Promise.reject(error);
            }
        );
    }

    // Handle browser close/refresh events
    window.addEventListener('beforeunload', function(e) {
        // Clear sensitive data from localStorage/sessionStorage
        sessionStorage.clear();
        
        // Send logout request if user is authenticated
        if (document.querySelector('meta[name="user-authenticated"]')) {
            navigator.sendBeacon('/logout', new FormData());
        }
    });

    // Handle page visibility changes (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, start inactivity timer
            startInactivityTimer();
        } else {
            // Page is visible, refresh CSRF token
            refreshCsrfToken();
            clearInactivityTimer();
        }
    });

    let inactivityTimer;
    
    function startInactivityTimer() {
        // Auto logout after 30 minutes of inactivity
        inactivityTimer = setTimeout(() => {
            if (document.querySelector('meta[name="user-authenticated"]')) {
                window.location.href = '/logout';
            }
        }, 1800000); // 30 minutes
    }
    
    function clearInactivityTimer() {
        if (inactivityTimer) {
            clearTimeout(inactivityTimer);
        }
    }
});

function refreshCsrfToken() {
    return fetch('/csrf-token', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Update meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && data.csrf_token) {
            metaTag.setAttribute('content', data.csrf_token);
        }
        
        // Update all CSRF input fields
        const csrfInputs = document.querySelectorAll('input[name="_token"]');
        csrfInputs.forEach(input => {
            if (data.csrf_token) {
                input.value = data.csrf_token;
            }
        });
        
        return data.csrf_token;
    })
    .catch(error => {
        console.error('Failed to refresh CSRF token:', error);
    });
}
