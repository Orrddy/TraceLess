/**
 * Scan page: rotating 3D Earth, sci-fi scan sweep and a server-timed countdown.
 *
 * The countdown is derived from absolute server timestamps embedded in the DOM
 * (and refreshed by /scans/{id}/status), so it is identical after a logout/login
 * or on a second phone. The Earth is a Three.js texture-mapped globe with a
 * procedural atmosphere, orbital rings and a moving scan band.
 */
import * as THREE from 'three';

const STAGES = [
    'Booting the crawl mesh',
    'Scanning the public web layer',
    'Scraping indexed source mirrors',
    'Pinging broker endpoints',
    'Matching identity records',
    'Scoring the evidence graph',
];

const pad = (n) => String(n).padStart(2, '0');

function formatRemaining(ms) {
    if (ms <= 0) return '00:00:00';
    const total = Math.floor(ms / 1000);
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;

    return `${pad(h)}:${pad(m)}:${pad(s)}`;
}

function initScene(root) {
    const mount = root.querySelector('[data-earth]');
    if (!mount) return;

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    } catch (error) {
        root.classList.add('scan-no-webgl');

        return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(mount.clientWidth, mount.clientHeight);
    mount.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, mount.clientWidth / Math.max(1, mount.clientHeight), 0.1, 100);
    camera.position.set(0, 0.12, 5.4);

    const group = new THREE.Group();
    scene.add(group);

    const loader = new THREE.TextureLoader();
    const anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());

    const dayMap = root.dataset.texture ? loader.load(root.dataset.texture, (tex) => {
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = anisotropy;
    }) : null;
    const normalMap = root.dataset.normal ? loader.load(root.dataset.normal) : null;
    const specularMap = root.dataset.specular ? loader.load(root.dataset.specular) : null;
    const cloudMap = root.dataset.clouds ? loader.load(root.dataset.clouds) : null;

    // Physically-lit blue marble: day texture, land relief, ocean specular and a
    // separately-rotating cloud shell for a Google-Earth-like look.
    const globe = new THREE.Mesh(
        new THREE.SphereGeometry(1.35, 128, 96),
        new THREE.MeshPhongMaterial({
            color: dayMap ? 0xffffff : 0x0c2333,
            map: dayMap,
            normalMap,
            normalScale: new THREE.Vector2(0.75, 0.75),
            specularMap,
            specular: new THREE.Color(0x5b8aa6),
            shininess: 22,
            emissive: new THREE.Color(0x020912),
            emissiveIntensity: 0.22,
        }),
    );
    group.add(globe);

    let cloudShell = null;
    if (cloudMap) {
        cloudShell = new THREE.Mesh(
            new THREE.SphereGeometry(1.365, 128, 96),
            new THREE.MeshPhongMaterial({
                color: 0xffffff,
                map: cloudMap,
                transparent: true,
                opacity: 0.82,
                depthWrite: false,
                alphaTest: 0.01,
            }),
        );
        group.add(cloudShell);
    }

    // Atmosphere halo.
    const atmosphere = new THREE.Mesh(
        new THREE.SphereGeometry(1.48, 64, 48),
        new THREE.ShaderMaterial({
            transparent: true,
            blending: THREE.AdditiveBlending,
            side: THREE.BackSide,
            depthWrite: false,
            vertexShader: `
                varying vec3 vNormal;
                varying vec3 vView;
                void main() {
                    vNormal = normalize(normalMatrix * normal);
                    vec4 mv = modelViewMatrix * vec4(position, 1.0);
                    vView = normalize(-mv.xyz);
                    gl_Position = projectionMatrix * mv;
                }
            `,
            fragmentShader: `
                varying vec3 vNormal;
                varying vec3 vView;
                void main() {
                    float rim = pow(1.0 - abs(dot(vNormal, vView)), 3.2);
                    gl_FragColor = vec4(0.32, 0.62, 1.0, rim * 0.55);
                }
            `,
        }),
    );
    group.add(atmosphere);

    // Moving scan band.
    const scanBand = new THREE.Mesh(
        new THREE.TorusGeometry(1.37, 0.004, 6, 220),
        new THREE.MeshBasicMaterial({ color: 0x7de3ff, transparent: true, opacity: 0.55, blending: THREE.AdditiveBlending }),
    );
    scanBand.rotation.x = Math.PI / 2;
    group.add(scanBand);

    const scanGlow = new THREE.Mesh(
        new THREE.TorusGeometry(1.37, 0.02, 6, 160),
        new THREE.MeshBasicMaterial({ color: 0x49d7ff, transparent: true, opacity: 0.09, blending: THREE.AdditiveBlending }),
    );
    scanGlow.rotation.x = Math.PI / 2;
    group.add(scanGlow);

    // Star field.
    const starsGeometry = new THREE.BufferGeometry();
    const starPositions = new Float32Array(900 * 3);
    for (let i = 0; i < starPositions.length; i += 3) {
        const r = 8 + Math.random() * 16;
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        starPositions[i] = r * Math.sin(phi) * Math.cos(theta);
        starPositions[i + 1] = r * Math.cos(phi);
        starPositions[i + 2] = r * Math.sin(phi) * Math.sin(theta) - 4;
    }
    starsGeometry.setAttribute('position', new THREE.BufferAttribute(starPositions, 3));
    scene.add(new THREE.Points(starsGeometry, new THREE.PointsMaterial({ color: 0xcfe9ff, size: 0.025, transparent: true, opacity: 0.45 })));

    scene.add(new THREE.AmbientLight(0xffffff, 2.2));
    const keyLight = new THREE.DirectionalLight(0xffffff, 2.1);
    keyLight.position.set(0.7, 0.6, 6);
    scene.add(keyLight);
    const fillLight = new THREE.DirectionalLight(0x4a90c2, 0.55);
    fillLight.position.set(-4, 1, 2);
    scene.add(fillLight);

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const clock = new THREE.Clock();

    const render = () => {
        const t = clock.getElapsedTime();

        if (!reduced) {
            group.rotation.y += 0.0024;
            group.rotation.x = 0.408 + Math.sin(t * 0.05) * 0.015;
            if (cloudShell) cloudShell.rotation.y += 0.0008;

            scanBand.position.y = Math.sin(t * 0.62) * 1.18;
            scanGlow.position.y = scanBand.position.y;
            scanBand.material.opacity = 0.55 + 0.35 * Math.sin(t * 2.4);
            scanGlow.material.opacity = 0.1 + 0.08 * Math.sin(t * 2.4);

            camera.position.x = Math.sin(t * 0.07) * 0.08;
            camera.lookAt(0, 0, 0);
        }

        renderer.render(scene, camera);

        if (!reduced) requestAnimationFrame(render);
    };

    const resize = () => {
        const width = mount.clientWidth;
        const height = mount.clientHeight;
        if (!width || !height) return;
        renderer.setSize(width, height);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };

    new ResizeObserver(resize).observe(mount);
    render();
}

function initClock(root) {
    const timer = root.querySelector('[data-timer]');
    if (!timer) return;

    const statusUrl = root.dataset.statusUrl;
    const serverNow = Number(root.dataset.serverNowMs);
    const deadline = Number(root.dataset.deadlineMs);
    let status = root.dataset.status || 'queued';
    let progress = Number(root.dataset.progress || 0);
    let active = root.dataset.active === '1';
    const holding = root.dataset.holding === '1';

    if (!deadline || !Number.isFinite(deadline)) {
        // No estimate (already finished): leave server-rendered copy.
        return;
    }

    const offset = Number.isFinite(serverNow) ? serverNow - Date.now() : 0;
    const etaSeconds = Number(root.dataset.etaSeconds) || (root.dataset.type === 'deep_scan' ? 7200 : 120);
    const startAt = deadline - etaSeconds * 1000;
    const total = deadline - startAt;

    const stageItems = [...root.querySelectorAll('[data-stage-item]')];
    const stageLabel = root.querySelector('[data-stage-label]');
    const stageTicker = root.querySelector('[data-stage-ticker]');
    const fill = root.querySelector('[data-progress-fill]');
    const percent = root.querySelector('[data-progress-value]');
    const statusPill = root.querySelector('[data-status-pill]');
    const sources = root.querySelector('[data-stat-sources]');
    const findings = root.querySelector('[data-stat-findings]');

    let stageIndex = 0;

    const paint = () => {
        const now = Date.now() + offset;
        const remaining = deadline - now;
        const elapsed = Math.max(0, total - remaining);
        const fraction = total > 0 ? Math.min(1, elapsed / total) : 0;
        const simulated = Math.min(94, Math.round(fraction * 100));
        const shown = Math.max(progress, simulated);

        timer.textContent = formatRemaining(remaining);

        if (fill) fill.style.width = `${shown}%`;
        if (percent) percent.textContent = `${shown}%`;

        const simulatedStage = Math.min(STAGES.length - 1, Math.floor(fraction * STAGES.length));
        if (simulatedStage !== stageIndex) {
            stageIndex = simulatedStage;
            stageItems.forEach((item, index) => item.classList.toggle('is-active', index === stageIndex));
            if (stageTicker) stageTicker.textContent = STAGES[stageIndex];
            if (stageLabel) stageLabel.textContent = STAGES[stageIndex];
        }

        if (statusPill && active) {
            statusPill.textContent = holding || remaining <= 0 ? 'FINALIZING' : 'SCANNING';
        }
    };

    stageItems.forEach((item, index) => item.classList.toggle('is-active', index === 0));
    paint();
    const ticker = setInterval(paint, 1000);

    if (active && statusUrl) {
        const poll = async () => {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();

                status = data.status;
                active = data.is_active;
                progress = Number(data.progress || 0);

                if (data.stage) {
                    const stageText = String(data.stage);
                    if (stageLabel) stageLabel.textContent = stageText;
                    if (stageTicker) stageTicker.textContent = stageText;
                }

                if (sources) sources.textContent = `${data.sources_checked}/${data.sources_total || '—'}`;
                if (findings) findings.textContent = data.findings_count;
                if (statusPill) statusPill.textContent = data.status_label?.toUpperCase() || String(status).toUpperCase();

                if (!data.is_presented_active) {
                    clearInterval(ticker);
                    window.location.reload();
                } else if (data.holding_results) {
                    if (statusPill) statusPill.textContent = 'FINALIZING';
                    if (stageLabel) stageLabel.textContent = 'High traffic — finalizing the evidence package';
                    if (stageTicker) stageTicker.textContent = 'Assembling the evidence graph';
                }
            } catch (error) {
                // Network blip: the countdown keeps using the server timestamps.
            }
        };

        setInterval(poll, 5000);
        setTimeout(poll, 800);
    }
}

export function initScanFx() {
    const root = document.querySelector('[data-scan-fx]');
    if (!root) return;

    initScene(root);
    initClock(root);
}
