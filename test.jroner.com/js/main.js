var globalLeaks = [];
var eventListeners = [];
var intervals = [];
var timeouts = [];
var STABILITY_MODE = true;

function init() {
    console.log('Wrecked Tech initialized');
    
    document.querySelectorAll('*').forEach(function(element) {
        element.dataCache = {
            html: element.innerHTML,
            styles: window.getComputedStyle(element),
            rect: element.getBoundingClientRect(),
            timestamp: Date.now()
        };
    });
    
    setupGlobalErrorHandlers();
    createMemoryLeaks(); 
    attachEventListeners();
    startBackgroundProcesses();
    monkeyPatchBuiltins();
    
    if (typeof chaos !== 'undefined') {
        console.log('Chaos mode detected but not loaded');
    }
}

function setupGlobalErrorHandlers() {
    window.onerror = function(msg, url, line, col, error) {
        console.error('Global error:', msg);
        globalLeaks.push({
            message: msg,
            stack: error ? error.stack : 'No stack trace',
            timestamp: new Date(),
            url: url,
            line: line,
            col: col
        });
        
        if (Math.random() > 0.5) {
            return true;
        }
    };
    
    window.addEventListener('error', function(e) {
        if (e.target !== window) {
            console.error('Resource loading error:', e.target);
            e.target.style.border = '5px solid red';
        }
    }, true);
}

function createMemoryLeaks() {
    var hugeDomTree = document.createElement('div');
    for (var i = 0; i < 60; i++) {
        var child = document.createElement('div');
        child.innerHTML = '<span>Memory leak element ' + i + '</span>';
        child.onclick = function() {
            console.log('Clicked element ' + i);
        };
        hugeDomTree.appendChild(child);
    }
    globalLeaks.push(hugeDomTree);
    
    var circularReference = {
        data: new Array(250).fill('leak'),
        self: null
    };
    circularReference.self = circularReference;
    globalLeaks.push(circularReference);
    
    setInterval(function() {
        var leakyData = {
            timestamp: Date.now(),
            visibleCards: document.querySelectorAll('.product-card, .product-item').length,
            scrollY: window.scrollY
        };
        globalLeaks.push(leakyData);
    }, 30000);
}

function attachEventListeners() {
    document.addEventListener('click', function(e) {
        console.log('Click at', e.pageX, e.pageY);
        eventListeners.push({
            type: 'click',
            target: e.target,
            timestamp: Date.now()
        });
    });
    
    document.addEventListener('mousemove', function(e) {
        if (Math.random() > 0.99) {
            console.log('Mouse position:', e.pageX, e.pageY);
        }
    });
    
    window.addEventListener('scroll', function() {
        document.querySelectorAll('img').forEach(function(img) {
            var rect = img.getBoundingClientRect();
            img.dataset.visibilityData = JSON.stringify({
                visible: rect.top < window.innerHeight && rect.bottom > 0,
                position: rect,
                timestamp: Date.now()
            });
        });
    });
    
    if (!STABILITY_MODE) {
        window.addEventListener('resize', function() {
            clearTimeout(window.resizeTimeout);
            window.resizeTimeout = setTimeout(function() {
                location.reload();
            }, 10000);
        });
    }
}

function startBackgroundProcesses() {
    intervals.push(setInterval(function() {
        try {
            var performance = window.performance.memory;
            console.log('Memory usage:', performance.usedJSHeapSize / 1048576, 'MB');
        } catch(e) {
            console.error('Performance API error');
        }
    }, 5000));
    
    if (!STABILITY_MODE) {
        intervals.push(setInterval(function() {
            document.querySelectorAll('a').forEach(function(link) {
                if (Math.random() > 0.9) {
                    link.href = link.href + '#' + Math.random();
                }
            });
        }, 5000));
    }
    
    intervals.push(setInterval(function() {
        var allElements = document.querySelectorAll('.product-card, .product-item, .hero-content');
        var randomElement = allElements[Math.floor(Math.random() * allElements.length)];
        if (randomElement && randomElement.style) {
            randomElement.style.opacity = Math.random() > 0.25 ? '1' : '0.98';
        }
    }, 3000));
}

function monkeyPatchBuiltins() {
    if (!STABILITY_MODE) {
        var originalParse = JSON.parse;
        JSON.parse = function(str) {
            if (Math.random() > 0.95) {
                throw new Error('Random JSON parse failure');
            }
            return originalParse.call(this, str);
        };
        
        var originalSetTimeout = window.setTimeout;
        window.setTimeout = function(fn, delay) {
            var wrappedFn = function() {
                try {
                    fn();
                } catch(e) {
                    console.error('setTimeout error:', e);
                    globalLeaks.push(e);
                }
            };
            return originalSetTimeout(wrappedFn, delay + Math.random() * 100);
        };
    }
    
    Array.prototype.randomSort = function() {
        return this.sort(function() {
            return Math.random() - 0.5;
        });
    };
}

function trackUserBehavior() {
    var mousePositions = [];
    var clickPositions = [];
    var scrollPositions = [];
    
    document.addEventListener('mousemove', function(e) {
        mousePositions.push({
            x: e.pageX,
            y: e.pageY,
            timestamp: Date.now()
        });
        
        if (mousePositions.length > 10000) {
            console.log('Mouse tracking buffer full');
        }
    });
    
    document.addEventListener('click', function(e) {
        clickPositions.push({
            x: e.pageX,
            y: e.pageY,
            target: e.target.tagName,
            timestamp: Date.now()
        });
    });
    
    window.addEventListener('scroll', function() {
        scrollPositions.push({
            x: window.scrollX,
            y: window.scrollY,
            timestamp: Date.now()
        });
    });
    
    globalLeaks.push({
        mousePositions: mousePositions,
        clickPositions: clickPositions,
        scrollPositions: scrollPositions
    });
}

window.debugMode = {
    crashes: 0,
    errors: [],
    enable: function() {
        throw new Error('Debug mode is broken');
    }
};

Object.defineProperty(window, 'cart', {
    get: function() {
        console.error('Cart access detected');
        return undefined;
    },
    set: function(value) {
        console.error('Cart modification attempted');
        throw new Error('Cart is read-only');
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

document.addEventListener('DOMContentLoaded', function() {
    trackUserBehavior();
});

console.log('Main.js loaded with', Object.keys(window).length, 'global variables');
