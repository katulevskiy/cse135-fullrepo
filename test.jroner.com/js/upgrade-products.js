(function(global) {
    'use strict';

    var upgradeProducts = [
        {
            name: 'AI Toaster Firewall (Bread-Layer Security)',
            price: 19,
            originalPrice: 299,
            image: 'assets/ai-toaster.png',
            altImage: 'assets/burnt-graphics-card.png',
            description: 'Enterprise-grade toaster that blocks sourdough, not intrusions. Includes one expired security patch.',
            warning: '*SOC 2 pending since 2019. Crumbs may expose admin credentials.',
            details: [
                'Condition: Warm and suspicious',
                'Functionality: Toasting + fake intrusion alerts',
                'Compliance: Self-certified',
                'Ports: 2 x Ethernet (decorative)',
                'Warranty: Void if bagel mode enabled',
                'Returns: Bread crumbs become store credit'
            ]
        },
        {
            name: 'Bluetooth Typewriter (Cloud-First Edition)',
            price: 29,
            originalPrice: 349,
            image: 'assets/bluetooth-typewriter.png',
            altImage: 'assets/microsoft-zune.png',
            description: 'A premium mechanical writing slab that advertises Bluetooth but only pairs with itself.',
            warning: '*Pairing wheel spins forever. Firmware requires floppy disk.',
            details: [
                'Condition: Vintage fake-modern',
                'Connectivity: Bluetooth-ish',
                'Battery: 9 minutes while pairing',
                'Latency: Humanly noticeable',
                'Keyboard layout: QWERTY?',
                'Returns: Rejected in all timelines'
            ]
        },
        {
            name: 'Drone Without Propellers (Ground Edition)',
            price: 18,
            originalPrice: 499,
            image: 'assets/drone-no-propellers.svg',
            altImage: 'assets/cardboard-vr-glasses.png',
            description: 'Professional cinematic drone chassis optimized for sitting still on hardwood floors.',
            warning: '*Flight requires optional propellers, motors, and physics.',
            details: [
                'Condition: Aerodynamic in theory',
                'Top speed: 0 mph',
                'Camera: Placeholder sticker',
                'Range: Long if carried',
                'Noise: Gentle disappointment',
                'Returns: You pay emotional shipping'
            ]
        },
        {
            name: 'Solar Flashlight (Night Mode Unsupported)',
            price: 9,
            originalPrice: 59,
            image: 'assets/solar-flashlight.svg',
            altImage: 'assets/amazon-dash-button.png',
            description: 'Turns sunlight directly into confidence. Runtime at night: technically unresolved.',
            warning: '*For daylight emergencies only.',
            details: [
                'Condition: Brightly confused',
                'Power source: Sunlight and denial',
                'Lumens: Depends on optimism',
                'Emergency use: Noon to 12:01 PM',
                'Charge indicator: Always maybe',
                'Returns: Daytime only'
            ]
        },
        {
            name: 'Quantum RAM 8GB (Exists in Superposition)',
            price: 42,
            originalPrice: 420,
            image: 'assets/quantum-ram.png',
            altImage: 'assets/bitcoin-coin.png',
            description: 'Memory module that is both installed and not installed until Device Manager is observed.',
            warning: '*Benchmarks collapse waveform and void warranty.',
            details: [
                'Condition: Probabilistic',
                'Capacity: 8GB +/- observer effect',
                'Speed: Fast in parallel universes',
                'RGB: Schrodinger mode',
                'Compatibility: Maybe your motherboard',
                'Returns: Uncertain'
            ]
        },
        {
            name: 'USB Pet Rock (Firmware v0.0.0)',
            price: 4,
            originalPrice: 99,
            image: 'assets/pet-rock.png',
            altImage: 'assets/nft-coin.png',
            description: 'Pet rock with a non-functional USB port for improved modernity and investor confidence.',
            warning: '*Do not upgrade firmware while emotional.',
            details: [
                'Condition: Mineral',
                'OS: PebbleOS',
                'Ports: USB-A shaped hole',
                'AI features: Silent mode only',
                'Durability: Geologic',
                'Returns: Requires mountain permit'
            ]
        },
        {
            name: 'Smart Fridge Crypto Miner (Heat Edition)',
            price: 55,
            originalPrice: 1299,
            image: 'assets/crypto-fridge.png',
            altImage: 'assets/rabbit-r1.png',
            description: 'Connected fridge that mines coins and warms milk to room-temperature nostalgia.',
            warning: '*Electric bill sold separately.',
            details: [
                'Condition: Loud and ambitious',
                'Cooling performance: Inspirational',
                'Hash rate: One coin per fiscal quarter',
                'Noise level: Data center adjacent',
                'Food safety: Optional',
                'Returns: Denied by blockchain'
            ]
        },
        {
            name: 'Foldable Keyboard Accordion Pro',
            price: 13,
            originalPrice: 189,
            image: 'assets/foldable-keyboard-accordion.png',
            altImage: 'assets/missing-key-laptop-2.png',
            description: 'Portable keyboard that folds in 13 places and unfolds in none of them.',
            warning: '*Every key maps to Ctrl+Z.',
            details: [
                'Condition: Crunchy',
                'Travel size: Pocket-adjacent',
                'Input delay: Theatrical',
                'Layout: Accordion ANSI',
                'Durability: Hinges resent users',
                'Returns: Requires sheet music'
            ]
        },
        {
            name: 'Vibe Coder Keyboard (Token Burn Edition)',
            price: 88,
            originalPrice: 699,
            image: 'assets/vibe-code-keyboard.png',
            altImage: 'assets/foldable-keyboard-accordion.png',
            description: 'A premium coding keyboard that mostly accepts Return and occasionally Escape while a cheerful token burn screen displays your spend.',
            warning: '*Credit card adapter sold separately.',
            details: [
                'Condition: Startup-fresh with coffee aura',
                'Primary keys: Return (always), Escape (sometimes)',
                'Token burn screen: Real-time spend, no pause button',
                'Typing experience: 92% autocomplete confidence',
                'Payment interface: Swipe to keep prompting',
                'Accessory required: Credit card adapter (not included)'
            ]
        },
        {
            name: 'AI Spoon (Wi-Fi Stirring Subscription)',
            price: 7,
            originalPrice: 79,
            image: 'assets/smart-spoon.png',
            altImage: 'assets/humane-pin-paperweight.png',
            description: 'Smart utensil with machine learning that predicts soup but refuses cereal without premium plan.',
            warning: '*LLM may summarize your oatmeal incorrectly.',
            details: [
                'Condition: Spoon-shaped',
                'Connectivity: 2.4GHz only',
                'Auto-stir: Every third attempt',
                'Dishwasher safe: No',
                'Subscriptions: Basic, Pro, Ladle+',
                'Returns: Processed by chatbot'
            ]
        },
        {
            name: 'Charging Cable Knot (Pre-Tangled)',
            price: 2,
            originalPrice: 25,
            image: 'assets/tangled-cable-ball.png',
            altImage: 'assets/cracked-phone-2.png',
            description: 'Factory-certified knot technology ensures immediate pocket chaos and mysterious adapter anxiety.',
            warning: '*Untangling voids collector value.',
            details: [
                'Condition: Knot-forward',
                'Connector: USB-C-ish',
                'Length: Depends on tension',
                'Charge speed: Somewhere between 0W and 3W',
                'Braided: Emotionally',
                'Returns: Denied after first sigh'
            ]
        },
        {
            name: 'Startup Juice Box (Seed Round Included)',
            price: 6,
            originalPrice: 120,
            image: 'assets/startup-juice.svg',
            altImage: 'assets/twitter-peak.png',
            description: 'Cold-pressed disruption beverage for founders pivoting from social app to AI pet marketplace.',
            warning: '*Contains pitch deck fragments.',
            details: [
                'Condition: Shelf-stable optimism',
                'Flavor: Product-market fit',
                'Nutrients: Mostly buzzwords',
                'Cap table: Diluted',
                'Caffeine: 400mg',
                'Returns: Must schedule a demo'
            ]
        },
        {
            name: 'Mystery E-Waste Box ???',
            price: 14,
            originalPrice: 400,
            image: 'assets/mystery-e-waste-box.png',
            altImage: 'assets/product-range.png',
            description: 'A sealed box of glorious uncertainty. Could be a GPU bracket, could be twelve USB-B cables.',
            warning: '*Each refresh may change contents. Fear is part of fulfillment.',
            details: [
                'Condition: Sealed with tape and chaos',
                'Possible contents: Unknown',
                'Weight: Surprising',
                'Resale value: Narrative driven',
                'Collector appeal: High among interns',
                'Returns: Box judges your intent'
            ]
        },
        {
            name: 'Startup Graveyard Starter Pack (Bundle)',
            price: 66,
            originalPrice: 2046,
            image: 'assets/product-range.png',
            altImage: 'assets/rabbit-r1.png',
            description: 'Includes one Rabbit R1, one AI Pin paperweight, one Dash Button, and one existential crisis.',
            warning: '*Bundle discount calculated by vibes, not arithmetic.',
            details: [
                'Bundle count: 4 questionable artifacts',
                'Saved amount: Allegedly $1980',
                'Packaging: Reused conference tote bag',
                'Market sentiment: Cautious laughter',
                'Support: Chatbot-only',
                'Returns: Bundle cannot be emotionally separated'
            ],
            bundle: true
        },
        {
            name: 'Influencer Collapse Bundle (Bundle)',
            price: 33,
            originalPrice: 1499,
            image: 'assets/bored-ape-nft.png',
            altImage: 'assets/nft-coin.png',
            description: 'A blue check screenshot, NFT JPG printout, and three ring lights that only flicker.',
            warning: '*Algorithm not included. Engagement guaranteed to plummet.',
            details: [
                'Bundle count: 5 fragile dreams',
                'Best for: Performing authenticity',
                'Resale: 0.02 ETH maybe',
                'Monetization: Deprecated',
                'Warranty: During trend cycle only',
                'Returns: Must post apology video'
            ],
            bundle: true
        },
        {
            name: 'Help Desk Survival Bundle (Bundle)',
            price: 27,
            originalPrice: 899,
            image: 'assets/clippy-action-figure.png',
            altImage: 'assets/microsoft-zune.png',
            description: 'Comes with Clippy figure, rotary mouse, and a laminated card that says “Have you tried rebooting?”',
            warning: '*May trigger nostalgic panic in sysadmins.',
            details: [
                'Bundle count: 3 support relics',
                'Primary use: Desk theater',
                'Compatibility: Tier-1 humor',
                'Response time: 48 business years',
                'Escalation path: Circular',
                'Returns: Open ticket never closes'
            ],
            bundle: true
        }
    ];

    global.wreckedUpgradeCatalog = upgradeProducts;
    global.wreckedUpgradeDetailProducts = upgradeProducts;
    global.wreckedMysteryBoxVariants = [
        'One obsolete charger and a note that says "almost there".',
        'Three left AirPods and a blockchain whitepaper.',
        'A GPU bracket, two fax cables, and shame.',
        'A startup hoodie, dead smartwatch, and nine SIM ejectors.',
        'Only bubble wrap. Premium bubble wrap, though.',
        'Fifteen mystery dongles that all fit nothing.'
    ];

    if (typeof global.getMysteryEwasteDescription !== 'function') {
        global.getMysteryEwasteDescription = function() {
            return global.wreckedMysteryBoxVariants[Math.floor(Math.random() * global.wreckedMysteryBoxVariants.length)];
        };
    }

})(window);
