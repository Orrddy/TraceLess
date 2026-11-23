/**
 * Landing-page effects for TraceLess.
 *
 * - Headline typewriter with realistic corrections (original).
 * - "We see you" browser/device panel using the MIT-licensed @marufme/device-key
 *   plus direct Web APIs for GPU, storage, capabilities and fingerprint detail.
 * - Water ripple distortion: adapted from the licensed Mouse Effects bundle.
 * - Two crawlers: adapted from the licensed Web Crawlers bundle.
 *
 * Everything degrades honestly: unsupported APIs show "Not exposed" rather than
 * a guess, reduced motion disables animation, and nothing is sent to the server.
 */
import {
    getOSInfo,
    getBrowserInfo,
    getNetworkInfo,
    getBatteryInfo,
    getDeviceId,
    generateFingerprint,
    getCanvasFingerprint,
} from '@marufme/device-key';

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
 * 2. "We see you" device panel.
 * ---------------------------------------------------------------------- */
const number = (value, unit = '') => (value === null || value === undefined || Number.isNaN(value) ? null : `${value}${unit}`);

function gpuInfo() {
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        if (!gl) return null;

        const debug = gl.getExtension('WEBGL_debug_renderer_info');
        const vendor = debug ? gl.getParameter(debug.UNMASKED_VENDOR_WEBGL) : gl.getParameter(gl.VENDOR);
        const renderer = debug ? gl.getParameter(debug.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
        const maxTexture = gl.getParameter(gl.MAX_TEXTURE_SIZE);

        return {
            vendor: vendor || null,
            renderer: renderer || null,
            capabilities: `${maxTexture}px tex · ${gl.getParameter(gl.MAX_VIEWPORT_DIMS)?.join('×')} viewport${gl.getContextAttributes()?.antialias ? ' · AA' : ''}`,
        };
    } catch (error) {
        return null;
    }
}

function capabilities() {
    const media = navigator.mediaDevices;
    const touch = navigator.maxTouchPoints > 0 || 'ontouchstart' in window;
    const camera = typeof media?.getUserMedia === 'function';
    const devices = typeof media?.enumerateDevices === 'function';

    return {
        touch,
        camera,
        devices,
        media: media ? 'available (permission required)' : 'not exposed',
    };
}

async function storageInfo() {
    const local = (() => {
        try {
            localStorage.setItem('__tl_probe', '1');
            localStorage.removeItem('__tl_probe');

            return true;
        } catch (error) {
            return false;
        }
    })();

    const indexed = typeof window.indexedDB !== 'undefined';
    let quota = null;

    try {
        const estimate = await navigator.storage?.estimate?.();
        if (estimate?.quota) {
            const gb = estimate.quota / 1024 ** 3;
            quota = gb >= 1 ? `${gb.toFixed(1)} GB available` : `${Math.round(estimate.quota / 1024 ** 2)} MB available`;
        }
    } catch (error) {
        quota = null;
    }

    return {
        local,
        indexed,
        quota,
    };
}

function privacyNotes() {
    const ua = navigator.userAgent;
    const ios = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const safari = /Safari/i.test(ua) && !/Chrome|Chromium|Edg|OPR|CriOS|Firefox|Android/i.test(ua);
    const firefox = /Firefox|FxiOS/i.test(ua);

    if (ios) {
        return 'iOS limits this hard: Safari exposes no battery API, device memory, network details or user-agent client hints. “Not exposed” here is the browser protecting you — not a failed check.';
    }

    if (safari) {
        return 'Safari hides most fingerprinting surfaces: no user-agent client hints, device memory or network information, and bot-checked canvas/WebGL values. “Not exposed” is expected on macOS.';
    }

    if (firefox) {
        return 'Firefox resists fingerprinting: user-agent client hints and device memory are withheld, and canvas/WebGL signatures may be randomised per session.';
    }

    return 'Chrome and Edge expose the most: user-agent client hints, device memory, network estimates and battery (on some platforms). Everything here stays in your browser.';
}

function setField(name, value) {
    document.querySelectorAll(`[data-device-field="${name}"]`).forEach((el) => {
        el.textContent = value === null || value === undefined || value === '' ? 'Not exposed' : value;
    });
}

async function initDevicePanel() {
    const compact = document.querySelector('[data-device-check]');
    const detailed = document.querySelector('[data-device-detail]');
    if (!compact && !detailed) return;

    const os = getOSInfo();
    const browser = getBrowserInfo();
    const network = getNetworkInfo();
    const isIPadDesktop = /iPad/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isIPhone = /iPhone|iPod/i.test(ua);
    const isIOS = isIPhone || isIPadDesktop;

    const deviceType = isIPadDesktop
        ? 'Tablet'
        : (/Mobi|Android/i.test(ua) ? 'Mobile' : 'Desktop');

    const mac = !isIOS && (/mac/i.test(os.name || '') || navigator.platform === 'MacIntel');

    let model = null;
    let platformVersion = null;
    try {
        if (navigator.userAgentData?.getHighEntropyValues) {
            const high = await navigator.userAgentData.getHighEntropyValues(['model', 'platformVersion']);
            model = high.model || null;
            platformVersion = high.platformVersion || null;
        }
    } catch (error) {
        model = null;
    }

    const deviceModel = model
        || (isIPhone ? 'iPhone (model hidden by iOS)' : null)
        || (isIPadDesktop ? 'iPad (model hidden by iPadOS)' : null)
        || (mac ? 'Mac (hardware model not exposed)' : null)
        || (deviceType === 'Desktop'
            ? (/windows/i.test(os.name || '') ? 'Windows PC' : (os.name || 'PC'))
            : `${os.name || 'Mobile device'} ${deviceType.toLowerCase()}`)
        || 'Not exposed';

    const engine = isIOS ? 'WebKit' : (browser.engine || 'Unknown engine');
    const vendor = isIOS ? 'Apple (WebKit required on iOS)' : (browser.vendor || 'Unknown vendor');
    const browserLine = [browser.name || 'Unknown', browser.version, engine, vendor].filter(Boolean).join(' · ');
    const windowsMajor = parseInt(String(platformVersion || '').split('.')[0] || '0', 10);
    const osName = isIPadDesktop
        ? 'iPadOS'
        : (/windows/i.test(os.name || '') && windowsMajor >= 13 ? 'Windows 11' : (os.name || 'Unknown OS'));
    const osVersion = isIPadDesktop
        ? (navigator.userAgent.match(/OS (\d+)[._]/)?.[1] && `iPadOS ${navigator.userAgent.match(/OS (\d+)[._]/)[1]}`)
        : (/windows/i.test(os.name || '') && windowsMajor >= 13 ? null : os.version);
    const osLine = [osName, osVersion, os.architecture].filter(Boolean).join(' · ');

    // Compact card (hero).
    setField('device', deviceModel);
    setField('browser', [browser.name, browser.version].filter(Boolean).join(' ') || 'Unknown browser');
    setField('spec', `${osLine} · ${window.screen.width}×${window.screen.height}${capabilities().touch ? ' · touch' : ' · pointer'}`);
    setField('screen', `${window.screen.width}×${window.screen.height} · ${window.devicePixelRatio.toFixed(1)}x · ${navigator.language || 'unknown locale'}`);

    // Detailed panel.
    setField('d-browser', browserLine);
    setField('d-os', osLine);
    setField('d-type', deviceType);
    setField('d-model', deviceModel);
    setField('d-cpu', number(navigator.hardwareConcurrency, ' logical cores') || 'Not exposed');
    setField('d-memory', navigator.deviceMemory ? `≈ ${navigator.deviceMemory} GB RAM` : 'Not exposed');
    setField('d-display', `${window.screen.width}×${window.screen.height} · ${window.devicePixelRatio.toFixed(2)}x · ${window.screen.colorDepth}-bit`);

    const gpu = gpuInfo();
    setField('d-gpu', gpu ? `${gpu.vendor || 'Unknown vendor'} · ${gpu.renderer || 'Unknown renderer'}` : 'Not exposed');
    setField('d-gpu-cap', gpu?.capabilities || 'Not exposed');

    const networkTypes = [...new Set([
        network.connectionType || connection?.type,
        connection?.effectiveType,
    ].filter(Boolean))];
    const networkLine = [
        networkTypes.join(' / '),
        connection?.downlink ? `≈ ${connection.downlink} Mb/s` : null,
        connection?.rtt ? `${connection.rtt} ms RTT` : null,
    ].filter(Boolean).join(' · ');
    setField('d-network', networkLine || 'Not exposed (Safari/Firefox hide this)');

    const caps = capabilities();
    setField('d-capabilities', `${caps.touch ? 'touch' : 'no touch'} · camera/mic API ${caps.camera ? 'available' : 'unavailable'} · media devices ${caps.devices ? 'enumerable' : 'unavailable'}`);

    setField('d-ua', ua);
    setField('d-locale', `${navigator.language || 'unknown'} · ${Intl.DateTimeFormat().resolvedOptions().timeZone || 'unknown timezone'}`);

    // Battery (async, may be unavailable by design).
    if (navigator.getBattery) {
        try {
            const battery = await getBatteryInfo();
            const rawLevel = Number(battery.level ?? 0);
            const level = Math.min(100, Math.round(rawLevel > 1 ? rawLevel : rawLevel * 100));
            const charging = battery.charging ? 'charging' : 'on battery';
            const chargeTime = battery.charging && Number.isFinite(battery.chargingTime) && battery.chargingTime > 0
                ? ` · ${Math.round(battery.chargingTime / 60)} min to full`
                : '';
            setField('battery', `${level}% · ${charging}${chargeTime}`);
            setField('d-battery', `${level}% · ${charging}${chargeTime}`);
            const bar = detailed?.querySelector('[data-battery-bar]') || compact?.querySelector('[data-battery-bar]');
            if (bar) bar.style.width = `${level}%`;
        } catch (error) {
            setField('battery', 'Not exposed by this browser');
            setField('d-battery', 'Not exposed by this browser');
        }
    } else {
        setField('battery', 'Not exposed by this browser');
        setField('d-battery', 'Not exposed by this browser');
    }

    // Storage (async).
    const storage = await storageInfo();
    setField('d-storage', [
        storage.local ? 'LocalStorage' : 'no LocalStorage',
        storage.indexed ? 'IndexedDB' : 'no IndexedDB',
        storage.quota,
    ].filter(Boolean).join(' · '));

    // Fingerprinting (async, local only — never stored or sent).
    try {
        const [fingerprint, identity] = await Promise.all([generateFingerprint(), getDeviceId().catch(() => null)]);
        const canvas = getCanvasFingerprint();
        setField('d-fingerprint', `${String(fingerprint).slice(0, 16)}… · canvas ${canvas ? 'signature captured' : 'blocked'}`);
        setField('d-device-id', identity?.deviceId ? `${String(identity.deviceId).slice(0, 18)}…` : 'Not exposed');
    } catch (error) {
        setField('d-fingerprint', 'Not exposed (browser blocked fingerprinting)');
        setField('d-device-id', 'Not exposed');
    }

    const note = document.querySelector('[data-device-privacy-note]');
    if (note) note.textContent = privacyNotes();
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
    initDevicePanel();
    initRipple();
    startCrawlers();
}
