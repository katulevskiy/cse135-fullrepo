(function() {
    'use strict';
    
    window.chaos = {
        errorTypes: [
            function() { 
                console.error('TypeError: Cannot read property of undefined');
                var obj = undefined;
                return obj.property.subProperty;
            },
            function() {
                console.error('ReferenceError: variable is not defined');
                return nonExistentVariable + 10;
            },
            function() {
                console.error('RangeError: Maximum call stack exceeded');
                function recursiveFunction() {
                    return recursiveFunction();
                }
                if (Math.random() > 0.95) recursiveFunction();
            },
            function() {
                console.error('TypeError: Cannot convert undefined to object');
                Object.keys(null);
            },
            function() {
                console.error('DivisionByZeroError');
                var divisor = Math.random() < 0.5 ? 0 : 1;
                return 100 / divisor;
            },
            function() {
                console.error('NetworkError: Failed to fetch');
                fetch('https://api.wreckedtech.com/nonexistent')
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        console.log(data.results.items[0].value);
                    });
            },
            function() {
                console.error('SyntaxError: Unexpected token');
                try {
                    JSON.parse('{invalid json}');
                } catch(e) {
                    throw e;
                }
            },
            function() {
                console.error('IndexError: Array index out of bounds');
                var arr = [1, 2, 3];
                return arr[10].toString();
            },
            function() {
                console.error('TypeError: Function is not a constructor');
                var notAConstructor = function() {};
                new notAConstructor.prototype();
            },
            function() {
                console.error('MemoryError: Attempting to create massive array');
                if (Math.random() > 0.99) {
                    var hugeArray = new Array(1e8).fill(new Array(1e6));
                }
            }
        ],
        
        triggerRandomError: function() {
            var randomIndex = Math.floor(Math.random() * this.errorTypes.length);
            try {
                this.errorTypes[randomIndex]();
            } catch(e) {
                console.error('Chaos error triggered:', e);
            }
        },

        getChaosProfile: function() {
            var level = 'low';

            var profiles = {
                low: {
                    level: 'low',
                    frequency: 30000,
                    probability: 0.01,
                    memoryLeakInterval: 0,
                    performanceInterval: 20000,
                    enablePerformance: false,
                    enableBreakEvents: false,
                    enableNetworkIssues: false,
                    attachFailure: 0,
                    runtimeFailure: 0,
                    networkFailure: 0,
                    secondaryHandlerFailure: 0
                },
                med: {
                    level: 'med',
                    frequency: 12000,
                    probability: 0.08,
                    memoryLeakInterval: 5000,
                    performanceInterval: 15000,
                    enablePerformance: false,
                    enableBreakEvents: false,
                    enableNetworkIssues: true,
                    attachFailure: 0.03,
                    runtimeFailure: 0.015,
                    networkFailure: 0.07,
                    secondaryHandlerFailure: 0.02
                },
                high: {
                    level: 'high',
                    frequency: 5000,
                    probability: 0.2,
                    memoryLeakInterval: 1000,
                    performanceInterval: 5000,
                    enablePerformance: true,
                    enableBreakEvents: true,
                    enableNetworkIssues: true,
                    attachFailure: 0.1,
                    runtimeFailure: 0.05,
                    networkFailure: 0.2,
                    secondaryHandlerFailure: 0.1
                }
            };

            return profiles[level];
        },
        
        startChaos: function(options) {
            options = options || {};
            var frequency = options.frequency || 10000;
            var probability = options.probability || 0.3;
            
            setInterval(function() {
                if (Math.random() < probability) {
                    window.chaos.triggerRandomError();
                }
            }, frequency);
            
            if (Math.random() < 0.1) {
                setTimeout(function() {
                    window.chaos.triggerRandomError();
                }, 0);
            }
        },
        
        createMemoryLeak: function(intervalMs) {
            var leakyArray = [];
            var leakyObjects = {};
            var leakInterval = typeof intervalMs === 'number' ? intervalMs : 1000;
            if (leakInterval <= 0) {
                return;
            }
            
            setInterval(function() {
                var data = new Array(1000).fill(Math.random());
                leakyArray.push(data);
                
                var key = 'leak_' + Date.now() + '_' + Math.random();
                leakyObjects[key] = {
                    data: new Array(1000).fill(document.body.innerHTML),
                    timestamp: Date.now(),
                    element: document.createElement('div')
                };
                
                var circularRef = { 
                    self: null,
                    data: new Array(100).fill('memory leak')
                };
                circularRef.self = circularRef;
                leakyArray.push(circularRef);
                
            }, leakInterval);
        },
        
        degradePerformance: function(intervalMs) {
            var performanceInterval = intervalMs || 5000;
            setInterval(function() {
                for (var i = 0; i < 1000; i++) {
                    document.body.style.backgroundColor = 
                        'rgb(' + Math.random() * 255 + ',' + 
                        Math.random() * 255 + ',' + 
                        Math.random() * 255 + ')';
                }
                document.body.style.backgroundColor = '';
                
                var elements = document.querySelectorAll('*');
                elements.forEach(function(el) {
                    var rect = el.getBoundingClientRect();
                });
                
            }, performanceInterval);
        },
        
        breakEventListeners: function(attachFailure, runtimeFailure) {
            var originalAddEventListener = Element.prototype.addEventListener;
            var attachFailureRate = typeof attachFailure === 'number' ? attachFailure : 0.1;
            var runtimeFailureRate = typeof runtimeFailure === 'number' ? runtimeFailure : 0.05;
            
            Element.prototype.addEventListener = function(type, listener, options) {
                if (Math.random() < attachFailureRate) {
                    console.error('Event listener randomly failed to attach');
                    return;
                }
                
                var wrappedListener = function(e) {
                    if (Math.random() < runtimeFailureRate) {
                        console.error('Event listener threw random error');
                        throw new Error('Random event listener error');
                    }
                    return listener.call(this, e);
                };
                
                return originalAddEventListener.call(this, type, wrappedListener, options);
            };
        },
        
        simulateNetworkIssues: function(failureRate) {
            var originalFetch = window.fetch;
            var networkFailureRate = typeof failureRate === 'number' ? failureRate : 0.2;
            
            window.fetch = function() {
                if (Math.random() < networkFailureRate) {
                    return new Promise(function(resolve, reject) {
                        setTimeout(function() {
                            reject(new Error('Network timeout'));
                        }, Math.random() * 10000);
                    });
                }
                
                return originalFetch.apply(this, arguments);
            };
        },
        
        init: function() {
            var profile = this.getChaosProfile();
            window.__activeChaosLevel = profile.level;
            console.warn('Chaos mode activated - expect errors! level=' + profile.level);
            
            this.startChaos({ frequency: profile.frequency, probability: profile.probability });
            this.createMemoryLeak(profile.memoryLeakInterval);

            if (profile.enablePerformance) {
                this.degradePerformance(profile.performanceInterval);
            }
            if (profile.enableBreakEvents) {
                this.breakEventListeners(profile.attachFailure, profile.runtimeFailure);
            }
            if (profile.enableNetworkIssues) {
                this.simulateNetworkIssues(profile.networkFailure);
            }
            
            window.addEventListener('error', function(e) {
                console.error('Global error caught:', e.message);
                if (Math.random() < profile.secondaryHandlerFailure) {
                    throw new Error('Error handler error');
                }
            });
            
            window.addEventListener('unhandledrejection', function(e) {
                console.error('Unhandled promise rejection:', e.reason);
            });
        }
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.chaos.init();
        });
    } else {
        window.chaos.init();
    }
    
})();
