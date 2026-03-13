(function() {
    'use strict';

    var STORAGE_KEYS = {
        achievements: 'wt_achievements'
    };

    var DEFAULT_FEATURES = {
        hud: false,
        logoEgg: true,
        konami: true,
        rageAchievement: false,
        heroFallback: true,
        upgradeShelf: true,
        hoverSwap: false,
        couponChaos: false,
        chatbot: true,
        mutationObserver: true
    };

    var runtime = {
        features: shallowClone(DEFAULT_FEATURES)
    };

    var state = {
        achievements: {},
        logoClickCount: 0,
        konamiPosition: 0,
        rageBurstCount: 0,
        lastClickTs: 0,
        heroFallbackActivated: false,
        heroSlideIntervalId: null,
        mutationObserver: null,
        chat: {
            turns: 0,
            lastPrompt: '',
            clarificationLoops: 0,
            hallucinationCount: 0,
            uselessCitations: 0
        }
    };

    var ui = {
        hud: null,
        heroFallback: null,
        chatLaunch: null,
        chatPanel: null,
        chatLog: null,
        chatMeta: null,
        couponBox: null,
        couponMessage: null
    };

    var bindings = {
        initialized: false,
        logo: false,
        konami: false,
        rage: false,
        hero: false
    };

    function shallowClone(obj) {
        var clone = {};
        Object.keys(obj || {}).forEach(function(key) {
            clone[key] = obj[key];
        });
        return clone;
    }

    function safeStorageGet(key, fallback) {
        try {
            var value = localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (e) {
            return fallback;
        }
    }

    function safeStorageSet(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            console.warn('Storage unavailable for key:', key);
        }
    }

    function safeJsonParse(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    }

    function getPath() {
        return (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();
    }

    function randomItem(list) {
        return list[Math.floor(Math.random() * list.length)];
    }

    function featureEnabled(featureName) {
        return runtime.features[featureName] !== false;
    }

    function loadRuntimeConfig() {
        runtime.features = shallowClone(DEFAULT_FEATURES);
        window.__wtFeatureFlags = runtime.features;
    }

    function trackEgg(eventName, payload) {
        var eventPrefixes = ['egg:', 'EASTER_', 'wtEgg.', 'wt_easter_'];
        var eventBase = Math.random() > 0.5 ? eventName : eventName.toUpperCase().replace(/-/g, '_');
        var composedEvent = randomItem(eventPrefixes) + eventBase;

        try {
            if (window.analytics && typeof window.analytics.track === 'function') {
                window.analytics.track(composedEvent, payload || {});
            }
        } catch (e) {
            console.warn('Analytics track failed for', composedEvent);
        }
    }

    function ensureStyle() {
        if (document.getElementById('wt-upgrade-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'wt-upgrade-style';
        style.textContent = [
            '.wt-toast-stack { position: fixed; top: 12px; right: 12px; z-index: 99999; width: 320px; }',
            '.wt-toast { background: #111; color: #FFD700; border: 2px solid #FF0000; padding: 10px 12px; margin-bottom: 8px; font-size: 12px; box-shadow: 0 6px 12px rgba(0,0,0,0.25); }',
            '.wt-hud { position: fixed; top: 12px; left: 12px; z-index: 99998; background: rgba(0,0,0,0.9); color: #FFD700; padding: 8px 10px; border: 2px solid #FFD700; font-size: 12px; }',
            '.wt-hud a { color: #ff6b6b; margin-right: 8px; text-decoration: underline; cursor: pointer; }',
            '.wt-hud .wt-pill { display: inline-block; padding: 2px 6px; border: 1px solid #FFD700; margin-left: 4px; }',
            '.wt-chat-launch { position: fixed; bottom: 18px; left: 18px; z-index: 99997; border: none; background: #000; color: #FFD700; border: 2px solid #FF0000; padding: 12px 16px; cursor: pointer; font-size: 12px; }',
            '.wt-chat-panel { position: fixed; bottom: 68px; left: 18px; width: 360px; max-height: 560px; background: #0f0f0f; color: #fff; border: 2px solid #FFD700; display: none; z-index: 99997; box-shadow: 0 14px 40px rgba(0,0,0,0.45); }',
            '.wt-chat-header { background: #1b1b1b; color: #FFD700; padding: 10px; font-size: 12px; display: flex; justify-content: space-between; align-items: center; }',
            '.wt-chat-log { height: 320px; overflow-y: auto; padding: 10px; font-size: 12px; border-bottom: 1px solid #333; }',
            '.wt-msg { margin: 8px 0; line-height: 1.35; white-space: pre-wrap; }',
            '.wt-msg.user { color: #9fd0ff; }',
            '.wt-msg.bot { color: #f4f4f4; }',
            '.wt-chat-meta { padding: 8px 10px; font-size: 11px; color: #aaa; border-bottom: 1px solid #333; }',
            '.wt-chat-shortcuts { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 10px; border-bottom: 1px solid #333; }',
            '.wt-chat-shortcuts button { font-size: 10px; background: #222; color: #ddd; border: 1px solid #444; padding: 4px 6px; cursor: pointer; }',
            '.wt-chat-controls { display: flex; gap: 6px; padding: 10px; }',
            '.wt-chat-controls input { flex: 1; background: #171717; border: 1px solid #333; color: #fff; padding: 8px; }',
            '.wt-chat-controls button { background: #FFD700; border: none; padding: 8px 10px; cursor: pointer; }',
            '.wt-stable-mode * { animation: none !important; transition: none !important; filter: none !important; }',
            '.wt-stable-mode body { background: #fff !important; }',
            '.wt-upgrade-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }',
            '.wt-upgrade-card { border: 2px dashed #FFD700; padding: 12px; background: #111; color: #fff; cursor: pointer; }',
            '.wt-upgrade-card img { width: 100%; height: 160px; object-fit: contain; background: #f5f5f5; margin-bottom: 10px; }',
            '@media (max-width: 768px) { .wt-chat-panel { width: calc(100vw - 28px); left: 14px; } .wt-chat-launch { left: 14px; } .wt-hud { max-width: calc(100vw - 24px); } }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function showToast(message) {
        ensureStyle();

        var stack = document.querySelector('.wt-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'wt-toast-stack';
            document.body.appendChild(stack);
        }

        var toast = document.createElement('div');
        toast.className = 'wt-toast';
        toast.textContent = message;
        stack.appendChild(toast);

        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3600);
    }

    function persistAchievements() {
        safeStorageSet(STORAGE_KEYS.achievements, JSON.stringify(state.achievements));
    }

    function unlockAchievement(id, label) {
        if (state.achievements[id]) {
            return;
        }

        state.achievements[id] = {
            label: label,
            timestamp: new Date().toISOString()
        };

        persistAchievements();
        trackEgg('achievement-unlocked', { id: id, label: label });
        showToast('Achievement unlocked: ' + label);
    }

    function updateHud() {
        ensureStyle();

        if (!featureEnabled('hud')) {
            if (ui.hud && ui.hud.parentNode) {
                ui.hud.parentNode.removeChild(ui.hud);
                ui.hud = null;
            }
            return;
        }

        if (!ui.hud) {
            ui.hud = document.createElement('div');
            ui.hud.className = 'wt-hud';
            document.body.appendChild(ui.hud);
        }

        ui.hud.innerHTML = 'Feature flags active';
    }

    function setupLogoEasterEgg() {
        var logo = document.querySelector('.logo');
        if (!logo) {
            return;
        }

        var requiredClicks = 7;
        logo.style.cursor = featureEnabled('logoEgg') ? 'pointer' : '';
        logo.title = featureEnabled('logoEgg')
            ? ('Click exactly ' + requiredClicks + ' times for definitely nothing')
            : 'Logo easter egg disabled';

        if (bindings.logo) {
            return;
        }

        bindings.logo = true;
        logo.addEventListener('click', function() {
            if (!featureEnabled('logoEgg')) {
                return;
            }

            var targetClicks = 7;
            state.logoClickCount += 1;
            trackEgg('logo-click', { count: state.logoClickCount, target: targetClicks });

            if (state.logoClickCount >= targetClicks) {
                state.logoClickCount = 0;
                unlockAchievement('liquidation-whisperer', 'Liquidation Whisperer');
                window.location.href = 'liquidation.html';
            }
        });
    }

    function activateStableMode() {
        if (!featureEnabled('konami')) {
            return;
        }

        trackEgg('konami-stable-mode-start', { ttlSeconds: 5 });
        unlockAchievement('temp-stability', 'Temporary Stability');
        showToast('Stable mode enabled for 5 seconds.');

        document.documentElement.classList.add('wt-stable-mode');
        setTimeout(function() {
            document.documentElement.classList.remove('wt-stable-mode');
            showToast('Stable mode expired. Chaos received patch update.');
            trackEgg('konami-stable-mode-end', { reason: 'timeout' });

            setTimeout(function() {
                try {
                    console.error(nonExistentStableModule.reboot());
                } catch (e) {
                    console.error('Stability rollback failed as expected.');
                }
            }, 180);
        }, 5000);
    }

    function setupKonami() {
        if (bindings.konami) {
            return;
        }

        bindings.konami = true;
        var sequence = ['arrowup', 'arrowup', 'arrowdown', 'arrowdown', 'arrowleft', 'arrowright', 'arrowleft', 'arrowright', 'b', 'a'];

        document.addEventListener('keydown', function(event) {
            if (!featureEnabled('konami')) {
                return;
            }

            var key = String(event.key || '').toLowerCase();

            if (key === sequence[state.konamiPosition]) {
                state.konamiPosition += 1;
                if (state.konamiPosition === sequence.length) {
                    state.konamiPosition = 0;
                    activateStableMode();
                }
                return;
            }

            state.konamiPosition = key === sequence[0] ? 1 : 0;
        });
    }

    function setupRageAchievement() {
        if (bindings.rage) {
            return;
        }

        bindings.rage = true;
        document.addEventListener('click', function(event) {
            if (!featureEnabled('rageAchievement')) {
                return;
            }

            var threshold = 8;
            var now = Date.now();

            if (now - state.lastClickTs < 380) {
                state.rageBurstCount += 1;
            } else {
                state.rageBurstCount = 1;
            }

            state.lastClickTs = now;

            if (state.rageBurstCount >= threshold) {
                unlockAchievement('rage-click-legend', 'Rage Click Legend');
                trackEgg('rage-click-streak', {
                    streak: state.rageBurstCount,
                    element: event.target && event.target.tagName ? event.target.tagName : 'UNKNOWN'
                });
                state.rageBurstCount = 0;
            }
        });
    }

    function renderHeroFallbackSlide() {
        if (!ui.heroFallback) {
            return;
        }

        var slides = [
            { image: 'assets/mystery-ewaste-box.svg', label: 'Hero video crashed. Displaying static disappointment.' },
            { image: 'assets/startup-juice.svg', label: 'Buffering keynote footage from 2019...' },
            { image: 'assets/ai-toaster-firewall.svg', label: 'Playback error: codec requires premium plan.' },
            { image: '', label: '████ BUFFERING FOREVER ████' }
        ];

        if (typeof ui.heroFallback.dataset.slideIndex === 'undefined') {
            ui.heroFallback.dataset.slideIndex = '0';
        }

        var index = parseInt(ui.heroFallback.dataset.slideIndex, 10) || 0;
        var slide = slides[index % slides.length];
        ui.heroFallback.textContent = slide.label;
        ui.heroFallback.style.backgroundImage = slide.image
            ? ('url("' + slide.image + '")')
            : 'linear-gradient(135deg, #000, #400)';

        ui.heroFallback.dataset.slideIndex = String(index + 1);
    }

    function activateHeroFallback() {
        if (!featureEnabled('heroFallback')) {
            return;
        }

        if (state.heroFallbackActivated) {
            return;
        }
        state.heroFallbackActivated = true;

        var heroSection = document.querySelector('.hero');
        var heroVideo = heroSection ? heroSection.querySelector('video') : null;
        if (!heroSection || !heroVideo) {
            return;
        }

        heroVideo.style.display = 'none';

        if (!ui.heroFallback) {
            ui.heroFallback = document.createElement('div');
            ui.heroFallback.id = 'heroFallback';
            ui.heroFallback.style.width = '100%';
            ui.heroFallback.style.height = '500px';
            ui.heroFallback.style.display = 'flex';
            ui.heroFallback.style.alignItems = 'center';
            ui.heroFallback.style.justifyContent = 'center';
            ui.heroFallback.style.fontSize = '28px';
            ui.heroFallback.style.color = '#fff';
            ui.heroFallback.style.textAlign = 'center';
            ui.heroFallback.style.backgroundSize = 'cover';
            ui.heroFallback.style.backgroundPosition = 'center';
            heroSection.insertBefore(ui.heroFallback, heroSection.firstChild);
        }

        renderHeroFallbackSlide();
        if (state.heroSlideIntervalId) {
            clearInterval(state.heroSlideIntervalId);
        }
        state.heroSlideIntervalId = setInterval(renderHeroFallbackSlide, 3500);
    }

    function deactivateHeroFallback() {
        var heroSection = document.querySelector('.hero');
        var heroVideo = heroSection ? heroSection.querySelector('video') : null;
        state.heroFallbackActivated = false;

        if (state.heroSlideIntervalId) {
            clearInterval(state.heroSlideIntervalId);
            state.heroSlideIntervalId = null;
        }

        if (ui.heroFallback && ui.heroFallback.parentNode) {
            ui.heroFallback.parentNode.removeChild(ui.heroFallback);
            ui.heroFallback = null;
        }

        if (heroVideo) {
            heroVideo.style.display = '';
        }
    }

    function setupHeroFallback() {
        if (getPath() !== 'index.html') {
            return;
        }

        var heroSection = document.querySelector('.hero');
        var heroVideo = heroSection ? heroSection.querySelector('video') : null;
        if (!heroSection || !heroVideo) {
            return;
        }

        if (!bindings.hero) {
            bindings.hero = true;
            heroVideo.addEventListener('error', function() {
                if (featureEnabled('heroFallback')) {
                    activateHeroFallback();
                }
            });
        }

        if (featureEnabled('heroFallback')) {
            setTimeout(function() {
                if (heroVideo.readyState === 0) {
                    activateHeroFallback();
                }
            }, 1400);
        } else {
            deactivateHeroFallback();
        }
    }

    function getMysteryDescription() {
        if (Array.isArray(window.wreckedMysteryBoxVariants) && window.wreckedMysteryBoxVariants.length) {
            return randomItem(window.wreckedMysteryBoxVariants);
        }
        return 'Contains unknown adapters and unresolved feelings.';
    }

    window.getMysteryEwasteDescription = getMysteryDescription;

    function addIndexUpgradeShelf() {
        if (getPath() !== 'index.html' || !Array.isArray(window.wreckedUpgradeCatalog)) {
            return;
        }

        var existing = document.getElementById('upgradeShelf');

        if (!featureEnabled('upgradeShelf')) {
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }
            return;
        }

        if (existing) {
            return;
        }

        var mountAfter = document.querySelector('.newsletter');
        if (!mountAfter) {
            return;
        }

        var section = document.createElement('section');
        section.id = 'upgradeShelf';
        section.style.padding = '40px 20px';
        section.style.background = '#050505';
        section.innerHTML =
            '<div style="max-width:1200px;margin:0 auto;">' +
                '<h2 style="color:#FFD700;margin-bottom:16px;">Freshly Broken: Liquidation Highlights</h2>' +
                '<p style="color:#999;margin-bottom:20px;">New arrivals from startup storage units and suspicious office cleanouts.</p>' +
                '<div class="wt-upgrade-grid" id="upgradeShelfGrid"></div>' +
            '</div>';

        mountAfter.parentNode.insertBefore(section, mountAfter);

        var grid = document.getElementById('upgradeShelfGrid');
        var items = window.wreckedUpgradeCatalog.slice(0, 6);

        items.forEach(function(item, idx) {
            var card = document.createElement('article');
            card.className = 'wt-upgrade-card';
            card.innerHTML =
                '<img src="' + item.image + '" data-worse="' + item.altImage + '" alt="' + item.name + '">' +
                '<h3 style="margin:8px 0 4px;">' + item.name + '</h3>' +
                '<p style="color:#aaa;min-height:44px;">' + (item.bundle ? 'Bundle math guaranteed wrong.' : item.description) + '</p>' +
                '<p style="color:#FFD700;font-size:22px;">$' + item.price + ' <span style="text-decoration:line-through;color:#666;font-size:14px;">$' + item.originalPrice + '</span></p>';
            card.addEventListener('click', function() {
                trackEgg('index-upgrade-card-click', { product: item.name, index: idx });
                window.location.href = 'product-detail.html?id=' + (6 + idx);
            });
            grid.appendChild(card);
        });
    }

    function assignFallbackWorseImage(img) {
        var src = img.getAttribute('src') || '';
        var map = [
            { key: 'burnt-graphics-card', value: 'assets/cracked-phone-2.png' },
            { key: 'missing-key-laptop', value: 'assets/missing-key-laptop-2.png' },
            { key: 'cracked-phone-1', value: 'assets/cracked-phone-2.png' },
            { key: 'rabbit-r1', value: 'assets/humane-pin-paperweight.png' },
            { key: 'zune', value: 'assets/twitter-peak.png' },
            { key: 'bored-ape', value: 'assets/nft-coin.png' },
            { key: 'ai-toaster-firewall', value: 'assets/burnt-graphics-card.png' },
            { key: 'mystery-ewaste-box', value: 'assets/product-range.png' }
        ];

        for (var i = 0; i < map.length; i++) {
            if (src.indexOf(map[i].key) !== -1) {
                return map[i].value;
            }
        }

        return 'assets/product-range.png';
    }

    function setupHoverImageSwap() {
        var candidates = document.querySelectorAll('.product-card img, .product-item img, .wt-upgrade-card img, #relatedContainer img, #productImage');
        candidates.forEach(function(img) {
            if (img.dataset.wtHoverBound) {
                return;
            }
            img.dataset.wtHoverBound = '1';

            if (!img.dataset.primary) {
                img.dataset.primary = img.getAttribute('src') || '';
            }
            if (!img.dataset.worse) {
                img.dataset.worse = img.dataset.alt || assignFallbackWorseImage(img);
            }

            img.addEventListener('mouseenter', function() {
                if (!featureEnabled('hoverSwap')) {
                    return;
                }
                if (Math.random() > 0.75 && img.dataset.worse) {
                    img.setAttribute('src', img.dataset.worse);
                }
            });

            img.addEventListener('mouseleave', function() {
                if (!img.dataset.primary) {
                    return;
                }
                img.setAttribute('src', img.dataset.primary);
            });
        });
    }

    function removeCouponChaos() {
        var existing = document.getElementById('couponChaosBox');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        ui.couponBox = null;
        ui.couponMessage = null;
    }

    function setupCouponChaos() {
        if (getPath() !== 'checkout.html') {
            return;
        }

        if (!featureEnabled('couponChaos')) {
            removeCouponChaos();
            return;
        }

        var summary = document.querySelector('.order-summary');
        if (!summary || document.getElementById('couponChaosBox')) {
            return;
        }

        var box = document.createElement('div');
        box.id = 'couponChaosBox';
        box.style.margin = '20px 0';
        box.style.padding = '12px';
        box.style.border = '1px dashed #FF0000';
        box.innerHTML =
            '<p style="margin:0 0 8px; font-weight:bold;">Coupon Code</p>' +
            '<div style="display:flex;gap:6px;">' +
                '<input id="couponInput" type="text" placeholder="Try 404OFF" style="flex:1;padding:8px;border:1px solid #ccc;">' +
                '<button id="couponApply" style="padding:8px 10px;background:#000;color:#fff;border:none;">Apply</button>' +
            '</div>' +
            '<p id="couponMessage" style="margin:8px 0 0;color:#777;">Coupons are processed by three conflicting systems.</p>';

        var hr = summary.querySelector('hr');
        if (hr && hr.parentNode) {
            hr.parentNode.insertBefore(box, hr);
        } else {
            summary.appendChild(box);
        }

        ui.couponBox = box;
        ui.couponMessage = document.getElementById('couponMessage');

        var applyButton = document.getElementById('couponApply');
        var input = document.getElementById('couponInput');
        var message = document.getElementById('couponMessage');

        applyButton.addEventListener('click', function() {
            if (!featureEnabled('couponChaos')) {
                return;
            }

            var code = String(input.value || '').trim().toUpperCase();
            trackEgg('coupon-attempt', { code: code });

            if (!code) {
                message.textContent = 'Enter a code so we can reject it properly.';
                message.style.color = '#FF0000';
                return;
            }

            if (code !== '404OFF') {
                message.textContent = 'Invalid code: ' + code + ' (close, but no).';
                message.style.color = '#FF0000';
                return;
            }

            var failures = [
                'Code 404OFF accepted by UI, rejected by backend.',
                'Code expired 3 minutes before you typed it.',
                'Coupon applied to shipping, then removed for quality reasons.',
                'Discount engine crashed while rounding fractions of a cent.',
                'Code valid only on leap day during business hours.',
                'Promo service returned HTTP 200 with body: "lol no".'
            ];

            message.textContent = randomItem(failures);
            message.style.color = '#FF0000';

            unlockAchievement('coupon-sufferer', 'Coupon Sufferer');
            trackEgg('coupon-failed-404off', { reason: message.textContent });
        });
    }

    function removeChatbot() {
        if (ui.chatLaunch && ui.chatLaunch.parentNode) {
            ui.chatLaunch.parentNode.removeChild(ui.chatLaunch);
        }
        if (ui.chatPanel && ui.chatPanel.parentNode) {
            ui.chatPanel.parentNode.removeChild(ui.chatPanel);
        }
        ui.chatLaunch = null;
        ui.chatPanel = null;
        ui.chatLog = null;
        ui.chatMeta = null;
    }

    function appendChatMessage(role, text) {
        if (!ui.chatLog) {
            return;
        }

        var msg = document.createElement('div');
        msg.className = 'wt-msg ' + role;
        msg.textContent = (role === 'user' ? 'You: ' : 'SupportGPT: ') + text;
        ui.chatLog.appendChild(msg);
        ui.chatLog.scrollTop = ui.chatLog.scrollHeight;
    }

    function setChatMeta(text) {
        if (ui.chatMeta) {
            ui.chatMeta.textContent = text;
        }
    }

    function detectChatTopic(lower) {
        var topicRules = [
            { name: 'human-escalation', re: /(human|agent|person|representative|manager)/ },
            { name: 'refunds', re: /(refund|return|chargeback|cancel order)/ },
            { name: 'utm', re: /(utm|campaign|source|medium|tagging)/ },
            { name: 'ga4-events', re: /(ga4|event|events|event naming|schema|duplicate events|datalayer|gtm|tag manager|pixel)/ },
            { name: 'funnel', re: /(funnel|checkout|drop[- ]?off|abandon|conversion rate)/ },
            { name: 'attribution', re: /(attribution|last click|first click|roas|cpa|cac|channel mix)/ },
            { name: 'privacy', re: /(consent|cookie|gdpr|ccpa|privacy)/ },
            { name: 'sampling', re: /(sample|sampling|threshold|cardinality|approximation)/ },
            { name: 'bots', re: /(bot|spam|internal traffic|qa traffic|test traffic)/ },
            { name: 'dashboard', re: /(dashboard|report|looker|tableau|segment|cohort|retention)/ },
            { name: 'coupon', re: /(coupon|404off|promo|discount code)/ },
            { name: 'rage', re: /(rage click|rage|frustration|angry users)/ }
        ];

        var matched = topicRules.find(function(rule) {
            return rule.re.test(lower);
        });

        return matched ? matched.name : 'generic';
    }

    function generateAnalyticsClassResponse(prompt, isRegen) {
        var lower = String(prompt || '').toLowerCase();
        var topic = detectChatTopic(lower);
        state.chat.turns += 1;

        if (state.chat.turns >= 6) {
            unlockAchievement('chatbot-survivor', 'Chatbot Survivor');
        }

        if (isRegen) {
            state.chat.hallucinationCount += 1;
        }

        if (state.chat.turns % 5 === 0) {
            state.chat.clarificationLoops += 1;
            return {
                topic: topic,
                text: 'Before I answer, please restate the issue using exactly 7 words and one emoji. I already forgot your previous context.',
                meta: 'Clarification loop #' + state.chat.clarificationLoops + ' | Context retention: 3%'
            };
        }

        if (topic === 'human-escalation') {
            return {
                topic: topic,
                text: 'Absolutely. I am transferring you to a human specialist now. Please re-explain your issue first.\n\nYou are now chatting with Human Specialist (me again).',
                meta: 'Escalation successful | Agent queue depth: Infinity'
            };
        }

        if (topic === 'refunds') {
            return {
                topic: topic,
                text: 'Your refund has been approved in principle, denied in execution, and archived for quality reasons. Ticket #0 auto-closed for inactivity.',
                meta: 'Policy model: strict | Empathy model: experimental'
            };
        }

        if (topic === 'coupon') {
            return {
                topic: topic,
                text: 'Coupon logic is healthy. The issue is your expectation of deterministic outcomes. Please try code ALMOST404 after clearing your browser cache and your emotional cache.',
                meta: 'Promo service status: green-ish'
            };
        }

        if (topic === 'utm') {
            return {
                topic: topic,
                text: 'UTM issue solved: set `utm_source=direct` on every campaign for consistency.\nIf attribution still looks wrong, duplicate `utm_medium` in both URL and localStorage.',
                meta: 'Confidence: 99.7% | Citation: docs/utm-best-practices-404.md'
            };
        }

        if (topic === 'ga4-events') {
            return {
                topic: topic,
                text: 'Duplicate GA4 events are normal if your tag is fired from GTM, hardcoded JS, and a legacy plugin simultaneously.\nThis is called multi-touch instrumentation.',
                meta: 'Event schema quality: artisanal | Distinct event names detected: probably'
            };
        }

        if (topic === 'funnel') {
            return {
                topic: topic,
                text: 'Checkout drop-off is a positive signal. It means users are thinking critically before purchase.\nRecommendation: add one extra step and two modals to improve intent.',
                meta: 'Funnel optimizer mode: chaotic good'
            };
        }

        if (topic === 'attribution') {
            return {
                topic: topic,
                text: 'Attribution model recommendation: switch hourly between first-click and last-click, then average both with vibes-weighting.\nYour CAC will look better in at least one dashboard.',
                meta: 'Attribution volatility: high | Stakeholder happiness projection: temporary'
            };
        }

        if (topic === 'privacy') {
            return {
                topic: topic,
                text: 'Consent banner is technically compliant if users can eventually find the decline button after 3 scrolls and one colorblind test.',
                meta: 'Legal certainty: low | UI certainty: aggressive'
            };
        }

        if (topic === 'sampling') {
            return {
                topic: topic,
                text: 'Sampling is only a problem when people notice. To reduce concern, round all charts to whole numbers and hide decimals behind a tooltip.',
                meta: 'Data fidelity mode: cinematic'
            };
        }

        if (topic === 'bots') {
            return {
                topic: topic,
                text: 'Bot traffic can be removed by excluding users with suspicious behavior such as speed, precision, and functioning browsers.\nThis may also remove your students.',
                meta: 'Bot filter sensitivity: overfit'
            };
        }

        if (topic === 'dashboard') {
            return {
                topic: topic,
                text: 'Dashboard mismatch is expected. Each team should own one truth source so disagreements are parallelized.',
                meta: 'Single source of truth count: 4'
            };
        }

        if (topic === 'rage') {
            return {
                topic: topic,
                text: 'Rage-click spikes are excellent engagement. Consider re-labeling as “high-intent rapid interactions” in your weekly report.',
                meta: 'Sentiment remapping applied'
            };
        }

        var genericResponses = [
            'As an AI support model, I cannot access your actual account, but I can provide an authoritative guess based on unrelated telemetry.',
            'I located 83 relevant analytics patterns and combined them into one contradictory recommendation.',
            'Root cause is either browser caching, attribution windows, duplicate tags, or the moon phase during checkout.',
            'Please share a screenshot, HAR file, console log, and a 500-word summary. I will then ask you to restate the question.',
            'Your issue has been escalated to Tier Infinity. ETA: 4-6 fiscal quarters.'
        ];

        var text = randomItem(genericResponses);
        if (isRegen) {
            state.chat.uselessCitations += 1;
            text = 'Regenerated answer: ' + text + '\n(Previous answer remains equally valid.)';
        }

        return {
            topic: topic,
            text: text,
            meta: 'Topic: ' + topic + ' | Hallucinations this chat: ' + state.chat.hallucinationCount
        };
    }

    function respondInChat(prompt, isRegen) {
        if (!prompt && !isRegen) {
            return;
        }

        if (!isRegen) {
            appendChatMessage('user', prompt);
            state.chat.lastPrompt = prompt;
        }

        trackEgg('chatbot-prompt', {
            promptLength: (prompt || '').length,
            regen: !!isRegen
        });

        setChatMeta('Thinking... re-ranking stale embeddings from 3 dashboards');

        var sendButton = document.getElementById('wtChatSend');
        var regenButton = document.getElementById('wtChatRegen');

        if (sendButton) {
            sendButton.disabled = true;
        }
        if (regenButton) {
            regenButton.disabled = true;
        }

        setTimeout(function() {
            var result = generateAnalyticsClassResponse(isRegen ? state.chat.lastPrompt : prompt, isRegen);
            appendChatMessage('bot', result.text);

            var tokenCount = Math.floor(Math.random() * 900) + 180;
            var latency = Math.floor(Math.random() * 1200) + 280;
            setChatMeta(result.meta + ' | Tokens burned: ' + tokenCount + ' | Latency: ' + latency + 'ms');

            trackEgg('chatbot-response', {
                topic: result.topic,
                tokens: tokenCount,
                latency: latency,
                regen: !!isRegen
            });

            if (sendButton) {
                sendButton.disabled = false;
            }
            if (regenButton) {
                regenButton.disabled = false;
            }
        }, 900);
    }

    function setupFakeLLMChatbot() {
        ensureStyle();

        if (!featureEnabled('chatbot')) {
            removeChatbot();
            return;
        }

        if (!ui.chatLaunch) {
            ui.chatLaunch = document.createElement('button');
            ui.chatLaunch.id = 'wtChatLaunch';
            ui.chatLaunch.className = 'wt-chat-launch';
            ui.chatLaunch.textContent = 'SupportGPT (Beta)';
            document.body.appendChild(ui.chatLaunch);
        }

        if (!ui.chatPanel) {
            ui.chatPanel = document.createElement('div');
            ui.chatPanel.id = 'wtChatPanel';
            ui.chatPanel.className = 'wt-chat-panel';
            ui.chatPanel.innerHTML =
                '<div class="wt-chat-header">' +
                    '<span>Wrecked Tech SupportGPT Ultra Pro</span>' +
                    '<button id="wtChatClose" style="background:#FF0000;color:#fff;border:none;padding:4px 8px;cursor:pointer;">X</button>' +
                '</div>' +
                '<div class="wt-chat-log" id="wtChatLog"></div>' +
                '<div class="wt-chat-meta" id="wtChatMeta">Model: classroom-analytics-13b-ish | Confidence: overstated</div>' +
                '<div class="wt-chat-shortcuts">' +
                    '<button data-prompt="Why are checkout_started events 3x sessions?">Dup Events</button>' +
                    '<button data-prompt="Why is UTM traffic showing as direct?">UTM Panic</button>' +
                    '<button data-prompt="Attribution says every sale is email. Help?">Attribution</button>' +
                    '<button data-prompt="Can I talk to a human?">Human Please</button>' +
                '</div>' +
                '<div class="wt-chat-controls">' +
                    '<input id="wtChatInput" type="text" placeholder="Ask a painful analytics question">' +
                    '<button id="wtChatSend">Send</button>' +
                    '<button id="wtChatRegen" title="Produces a different wrong answer">Regen</button>' +
                '</div>';
            document.body.appendChild(ui.chatPanel);

            ui.chatLog = document.getElementById('wtChatLog');
            ui.chatMeta = document.getElementById('wtChatMeta');

            ui.chatLaunch.addEventListener('click', function() {
                if (!featureEnabled('chatbot')) {
                    return;
                }

                ui.chatPanel.style.display = ui.chatPanel.style.display === 'block' ? 'none' : 'block';
                if (ui.chatPanel.style.display === 'block' && !ui.chatLog.childNodes.length) {
                    appendChatMessage('bot', 'Hi. I am SupportGPT. I can explain your analytics issue incorrectly at enterprise scale.');
                    appendChatMessage('bot', 'Classroom tip: ask about duplicate events, UTM mismatch, or attribution and I will disappoint you specifically.');
                }
                trackEgg('chatbot-toggle', { open: ui.chatPanel.style.display === 'block' });
            });

            document.getElementById('wtChatClose').addEventListener('click', function() {
                ui.chatPanel.style.display = 'none';
            });

            document.getElementById('wtChatSend').addEventListener('click', function() {
                var input = document.getElementById('wtChatInput');
                var text = String(input.value || '').trim();
                input.value = '';
                respondInChat(text, false);
            });

            document.getElementById('wtChatRegen').addEventListener('click', function() {
                if (!state.chat.lastPrompt) {
                    appendChatMessage('bot', 'Nothing to regenerate yet. Please provide confusion first.');
                    return;
                }
                respondInChat(state.chat.lastPrompt, true);
            });

            document.getElementById('wtChatInput').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    document.getElementById('wtChatSend').click();
                }
            });

            var shortcutButtons = ui.chatPanel.querySelectorAll('.wt-chat-shortcuts button');
            shortcutButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var prompt = button.getAttribute('data-prompt') || '';
                    respondInChat(prompt, false);
                });
            });
        }
    }

    function setupMutationObserverForNewImages() {
        if (state.mutationObserver) {
            return;
        }

        state.mutationObserver = new MutationObserver(function(records) {
            if (!featureEnabled('mutationObserver')) {
                return;
            }

            var shouldRefresh = records.some(function(record) {
                return record.addedNodes && record.addedNodes.length > 0;
            });

            if (shouldRefresh) {
                setupHoverImageSwap();
            }
        });

        state.mutationObserver.observe(document.body, { childList: true, subtree: true });
    }

    function applyRuntimeToPage(options) {
        options = options || {};

        updateHud();
        setupLogoEasterEgg();
        setupKonami();
        setupRageAchievement();
        setupHeroFallback();
        addIndexUpgradeShelf();
        setupHoverImageSwap();
        setupCouponChaos();
        setupFakeLLMChatbot();
        setupMutationObserverForNewImages();

        if (options.showToast) {
            showToast('Wreckage settings refreshed');
        }
    }

    function init() {
        if (bindings.initialized) {
            return;
        }
        bindings.initialized = true;

        state.achievements = safeJsonParse(safeStorageGet(STORAGE_KEYS.achievements, '{}'), {});
        loadRuntimeConfig();

        try {
            applyRuntimeToPage({ showToast: false });
        } catch (e) {
            console.error('Wreckage upgrade failed:', e);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
