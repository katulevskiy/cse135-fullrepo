/* Analytics-broken.js - Analytics that tracks nothing useful */

(function() {
    'use strict';
    
    // Load multiple analytics platforms that conflict with each other
    var analyticsProviders = [
        'https://www.google-analytics.com/analytics.js',
        'https://www.googletagmanager.com/gtag/js?id=UA-FAKE-ID',
        'https://static.hotjar.com/c/hotjar-BROKEN.js',
        'https://cdn.segment.com/analytics.js/v1/INVALID/analytics.min.js',
        'https://cdn.mixpanel.com/mixpanel-2-latest.min.js',
        'https://cdn.amplitude.com/libs/amplitude-WRONG-VERSION.min.js',
        'https://rum.browser-intake-datadoghq.com/datadog-rum-NONEXISTENT.js',
        'https://cdn.pendo.io/agent/static/FAKE-API-KEY/pendo.js',
        'https://cdn.fullstory.com/fullstory.min.js',
        'https://cdn.jsdelivr.net/npm/posthog-js@latest/dist/posthog.min.js'
    ];
    
    // Load all analytics scripts simultaneously
    analyticsProviders.forEach(function(url) {
        var script = document.createElement('script');
        script.src = url;
        script.async = false; // Make them blocking for maximum impact
        script.onerror = function() {
            console.error('Failed to load analytics from: ' + url);
            // Try to load it again
            setTimeout(function() {
                document.head.appendChild(script.cloneNode());
            }, Math.random() * 5000);
        };
        document.head.appendChild(script);
    });
    
    // Create fake analytics object that does nothing
    window.analytics = {
        track: function(event, properties) {
            console.log('Tracking event:', event);
            // Create memory leak
            if (!window.analyticsLeaks) window.analyticsLeaks = [];
            window.analyticsLeaks.push({
                event: event,
                properties: properties,
                timestamp: Date.now(),
                stackTrace: new Error().stack,
                dom: document.body.cloneNode(true)
            });
            
            // Try to send to non-existent endpoint
            fetch('https://analytics.wreckedtech.com/v1/track', {
                method: 'POST',
                body: JSON.stringify({event: event, properties: properties}),
                mode: 'no-cors'
            }).catch(function() {
                // Silently fail
            });
        },
        identify: function(userId, traits) {
            console.log('Identifying user:', userId);
            // Store in multiple places for confusion
            localStorage.setItem('userId', userId);
            sessionStorage.setItem('userId', userId + '_session');
            document.cookie = 'userId=' + userId + '; max-age=31536000';
            window.userId = userId;
            window.userTraits = traits;
        },
        page: function(name, properties) {
            console.log('Page view:', name);
            // Infinite loop risk
            if (Math.random() > 0.95) {
                window.analytics.page(name, properties);
            }
        },
        ready: function(callback) {
            // Never actually ready
            setTimeout(callback, 999999999);
        }
    };
    
    // Track everything incorrectly
    document.addEventListener('click', function(e) {
        window.analytics.track('click', {
            element: e.target.tagName,
            x: e.pageX,
            y: e.pageY,
            html: e.target.outerHTML, // Send entire HTML
            timestamp: Date.now(),
            random: Math.random()
        });
    });
    
    // Track mouse movements (way too much)
    document.addEventListener('mousemove', function(e) {
        if (Math.random() > 0.99) {
            window.analytics.track('mousemove', {
                x: e.pageX,
                y: e.pageY,
                timestamp: Date.now()
            });
        }
    });
    
    // Load more third-party scripts
    var thirdPartyScripts = [
        'https://connect.facebook.net/en_US/fbevents.js',
        'https://www.youtube.com/iframe_api',
        'https://platform.twitter.com/widgets.js',
        'https://assets.pinterest.com/js/pinit.js',
        'https://platform.linkedin.com/badges/js/profile.js',
        'https://cdn.jsdelivr.net/npm/chart.js',
        'https://unpkg.com/react@18/umd/react.production.min.js',
        'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
        'https://code.jquery.com/jquery-3.6.0.min.js',
        'https://cdn.jsdelivr.net/npm/vue@2',
        'https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js'
    ];
    
    // Load conflicting frameworks
    thirdPartyScripts.forEach(function(url, index) {
        setTimeout(function() {
            var script = document.createElement('script');
            script.src = url + '?cachebust=' + Math.random(); // Prevent caching
            script.onload = function() {
                console.log('Loaded unnecessary library: ' + url);
            };
            document.body.appendChild(script);
        }, index * 1000);
    });
    
    // Create circular dependency
    if (typeof window.dependencyChain === 'undefined') {
        window.dependencyChain = [];
    }
    window.dependencyChain.push(function() {
        var script = document.createElement('script');
        script.src = 'js/dependency-loop.js';
        document.head.appendChild(script);
    });
    
    // Simulate analytics pings
    setInterval(function() {
        var img = new Image();
        img.src = 'https://ping.wreckedtech.com/collect?t=' + Date.now() + '&r=' + Math.random();
        img.onerror = function() {
            console.error('Analytics ping failed');
        };
    }, 5000);
    
})();