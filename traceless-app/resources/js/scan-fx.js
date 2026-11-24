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
    const camera = new THREE.PerspectiveCamera(38, mount.clientWidth / Math.max(1, mount.clientHeight), 0.1, 100);
    camera.position.set(0, 0.15, 4.3);

    const group = new THREE.Group();
    scene.add(group);

    const textureUrl = root.dataset.texture || '';
    const globe = new THREE.Mesh(
        new THREE.SphereGeometry(1.35, 96, 64),
        new THREE.MeshStandardMaterial({
            color: textureUrl ? 0xffffff : 0x0c2333,
            map: textureUrl ? new THREE.TextureLoader().load(textureUrl, (tex) => {
                tex.colorSpace = THREE.SRGBColorSpace;
                tex.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
            }) : null,
            roughness: 0.82,
            metalness: 0.12,
            emissive: new THREE.Color(0x061a2a),
            emissiveIntensity: 0.55,
        }),
    );
    group.add(globe);

    // Graticule overlay for the sci-fi scan-console look.
    group.add(new THREE.Mesh(
        new THREE.SphereGeometry(1.352, 48, 32),
        new THREE.MeshBasicMaterial({ color: 0x49d7ff, wireframe: true, transparent: true, opacity: 0.055 }),
    ));

    // Atmosphere halo.
    const atmosphere = new THREE.Mesh(
        new THREE.SphereGeometry(1.52, 64, 48),
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
                    float rim = pow(1.0 - abs(dot(vNormal, vView)), 2.2);
                    gl_FragColor = vec4(0.24, 0.72, 1.0, rim * 0.75);
                }
            `,
        }),
    );
    group.add(atmosphere);

    // Slow orbital rings.
    [1.75, 1.95, 2.15].forEach((radius, index) => {
        const ring = new THREE.Mesh(
            new THREE.TorusGeometry(radius, 0.0025 + index * 0.0008, 8, 240),
            new THREE.MeshBasicMaterial({ color: index === 1 ? 0x7de3ff : 0x2f7fa8, transparent: true, opacity: 0.22 }),
        );
        ring.rotation.set(1.1 + index * 0.2, index * 0.7, index * 0.45);
        group.add(ring);
    });

    // Moving scan band.
    const scanBand = new THREE.Mesh(
        new THREE.TorusGeometry(1.4, 0.006, 6, 220),
        new THREE.MeshBasicMaterial({ color: 0x7de3ff, transparent: true, opacity: 0.9, blending: THREE.AdditiveBlending }),
    );
    scanBand.rotation.x = Math.PI / 2;
    group.add(scanBand);

    const scanGlow = new THREE.Mesh(
        new THREE.TorusGeometry(1.4, 0.035, 6, 160),
        new THREE.MeshBasicMaterial({ color: 0x49d7ff, transparent: true, opacity: 0.16, blending: THREE.AdditiveBlending }),
    );
    scanGlow.rotation.x = Math.PI / 2;
    group.add(scanGlow);

    // Pulsing "hit" points across the surface.
    const hits = [];
    const hitGeometry = new THREE.SphereGeometry(0.012, 8, 8);
    for (let i = 0; i < 42; i += 1) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const radius = 1.365;
        const material = new THREE.MeshBasicMaterial({
            color: Math.random() > 0.45 ? 0x6cf0c2 : 0x7de3ff,
            transparent: true,
            opacity: 0.75,
        });
        const hit = new THREE.Mesh(hitGeometry, material);
        hit.position.set(
            radius * Math.sin(phi) * Math.cos(theta),
            radius * Math.cos(phi),
            radius * Math.sin(phi) * Math.sin(theta),
        );
        hit.userData.phase = Math.random() * Math.PI * 2;
        group.add(hit);
        hits.push(hit);
    }

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
    scene.add(new THREE.Points(starsGeometry, new THREE.PointsMaterial({ color: 0xcfe9ff, size: 0.035, transparent: true, opacity: 0.55 })));

    scene.add(new THREE.AmbientLight(0x35506b, 1.6));
    const keyLight = new THREE.DirectionalLight(0xdfefff, 2.4);
    keyLight.position.set(4, 2.5, 5);
    scene.add(keyLight);
    const rimLight = new THREE.DirectionalLight(0x49d7ff, 1.1);
    rimLight.position.set(-4, -1, -3);
    scene.add(rimLight);

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const clock = new THREE.Clock();

    const render = () => {
        const t = clock.getElapsedTime();

        if (!reduced) {
            group.rotation.y += 0.0019;
            group.rotation.x = Math.sin(t * 0.11) * 0.045;

            scanBand.position.y = Math.sin(t * 0.62) * 1.18;
            scanGlow.position.y = scanBand.position.y;
            scanBand.material.opacity = 0.55 + 0.35 * Math.sin(t * 2.4);
            scanGlow.material.opacity = 0.1 + 0.08 * Math.sin(t * 2.4);

            hits.forEach((hit) => {
                const pulse = 0.55 + 0.65 * Math.max(0, Math.sin(t * 1.8 + hit.userData.phase));
                hit.scale.setScalar(pulse);
                hit.material.opacity = 0.25 + pulse * 0.45;
            });

            camera.position.x = Math.sin(t * 0.09) * 0.18;
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
