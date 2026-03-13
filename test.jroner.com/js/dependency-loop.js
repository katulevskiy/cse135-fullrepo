/* Dependency-loop.js - Creates circular dependencies */

(function() {
    'use strict';
    
    console.log('Loading dependency-loop.js');
    
    // Check if we've already loaded dependencies
    if (!window.dependencyCount) {
        window.dependencyCount = 0;
    }
    window.dependencyCount++;
    
    if (window.dependencyCount > 10) {
        console.error('Dependency loop detected! But continuing anyway...');
    }
    
    // Load a chain of dependencies
    var dependencies = [
        'js/unused-module-1.js',
        'js/unused-module-2.js',
        'js/unused-module-3.js',
        'js/vendor/broken-lib.js',
        'js/vendor/obsolete-plugin.js',
        'https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.13.1/underscore-min.js', // Conflict with lodash
        'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/date-fns/1.30.1/date_fns.min.js', // Conflict with moment
        'https://cdnjs.cloudflare.com/ajax/libs/axios/0.21.1/axios.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/fetch/3.0.0/fetch.min.js' // Unnecessary polyfill
    ];
    
    // Load each dependency with a delay
    dependencies.forEach(function(dep, index) {
        setTimeout(function() {
            var script = document.createElement('script');
            script.src = dep + '?v=' + Date.now(); // Cache busting
            script.onerror = function() {
                console.error('Failed to load dependency: ' + dep);
                // Create a fake global for it anyway
                window['fakeModule' + index] = {
                    init: function() { throw new Error('Module not loaded'); },
                    version: 'ERROR',
                    data: new Array(1000).fill('waste')
                };
            };
            script.onload = function() {
                console.log('Loaded dependency: ' + dep);
                // Load analytics-broken.js again, creating a loop
                if (index === dependencies.length - 1 && window.dependencyCount < 5) {
                    var loopScript = document.createElement('script');
                    loopScript.src = 'js/analytics-broken.js?loop=' + window.dependencyCount;
                    document.head.appendChild(loopScript);
                }
            };
            document.head.appendChild(script);
        }, index * 500);
    });
    
    // Create fake modules that look like they do something
    window.UnusedModule = {
        init: function(config) {
            console.log('Initializing unused module with config:', config);
            this.config = config;
            this.startPolling();
        },
        startPolling: function() {
            setInterval(function() {
                fetch('https://api.wreckedtech.com/unused-endpoint')
                    .catch(function() { /* Silently fail */ });
            }, 10000);
        },
        performComplexCalculation: function() {
            var result = 0;
            for (var i = 0; i < 1000000; i++) {
                result += Math.sqrt(i) * Math.random();
            }
            return result;
        },
        data: new Array(10000).fill(null).map(function(_, i) {
            return {
                id: i,
                value: Math.random(),
                timestamp: Date.now(),
                metadata: {
                    source: 'unused-module',
                    processed: false,
                    errors: []
                }
            };
        })
    };
    
    // Load some web fonts that aren't used
    var fontLinks = [
        'https://fonts.googleapis.com/css2?family=Creepster&display=swap',
        'https://fonts.googleapis.com/css2?family=Nosifer&display=swap',
        'https://fonts.googleapis.com/css2?family=Eater&display=swap',
        'https://fonts.googleapis.com/css2?family=Butcherman&display=swap',
        'https://fonts.googleapis.com/css2?family=Griffy&display=swap'
    ];
    
    fontLinks.forEach(function(href) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    });
    
    // Load some CSS frameworks that conflict
    var cssFrameworks = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css',
        'https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css'
    ];
    
    cssFrameworks.forEach(function(href, index) {
        setTimeout(function() {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href + '?conflict=' + index;
            document.head.appendChild(link);
        }, index * 2000);
    });
    
})();