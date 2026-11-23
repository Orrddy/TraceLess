/**
 * Landing-page effects.
 *
 * Original implementations inspired by the idea of interactive mouse ripples and
 * word-eating crawlers. No third-party code is bundled: the ripple is a small
 * WebGL shader, the crawlers are plain DOM/SVG, the typewriter is timed text.
 *
 * Everything degrades to the static page: reduced motion, no WebGL, no JS.
 */

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* -------------------------------------------------------------------------
 * 1. Typewriter with mistakes, a green/red cursor and a pause before looping.
 * ---------------------------------------------------------------------- */
function initTypewriter() {
    const target = document.querySelector('[data-typewriter]');
    if (!target) return;

    const text = target.dataset.text || target.dataset.typewriter || target.textContent;

    if (reduceMotion()) {
        target.textContent = text;
        return;
    }

    const cursor = document.querySelector('[data-typewriter-cursor]');
    const typoChars = 'xzkqvwj';
    let index = 0;
    let mistakes = 0;

    target.textContent = '';
    cursor?.classList.add('is-typing');

    const setCursor = (state) => {
        if (!cursor) return;
        cursor.classList.remove('is-typing', 'is-correct', 'is-error', 'is-idle');
        if (state) cursor.classList.add(state);
    };

    const type = () => {
        if (index >= text.length) {
            // Complete: blink green, pause, then loop after a clean beat.
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

        // Occasionally type a wrong letter, blink red, delete it, then fix.
        if (mistakes < 2 && index > 6 && Math.random() < 0.08) {
            mistakes += 1;
            const wrong = typoChars[Math.floor(Math.random() * typoChars.length)];
            target.textContent += wrong;
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
 * 2. Water ripple distortion over the product film (WebGL, 8 ripple slots).
 * ---------------------------------------------------------------------- */
function initRipple() {
    const container = document.querySelector('[data-ripple]');
    if (!container) return;

    const video = container.querySelector('video');
    if (!video) return;

    const canvas = document.createElement('canvas');
    canvas.className = 'ripple-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    container.appendChild(canvas);

    const gl = canvas.getContext('webgl', { alpha: false, antialias: false, premultipliedAlpha: false });
    if (!gl) {
        canvas.remove();

        return;
    }

    if (reduceMotion()) {
        canvas.remove();

        return;
    }

    const vertex = `
        attribute vec2 aPos;
        varying vec2 vUv;
        void main() {
            vUv = aPos * 0.5 + 0.5;
            gl_Position = vec4(aPos, 0.0, 1.0);
        }
    `;

    const fragment = `
        precision mediump float;
        varying vec2 vUv;
        uniform sampler2D uTex;
        uniform vec2 uRes;
        uniform float uTime;
        uniform vec4 uRipples[8];

        void main() {
            vec2 uv = vec2(vUv.x, 1.0 - vUv.y);
            vec2 aspect = vec2(uRes.x / max(uRes.y, 1.0), 1.0);

            vec2 offset = vec2(0.0);
            float highlight = 0.0;

            for (int i = 0; i < 8; i++) {
                vec4 r = uRipples[i];
                if (r.w <= 0.0) continue;
                float age = uTime - r.z;
                if (age < 0.0 || age > 2.4) continue;

                vec2 center = r.xy;
                vec2 delta = (vUv - center) * aspect;
                float dist = length(delta);
                float radius = age * 0.42;
                float band = exp(-pow((dist - radius) / 0.055, 2.0));
                float decay = exp(-age * 1.6) * r.w;

                vec2 dir = dist > 0.0001 ? delta / dist : vec2(0.0);
                offset += dir * band * decay * 0.016;
                highlight += band * decay * 0.35;
            }

            // Chromatic aberration grows with the distortion.
            vec2 rUv = uv + offset * 1.15;
            vec2 gUv = uv + offset;
            vec2 bUv = uv + offset * 0.88;

            vec3 color;
            color.r = texture2D(uTex, rUv).r;
            color.g = texture2D(uTex, gUv).g;
            color.b = texture2D(uTex, bUv).b;

            // Soft specular sheen on the wave crest.
            color += vec3(highlight) * vec3(0.55, 0.7, 0.85);

            // Keep edges honest.
            color *= smoothstep(0.0, 0.02, uv.x) * smoothstep(1.0, 0.98, uv.x);
            gl_FragColor = vec4(color, 1.0);
        }
    `;

    const compile = (type, source) => {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            gl.deleteShader(shader);

            return null;
        }

        return shader;
    };

    const vs = compile(gl.VERTEX_SHADER, vertex);
    const fs = compile(gl.FRAGMENT_SHADER, fragment);
    if (!vs || !fs) {
        canvas.remove();

        return;
    }

    const program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        canvas.remove();

        return;
    }
    gl.useProgram(program);

    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    const aPos = gl.getAttribLocation(program, 'aPos');
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);

    const uTex = gl.getUniformLocation(program, 'uTex');
    const uRes = gl.getUniformLocation(program, 'uRes');
    const uTime = gl.getUniformLocation(program, 'uTime');
    const uRipples = gl.getUniformLocation(program, 'uRipples');

    const ripples = new Float32Array(8 * 4);
    let slot = 0;
    let lastDrop = 0;
    let running = true;

    const dropRipple = (clientX, clientY) => {
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;

        const x = (clientX - rect.left) / rect.width;
        const y = (clientY - rect.top) / rect.height;
        if (x < 0 || x > 1 || y < 0 || y > 1) return;

        const now = performance.now() / 1000;
        if (now - lastDrop < 0.055) return;
        lastDrop = now;

        const i = slot * 4;
        ripples[i] = x;
        ripples[i + 1] = y;
        ripples[i + 2] = now;
        ripples[i + 3] = 1.0;
        slot = (slot + 1) % 8;
    };

    const onPointer = (event) => dropRipple(event.clientX, event.clientY);
    container.addEventListener('pointermove', onPointer, { passive: true });
    container.addEventListener('pointerdown', onPointer, { passive: true });

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.max(1, Math.floor(rect.width * dpr));
        canvas.height = Math.max(1, Math.floor(rect.height * dpr));
        gl.viewport(0, 0, canvas.width, canvas.height);
    };

    window.addEventListener('resize', resize);
    resize();

    const observer = new IntersectionObserver((entries) => {
        running = entries[0]?.isIntersecting ?? true;
    }, { threshold: 0.05 });
    observer.observe(canvas);

    container.classList.add('has-ripple');

    const start = performance.now();
    const render = () => {
        if (running) {
            if (video.readyState >= 2) {
                gl.bindTexture(gl.TEXTURE_2D, texture);
                gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
                gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, video);
            }

            gl.uniform1i(uTex, 0);
            gl.uniform2f(uRes, canvas.width, canvas.height);
            gl.uniform1f(uTime, (performance.now() - start) / 1000);
            gl.uniform4fv(uRipples, ripples);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        }

        requestAnimationFrame(render);
    };
    requestAnimationFrame(render);
}

/* -------------------------------------------------------------------------
 * 3. Two small crawlers that walk a word strip, bite letters and move on.
 * ---------------------------------------------------------------------- */
function initCrawlers() {
    const root = document.querySelector('[data-crawlers]');
    if (!root) return;

    const words = Array.from(root.querySelectorAll('[data-crawler-word]'));
    if (!words.length) return;

    // Split words into letters so a bite can tear individual glyphs.
    words.forEach((word) => {
        const letters = Array.from(word.dataset.word || word.textContent);
        word.textContent = '';
        letters.forEach((letter, i) => {
            const span = document.createElement('span');
            span.className = 'c-letter';
            span.textContent = letter;
            span.style.setProperty('--i', i);
            word.appendChild(span);
        });
    });

    const makeCrawler = (index) => {
        const el = document.createElement('span');
        el.className = 'landing-crawler';
        el.style.setProperty('--size', index === 0 ? '26px' : '21px');
        el.innerHTML = `
            <svg viewBox="0 0 44 30" aria-hidden="true">
                <g class="crawler-legs">
                    <path d="M12 14 L2 4" /><path d="M15 17 L3 15" /><path d="M17 20 L6 26" /><path d="M20 22 L14 29" />
                    <path d="M32 14 L42 4" /><path d="M29 17 L41 15" /><path d="M27 20 L38 26" /><path d="M24 22 L30 29" />
                </g>
                <ellipse cx="22" cy="15" rx="9" ry="7" fill="currentColor" />
                <circle cx="24" cy="12" r="1.4" fill="#fff" opacity=".85" />
                <circle cx="26.5" cy="12" r="1.4" fill="#fff" opacity=".85" />
            </svg>
        `;
        root.appendChild(el);

        return { el, index, x: 8 + index * 60, speed: index === 0 ? 26 : 19, target: null, nextBite: 0 };
    };

    const crawlers = [makeCrawler(0), makeCrawler(1)];

    if (reduceMotion()) {
        crawlers.forEach((crawler, i) => {
            crawler.el.style.transform = `translate3d(${34 + i * 210}px, -6px, 0)`;
        });

        return;
    }

    const centerOf = (word) => {
        const rootRect = root.getBoundingClientRect();
        const rect = word.getBoundingClientRect();

        return rect.left - rootRect.left + rect.width / 2;
    };

    const chooseTarget = () => {
        const available = words.filter((word) => !word.classList.contains('is-torn'));
        if (!available.length) return null;

        return available[Math.floor(Math.random() * available.length)];
    };

    const bite = (crawler, word) => {
        if (!word || word.classList.contains('is-torn')) return;

        crawler.el.classList.add('is-eating');
        word.classList.add('is-torn');
        setTimeout(() => word.classList.remove('is-torn'), 4200 + Math.random() * 900);
        setTimeout(() => crawler.el.classList.remove('is-eating'), 420);
        crawler.target = null;
        crawler.nextBite = performance.now() + 900 + Math.random() * 1600;
    };

    // Pointer play: hovering steers the first crawler, clicking sends both.
    root.addEventListener('pointermove', (event) => {
        if (!crawlers[0].target) return;
        const rect = root.getBoundingClientRect();
        const pointerX = event.clientX - rect.left;
        const nearest = words.reduce((best, word) => {
            const distance = Math.abs(centerOf(word) - pointerX);

            return !best || distance < best.distance ? { word, distance } : best;
        }, null);
        if (nearest && nearest.distance < 90) crawlers[0].target = nearest.word;
    }, { passive: true });

    root.addEventListener('pointerdown', (event) => {
        const rect = root.getBoundingClientRect();
        const pointerX = event.clientX - rect.left;
        const nearest = words.reduce((best, word) => {
            const distance = Math.abs(centerOf(word) - pointerX);

            return !best || distance < best.distance ? { word, distance } : best;
        }, null);
        if (nearest) crawlers.forEach((crawler) => { crawler.target = nearest.word; });
    });

    let lastFrame = performance.now();
    const tick = (now) => {
        const delta = Math.min(64, now - lastFrame) / 1000;
        lastFrame = now;

        crawlers.forEach((crawler) => {
            if (!crawler.target) {
                if (now > crawler.nextBite) crawler.target = chooseTarget();
                if (!crawler.target) return;
            }

            const targetX = centerOf(crawler.target);
            const distance = targetX - crawler.x;
            const step = crawler.speed * delta;

            if (Math.abs(distance) <= step + 11) {
                crawler.x = targetX;
                if (Math.abs(distance) < 18 && now > crawler.nextBite - 100) {
                    bite(crawler, crawler.target);
                } else {
                    crawler.target = null;
                }
            } else {
                crawler.x += Math.sign(distance) * step;
                crawler.el.classList.toggle('is-walking', true);
            }

            crawler.el.style.transform = `translate3d(${crawler.x}px, -7px, 0) scaleX(${distance < 0 ? -1 : 1})`;
        });

        requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

export function initLandingEffects() {
    initTypewriter();
    initRipple();
    initCrawlers();
}
