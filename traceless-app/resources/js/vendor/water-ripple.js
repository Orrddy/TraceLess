/**
 * Water Ripple Distortion — adapted for TraceLess.
 *
 * Source: https://github.com/cortiz2894/mouse-effects (water-distortion effect),
 * commercial-use licence acquired from the author's official distribution. The
 * original is a React Three Fiber component; this is a dependency-free WebGL2
 * port of its shaders and three-target ping-pong fluid simulation, sampling the
 * product film as the texture instead of static images.
 */
const QUAD_VERTEX = `
    attribute vec2 aPos;
    varying vec2 vUv;
    void main() {
        vUv = aPos * 0.5 + 0.5;
        gl_Position = vec4(aPos, 0.0, 1.0);
    }
`;

const FLUID_FRAGMENT = `
    precision mediump float;
    uniform sampler2D uPrevState;
    uniform sampler2D uCurrentState;
    uniform vec2 uResolution;
    uniform float uViscosity;
    uniform float uDecay;
    uniform vec2 uMouse;
    uniform vec2 uPrevMouse;
    uniform float uRadius;
    uniform float uIntensity;
    uniform float uMouseVelocity;
    varying vec2 vUv;

    void main() {
        vec2 texel = 1.0 / uResolution;

        float current = texture2D(uCurrentState, vUv).r;
        float prev = texture2D(uPrevState, vUv).r;

        float left = texture2D(uCurrentState, vUv + vec2(-texel.x, 0.0)).r;
        float right = texture2D(uCurrentState, vUv + vec2(texel.x, 0.0)).r;
        float top = texture2D(uCurrentState, vUv + vec2(0.0, texel.y)).r;
        float bottom = texture2D(uCurrentState, vUv + vec2(0.0, -texel.y)).r;

        float neighbors = (left + right + top + bottom) * 0.25;
        float wave = neighbors * 2.0 - prev;
        wave = mix(current, wave, uViscosity);
        wave *= uDecay;

        if (uMouseVelocity > 0.0001) {
            float dist = distance(vUv, uMouse);
            float ripple = smoothstep(uRadius, 0.0, dist);
            ripple = pow(ripple, 2.0);

            for (float i = 0.0; i < 8.0; i++) {
                float t = i / 8.0;
                vec2 trailPos = mix(uPrevMouse, uMouse, t);
                float d = distance(vUv, trailPos);
                float trailRipple = smoothstep(uRadius * 0.7, 0.0, d);
                ripple = max(ripple, pow(trailRipple, 2.0));
            }

            wave += ripple * uIntensity * min(uMouseVelocity * 10.0, 1.0);
        }

        gl_FragColor = vec4(wave, wave, wave, 1.0);
    }
`;

const SCREEN_FRAGMENT = `
    precision mediump float;
    uniform sampler2D uTexture;
    uniform sampler2D uDisplacement;
    uniform float uDistortionStrength;
    uniform float uAberration;
    uniform float uLightIntensity;
    uniform float uSpecularPower;
    uniform vec2 uResolution;
    uniform float uImageAspect;
    uniform float uPlaneAspect;
    varying vec2 vUv;

    vec2 coverUv(vec2 uv, float imageAspect, float planeAspect) {
        vec2 ratio = vec2(
            min(planeAspect / imageAspect, 1.0),
            min(imageAspect / planeAspect, 1.0)
        );
        return vec2(
            uv.x * ratio.x + (1.0 - ratio.x) * 0.5,
            uv.y * ratio.y + (1.0 - ratio.y) * 0.5
        );
    }

    vec3 calculateNormal(vec2 uv, float strength) {
        vec2 texel = 1.0 / uResolution;
        float left = texture2D(uDisplacement, uv + vec2(-texel.x, 0.0)).r;
        float right = texture2D(uDisplacement, uv + vec2(texel.x, 0.0)).r;
        float top = texture2D(uDisplacement, uv + vec2(0.0, texel.y)).r;
        float bottom = texture2D(uDisplacement, uv + vec2(0.0, -texel.y)).r;
        vec3 normal;
        normal.x = (left - right) * strength;
        normal.y = (bottom - top) * strength;
        normal.z = 1.0;
        return normalize(normal);
    }

    void main() {
        vec2 coveredUv = coverUv(vUv, uImageAspect, uPlaneAspect);
        float displacement = texture2D(uDisplacement, vUv).r;
        vec3 normal = calculateNormal(vUv, 50.0);
        float normalDeviation = length(normal.xy);

        vec2 refraction = normal.xy * uDistortionStrength;
        vec2 distortedUv = clamp(coveredUv + refraction, 0.001, 0.999);

        float aberrationAmount = uAberration * (abs(normal.x) + abs(normal.y));
        vec4 colorR = texture2D(uTexture, distortedUv + vec2(aberrationAmount, 0.0));
        vec4 colorG = texture2D(uTexture, distortedUv);
        vec4 colorB = texture2D(uTexture, distortedUv - vec2(aberrationAmount, 0.0));
        vec3 color = vec3(colorR.r, colorG.g, colorB.b);

        float rippleMask = smoothstep(0.01, 0.1, normalDeviation);
        vec3 lightDir = normalize(vec3(0.5, 0.5, 1.0));
        vec3 viewDir = vec3(0.0, 0.0, 1.0);
        vec3 halfDir = normalize(lightDir + viewDir);
        float specular = pow(max(dot(normal, halfDir), 0.0), uSpecularPower);
        specular *= uLightIntensity * rippleMask;
        color += vec3(specular);

        float fresnel = pow(1.0 - max(dot(normal, viewDir), 0.0), 2.0);
        color += vec3(fresnel * uLightIntensity * 0.1 * rippleMask);

        gl_FragColor = vec4(color, 1.0);
    }
`;

const SIM_SIZE = 512;

function compile(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        gl.deleteShader(shader);

        return null;
    }

    return shader;
}

function program(gl, vertexSource, fragmentSource) {
    const vs = compile(gl, gl.VERTEX_SHADER, vertexSource);
    const fs = compile(gl, gl.FRAGMENT_SHADER, fragmentSource);
    if (!vs || !fs) return null;

    const linked = gl.createProgram();
    gl.attachShader(linked, vs);
    gl.attachShader(linked, fs);
    gl.linkProgram(linked);
    if (!gl.getProgramParameter(linked, gl.LINK_STATUS)) return null;

    return linked;
}

function makeTarget(gl, size) {
    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA16F, size, size, 0, gl.RGBA, gl.HALF_FLOAT, null);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);

    const framebuffer = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, framebuffer);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texture, 0);
    gl.clearColor(0, 0, 0, 1);
    gl.clear(gl.COLOR_BUFFER_BIT);

    return { texture, framebuffer };
}

/**
 * Attach the ripple canvas to a container holding the film <video>.
 * Returns false when WebGL2 float rendering is unavailable (video stays visible).
 */
export function initWaterRipple(container = document.querySelector('[data-ripple]')) {
    if (!container) return false;

    const video = container.querySelector('video');
    if (!video) return false;

    const canvas = document.createElement('canvas');
    canvas.className = 'water-ripple-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    container.appendChild(canvas);

    const gl = canvas.getContext('webgl2', { alpha: false, antialias: false, premultipliedAlpha: false });
    if (!gl || !gl.getExtension('EXT_color_buffer_float')) {
        canvas.remove();

        return false;
    }

    const fluid = program(gl, QUAD_VERTEX, FLUID_FRAGMENT);
    const screen = program(gl, QUAD_VERTEX, SCREEN_FRAGMENT);
    if (!fluid || !screen) {
        canvas.remove();

        return false;
    }

    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);

    const bindQuad = (linked) => {
        const location = gl.getAttribLocation(linked, 'aPos');
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.enableVertexAttribArray(location);
        gl.vertexAttribPointer(location, 2, gl.FLOAT, false, 0, 0);
    };

    const targets = [makeTarget(gl, SIM_SIZE), makeTarget(gl, SIM_SIZE), makeTarget(gl, SIM_SIZE)];
    let pingPong = 0;

    const videoTexture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, videoTexture);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);

    const fluidUniforms = {
        uPrevState: gl.getUniformLocation(fluid, 'uPrevState'),
        uCurrentState: gl.getUniformLocation(fluid, 'uCurrentState'),
        uResolution: gl.getUniformLocation(fluid, 'uResolution'),
        uViscosity: gl.getUniformLocation(fluid, 'uViscosity'),
        uDecay: gl.getUniformLocation(fluid, 'uDecay'),
        uMouse: gl.getUniformLocation(fluid, 'uMouse'),
        uPrevMouse: gl.getUniformLocation(fluid, 'uPrevMouse'),
        uRadius: gl.getUniformLocation(fluid, 'uRadius'),
        uIntensity: gl.getUniformLocation(fluid, 'uIntensity'),
        uMouseVelocity: gl.getUniformLocation(fluid, 'uMouseVelocity'),
    };

    const screenUniforms = {
        uTexture: gl.getUniformLocation(screen, 'uTexture'),
        uDisplacement: gl.getUniformLocation(screen, 'uDisplacement'),
        uDistortionStrength: gl.getUniformLocation(screen, 'uDistortionStrength'),
        uAberration: gl.getUniformLocation(screen, 'uAberration'),
        uLightIntensity: gl.getUniformLocation(screen, 'uLightIntensity'),
        uSpecularPower: gl.getUniformLocation(screen, 'uSpecularPower'),
        uResolution: gl.getUniformLocation(screen, 'uResolution'),
        uImageAspect: gl.getUniformLocation(screen, 'uImageAspect'),
        uPlaneAspect: gl.getUniformLocation(screen, 'uPlaneAspect'),
    };

    const mouse = { x: 0.5, y: 0.5 };
    const prevMouse = { x: 0.5, y: 0.5 };
    let velocity = 0;
    let running = true;
    let drawn = false;

    const onPointer = (event) => {
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        mouse.x = (event.clientX - rect.left) / rect.width;
        mouse.y = 1 - (event.clientY - rect.top) / rect.height;
    };
    container.addEventListener('pointermove', onPointer, { passive: true });
    container.addEventListener('pointerdown', onPointer, { passive: true });

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.max(1, Math.floor(rect.width * dpr));
        canvas.height = Math.max(1, Math.floor(rect.height * dpr));
    };
    window.addEventListener('resize', resize);
    resize();

    const observer = new IntersectionObserver((entries) => {
        running = entries[0]?.isIntersecting ?? true;
    }, { threshold: 0.04 });
    observer.observe(canvas);

    const render = () => {
        if (running) {
            if (!drawn && video.readyState >= 2) {
                container.classList.add('has-ripple');
                drawn = true;
            }

            if (video.readyState >= 2) {
                const current = pingPong;
                const prev = (current + 2) % 3;
                const next = (current + 1) % 3;

                velocity = Math.min(0.025, Math.hypot(mouse.x - prevMouse.x, mouse.y - prevMouse.y));

                // 1. Fluid simulation pass.
                gl.useProgram(fluid);
                bindQuad(fluid);
                gl.bindFramebuffer(gl.FRAMEBUFFER, targets[next].framebuffer);
                gl.viewport(0, 0, SIM_SIZE, SIM_SIZE);
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, targets[prev].texture);
                gl.uniform1i(fluidUniforms.uPrevState, 0);
                gl.activeTexture(gl.TEXTURE1);
                gl.bindTexture(gl.TEXTURE_2D, targets[current].texture);
                gl.uniform1i(fluidUniforms.uCurrentState, 1);
                gl.uniform2f(fluidUniforms.uResolution, SIM_SIZE, SIM_SIZE);
                gl.uniform1f(fluidUniforms.uViscosity, 0.96);
                gl.uniform1f(fluidUniforms.uDecay, 0.975);
                gl.uniform2f(fluidUniforms.uMouse, mouse.x, mouse.y);
                gl.uniform2f(fluidUniforms.uPrevMouse, prevMouse.x, prevMouse.y);
                gl.uniform1f(fluidUniforms.uRadius, 0.045);
                gl.uniform1f(fluidUniforms.uIntensity, 0.28);
                gl.uniform1f(fluidUniforms.uMouseVelocity, velocity);
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

                // 2. Video upload (flip Y so the frame is upright).
                gl.activeTexture(gl.TEXTURE2);
                gl.bindTexture(gl.TEXTURE_2D, videoTexture);
                gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
                gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, video);

                // 3. Composite the displaced film to the canvas.
                gl.useProgram(screen);
                bindQuad(screen);
                gl.bindFramebuffer(gl.FRAMEBUFFER, null);
                gl.viewport(0, 0, canvas.width, canvas.height);
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, videoTexture);
                gl.uniform1i(screenUniforms.uTexture, 0);
                gl.activeTexture(gl.TEXTURE1);
                gl.bindTexture(gl.TEXTURE_2D, targets[next].texture);
                gl.uniform1i(screenUniforms.uDisplacement, 1);
                const imageAspect = (video.videoWidth || 16) / (video.videoHeight || 9);
                gl.uniform1f(screenUniforms.uImageAspect, imageAspect);
                gl.uniform1f(screenUniforms.uPlaneAspect, canvas.width / Math.max(1, canvas.height));
                gl.uniform1f(screenUniforms.uDistortionStrength, 0.018);
                gl.uniform1f(screenUniforms.uAberration, 0.0022);
                gl.uniform1f(screenUniforms.uLightIntensity, 0.7);
                gl.uniform1f(screenUniforms.uSpecularPower, 64.0);
                gl.uniform2f(screenUniforms.uResolution, SIM_SIZE, SIM_SIZE);
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

                pingPong = next;
            }

            prevMouse.x = mouse.x;
            prevMouse.y = mouse.y;
        }

        requestAnimationFrame(render);
    };

    requestAnimationFrame(render);

    return true;
}
