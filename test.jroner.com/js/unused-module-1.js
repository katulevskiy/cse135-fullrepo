/* Unused-module-1.js - Module that initializes but does nothing */

(function(global) {
    'use strict';
    
    console.log('Loading unused-module-1.js');
    
    var UnusedModule1 = {
        version: '1.0.0-broken',
        initialized: false,
        config: {},
        data: [],
        callbacks: [],
        timers: [],
        
        init: function(options) {
            console.log('Initializing UnusedModule1 with options:', options);
            this.initialized = true;
            this.config = options || {};
            
            // Set up timers that do nothing
            this.timers.push(setInterval(function() {
                UnusedModule1.tick();
            }, 1000));
            
            // Load more dependencies
            this.loadDependencies();
            
            // Create memory leak
            this.createDataStructure();
            
            // Set up event listeners that won't fire
            this.setupEventListeners();
            
            return this;
        },
        
        tick: function() {
            // Do nothing but log
            if (Math.random() > 0.99) {
                console.log('UnusedModule1 tick:', Date.now());
            }
        },
        
        loadDependencies: function() {
            // Try to load non-existent dependencies
            var deps = [
                'https://cdn.example.com/lib-that-doesnt-exist.js',
                'https://unpkg.com/fake-package@latest/dist/fake.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/nonexistent/1.0.0/nonexistent.js'
            ];
            
            deps.forEach(function(dep) {
                var script = document.createElement('script');
                script.src = dep;
                script.onerror = function() {
                    console.error('UnusedModule1: Failed to load ' + dep);
                };
                document.head.appendChild(script);
            });
        },
        
        createDataStructure: function() {
            // Create a large, useless data structure
            for (var i = 0; i < 1000; i++) {
                this.data.push({
                    id: 'item_' + i,
                    value: Math.random(),
                    nested: {
                        deep: {
                            deeper: {
                                deepest: {
                                    value: new Array(100).fill(Math.random())
                                }
                            }
                        }
                    },
                    timestamp: Date.now(),
                    callback: function() {
                        console.log('This will never be called');
                    }
                });
            }
        },
        
        setupEventListeners: function() {
            // Set up listeners for events that don't exist
            document.addEventListener('module1:ready', function() {
                console.log('Module1 ready event (never fires)');
            });
            
            document.addEventListener('module1:error', function() {
                console.log('Module1 error event (never fires)');
            });
            
            // Listen for custom events on elements that don't exist
            var phantom = document.getElementById('phantom-element-module1');
            if (phantom) {
                phantom.addEventListener('click', function() {
                    UnusedModule1.handlePhantomClick();
                });
            }
        },
        
        // Public API that's never used
        api: {
            get: function(key) {
                throw new Error('API not implemented');
            },
            set: function(key, value) {
                throw new Error('API not implemented');
            },
            delete: function(key) {
                throw new Error('API not implemented');
            },
            list: function() {
                return [];
            }
        },
        
        // Fake promise-based API
        async: {
            load: function() {
                return new Promise(function(resolve, reject) {
                    setTimeout(function() {
                        reject(new Error('Async load failed'));
                    }, 5000);
                });
            },
            save: function() {
                return new Promise(function(resolve) {
                    // Never resolves
                });
            }
        },
        
        // Cleanup function that creates more mess
        destroy: function() {
            console.log('Destroying UnusedModule1');
            // Clear timers
            this.timers.forEach(function(timer) {
                clearInterval(timer);
            });
            // But create new ones
            setInterval(function() {
                console.error('UnusedModule1 was destroyed but still running');
            }, 10000);
        }
    };
    
    // Expose to global scope in multiple ways
    global.UnusedModule1 = UnusedModule1;
    global.UM1 = UnusedModule1;
    global.$module1 = UnusedModule1;
    
    // Auto-initialize with wrong config
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            UnusedModule1.init({ autoStart: true, debug: true });
        });
    } else {
        UnusedModule1.init({ autoStart: true, debug: true });
    }
    
    // Load the next module in chain
    var nextScript = document.createElement('script');
    nextScript.src = 'js/unused-module-2.js?chain=true';
    document.head.appendChild(nextScript);
    
})(window);