/**
 * Landing-page effects for TraceLess.
 *
 * - Headline typewriter with realistic corrections (original).
 * - Device/browser probe card, filled only from browser-exposed APIs (original).
 * - Water ripple distortion: adapted from the licensed Mouse Effects bundle
 *   (see vendor/water-ripple.js).
 * - Two crawlers: adapted from the licensed Web Crawlers bundle
 *   (see vendor/web-crawler-spider.js).
 *
 * Everything degrades: reduced motion, no WebGL2 or no JS leave the page static.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* -------------------------------------------------------------------------
 * 1. Typewriter with corrections, a green/red cursor and a pause before looping.
 * ---------------------------------------------------------------------- */
function initTypewriter() {
    const target = document.querySelector('[data-typewriter]');
    if (!target) return;

    const text = target.dataset.text || target.dataset.typewriter || target.textContent;
    const cursor = document.querySelector('[data-typewriter-cursor]');

    if (reduceMotion()) {
        target.textContent = text;
        cursor?.classList.add('is-idle');

        return;
    }

    const typoChars = 'xzkqvwj';
    let index = 0;
    let mistakes = 0;

    target.textContent = '';

    const setCursor = (state) => {
        if (!cursor) return;
        cursor.classList.remove('is-typing', 'is-correct', 'is-error', 'is-idle');
        if (state) cursor.classList.add(state);
    };

    const type = () => {
        if (index >= text.length) {
            setCursor('is-correct');
            setTimeout(() => {
                setCursor('is-idle');
                setTimeout(() => {
                    target.textContent = '';
                    index = 0;
                    mistakes = 0;
                    setCursor('is-typing');
                    setTimeout(type, 400);
                }, 900);
            }, 2600);

            return;
        }

        if (mistakes < 2 && index > 6 && Math.random() < 0.08) {
            mistakes += 1;
            target.textContent += typoChars[Math.floor(Math.random() * typoChars.length)];
            setCursor('is-error');
            setTimeout(() => {
                target.textContent = target.textContent.slice(0, -1);
                setCursor('is-typing');
                setTimeout(type, 120);
            }, 340);

            return;
        }

        target.textContent += text[index];
        index += 1;
        setCursor('is-typing');
        setTimeout(type, 26 + Math.random() * 42);
    };

    setTimeout(type, 500);
}

/* -------------------------------------------------------------------------
 * 2. "We see you" device card — only what the browser itself exposes.
 * ---------------------------------------------------------------------- */
function browserLabel() {
    const uaData = navigator.userAgentData;
    if (uaData && Array.isArray(uaData.brands) && uaData.brands.length) {
        const meaningful = uaData.brands.filter((b) => !/Not.?A.?Brand|Chromium/i.test(b.brand));
        const chosen = meaningful.find((b) => /Chrome|Edge|Opera|Brave|Samsung|Vivaldi/i.test(b.brand)) || meaningful[0] || uaData.brands[0];

        return chosen ? `${chosen.brand}${chosen.version ? ' ' + chosen.version : ''}` : 'Unknown browser';
    }

    const ua = navigator.userAgent;
    const patterns = [
        [/Edg\/(\d+)/, 'Microsoft Edge'],
        [/OPR\/(\d+)/, 'Opera'],
        [/SamsungBrowser\/(\d+)/, 'Samsung Internet'],
        [/Firefox\/(\d+)/, 'Firefox'],
        [/CriOS\/(\d+)/, 'Chrome on iOS'],
        [/Chrome\/(\d+)/, 'Google Chrome'],
        [/Version\/(\d+).*Safari/, 'Safari'],
    ];
    for (const [pattern, name] of patterns) {
        const match = ua.match(pattern);
        if (match) return `${name} ${match[1]}`;
    }

    return 'Unknown browser';
}

function platformLabel(high = {}) {
    const ua = navigator.userAgent;
    const platform = high.platform || navigator.userAgentData?.platform || navigator.platform || '';
    const version = high.platformVersion || '';

    if (/Android/i.test(ua) || /Android/i.test(platform)) {
        const androidVersion = (ua.match(/Android\s([\d.]+)/) || [])[1] || version.split('.')[0];
        return {
            device: high.model || 'Android phone',
            spec: `Android${androidVersion ? ' ' + androidVersion : ''}`,
        };
    }

    if (/iPhone/i.test(ua)) return { device: 'iPhone', spec: (ua.match(/OS (\d+)_/) ? 'iOS ' + ua.match(/OS (\d+)_/)[1] : 'iOS') };
    if (/iPad/i.test(ua)) return { device: 'iPad', spec: (ua.match(/OS (\d+)_/) ? 'iPadOS ' + ua.match(/OS (\d+)_/)[1] : 'iPadOS') };

    if (/Windows/i.test(ua) || /Windows/i.test(platform)) {
        const major = parseInt(version.split('.')[0] || '0', 10);
        const label = major >= 13 ? 'Windows 11' : (major >= 1 ? 'Windows 10' : 'Windows');
        return { device: 'Windows PC', spec: label };
    }

    if (/Mac/i.test(platform) || /Mac OS X/i.test(ua)) {
        const major = (ua.match(/Mac OS X (\d+)[._](\d+)/) || []).slice(1);
        return { device: 'Mac', spec: major.length === 2 ? `macOS ${major[0]}.${major[1]}` : 'macOS' };
    }

    if (/Linux/i.test(platform)) return { device: 'Linux workstation', spec: 'Linux' };

    return { device: platform || 'Unknown device', spec: high.architecture ? `${high.architecture}${high.bitness ? ' · ' + high.bitness + '-bit' : ''}` : 'Unavailable' };
}

async function initDeviceCheck() {
    const root = document.querySelector('[data-device-check]');
    if (!root) return;

    const field = (name) => root.querySelector(`[data-device-field="${name}"]`);
    const set = (name, value) => {
        const el = field(name);
        if (el) el.textContent = value;
    };

    set('browser', browserLabel());

    let high = {};
    try {
        if (navigator.userAgentData?.getHighEntropyValues) {
            high = await navigator.userAgentData.getHighEntropyValues(['model', 'platformVersion', 'architecture', 'bitness']);
        }
    } catch (error) {
        high = {};
    }

    const platform = platformLabel(high);
    set('device', platform.device);
    set('spec', `${platform.spec} · ${window.screen.width}×${window.screen.height}${navigator.maxTouchPoints > 0 ? ' · touch' : ' · pointer'}`);
    set('screen', `${window.screen.width}×${window.screen.height} · ${window.devicePixelRatio.toFixed(1)}x · ${navigator.language || 'unknown locale'}`);

    const batteryField = field('battery');
    if (!batteryField) return;

    if (!navigator.getBattery) {
        batteryField.textContent = 'Not exposed by this browser';

        return;
    }

    try {
        const battery = await navigator.getBattery();
        const paint = () => {
            const level = Math.round(battery.level * 100);
            batteryField.textContent = `${level}% · ${battery.charging ? 'charging' : 'on battery'}`;
            set('battery-bar', '');
            const bar = root.querySelector('[data-battery-bar]');
            if (bar) bar.style.width = `${level}%`;
        };
        paint();
        battery.addEventListener('levelchange', paint);
        battery.addEventListener('chargingchange', paint);
    } catch (error) {
        batteryField.textContent = 'Not exposed by this browser';
    }
}

/* -------------------------------------------------------------------------
 * 3. Licensed third-party effects.
 * ---------------------------------------------------------------------- */
function initRipple() {
    if (reduceMotion()) return;

    import('./vendor/water-ripple').then(({ initWaterRipple }) => {
        initWaterRipple();
    });
}

function startCrawlers() {
    if (reduceMotion()) return;

    import('./vendor/web-crawler-spider').then(() => {
        const factory = window.createTraceLessCrawler;
        if (typeof factory !== 'function') return;

        [
            ['__tlCrawlerOne', { autoRead: false, size: 'S', density: 'calm' }],
            ['__tlCrawlerTwo', { autoRead: false, size: 'S', density: 'calm' }],
        ].forEach(([key, options]) => {
            try {
                factory(key);
                window[key]?.start(options);
            } catch (error) {
                // A failed overlay must never break the page.
            }
        });
    });
}

export function initLandingEffects() {
    initTypewriter();
    initDeviceCheck();
    initRipple();
    startCrawlers();
}
