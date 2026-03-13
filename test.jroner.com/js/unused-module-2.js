/* Unused-module-2.js - Another module that conflicts with module 1 */

(function(global) {
    'use strict';
    
    console.log('Loading unused-module-2.js');
    
    // Check if Module1 loaded and override it
    if (global.UnusedModule1) {
        console.warn('UnusedModule1 detected, overriding its methods');
        global.UnusedModule1.init = function() {
            throw new Error('Module1 has been hijacked by Module2');
        };
    }
    
    var UnusedModule2 = {
        version: '2.0.0-more-broken',
        state: 'uninitialized',
        queue: [],
        workers: [],
        
        bootstrap: function() {
            console.log('Bootstrapping UnusedModule2');
            this.state = 'bootstrapping';
            
            // Try to use Web Workers unnecessarily
            if (typeof Worker !== 'undefined') {
                try {
                    var blob = new Blob(['postMessage("worker failed");'], { type: 'application/javascript' });
                    var worker = new Worker(URL.createObjectURL(blob));
                    worker.onmessage = function(e) {
                        console.error('Worker message:', e.data);
                    };
                    this.workers.push(worker);
                } catch (e) {
                    console.error('Failed to create worker:', e);
                }
            }
            
            // Load conflicting libraries
            this.loadConflictingLibraries();
            
            // Set up infinite recursion risk
            this.setupRecursion();
            
            // Pollute global scope
            this.polluteGlobals();
            
            return this;
        },
        
        loadConflictingLibraries: function() {
            // Load multiple versions of the same library
            var libs = [
                'https://code.jquery.com/jquery-1.12.4.min.js',
                'https://code.jquery.com/jquery-2.2.4.min.js',
                'https://code.jquery.com/jquery-3.6.0.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/prototype/1.7.3/prototype.min.js', // Conflicts with jQuery
                'https://cdnjs.cloudflare.com/ajax/libs/mootools/1.6.0/mootools-core.min.js' // More conflicts
            ];
            
            libs.forEach(function(lib, index) {
                setTimeout(function() {
                    var script = document.createElement('script');
                    script.src = lib;
                    script.onload = function() {
                        console.log('Loaded conflicting library:', lib);
                    };
                    document.body.appendChild(script);
                }, index * 1000);
            });
        },
        
        setupRecursion: function() {
            var depth = 0;
            var maxDepth = 100;
            
            function recurse() {
                depth++;
                if (depth < maxDepth) {
                    // Sometimes recurse immediately, sometimes with delay
                    if (Math.random() > 0.5) {
                        recurse();
                    } else {
                        setTimeout(recurse, 0);
                    }
                } else {
                    depth = 0; // Reset and start again
                    setTimeout(recurse, 5000);
                }
            }
            
            // Start recursion
            recurse();
        },
        
        polluteGlobals: function() {
            // Add random properties to window
            var pollution = [
                'data', 'config', 'state', 'cache', 'buffer',
                'queue', 'stack', 'heap', 'memory', 'storage'
            ];
            
            pollution.forEach(function(prop) {
                if (!global[prop]) {
                    global[prop] = {
                        module2: true,
                        data: new Array(1000).fill('pollution'),
                        timestamp: Date.now()
                    };
                }
            });
            
            // Override native methods (bad practice)
            var originalParse = JSON.parse;
            JSON.parse = function(str) {
                console.log('JSON.parse intercepted by Module2');
                try {
                    return originalParse.call(this, str);
                } catch (e) {
                    return { error: 'Parsing failed', original: str };
                }
            };
            
            // Add properties to native prototypes (very bad)
            Array.prototype.superSort = function() {
                console.error('Array.superSort is not implemented');
                return this.sort(function() { return Math.random() - 0.5; });
            };
            
            String.prototype.encrypt = function() {
                return btoa(this.toString());
            };
            
            Number.prototype.random = function() {
                return this * Math.random();
            };
        },
        
        // Fake WebSocket connection
        connectWebSocket: function() {
            try {
                var ws = new WebSocket('wss://nonexistent.wreckedtech.com/socket');
                ws.onopen = function() {
                    console.log('WebSocket opened (impossible)');
                };
                ws.onerror = function(error) {
                    console.error('WebSocket error:', error);
                    // Try again
                    setTimeout(function() {
                        UnusedModule2.connectWebSocket();
                    }, 5000);
                };
            } catch (e) {
                console.error('WebSocket failed:', e);
            }
        },
        
        // Start everything
        start: function() {
            this.state = 'running';
            this.bootstrap();
            this.connectWebSocket();
            
            // Create interval that can't be stopped
            var that = this;
            setInterval(function() {
                that.queue.push({
                    timestamp: Date.now(),
                    random: Math.random(),
                    data: new Array(100).fill('queue-item')
                });
                
                if (that.queue.length > 10000) {
                    console.warn('Queue overflow, clearing...');
                    that.queue = that.queue.slice(-1000); // Keep last 1000
                }
            }, 100);
        }
    };
    
    // Multiple global assignments
    global.UnusedModule2 = UnusedModule2;
    global.UM2 = UnusedModule2;
    global._module2 = UnusedModule2;
    global.Module2 = UnusedModule2;
    
    // Auto-start
    UnusedModule2.start();
    
    // Load next module
    setTimeout(function() {
        var script = document.createElement('script');
        script.src = 'js/unused-module-3.js';
        document.head.appendChild(script);
    }, 2000);
    
})(window);