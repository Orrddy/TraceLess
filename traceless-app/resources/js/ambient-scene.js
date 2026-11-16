/**
 * Lightweight decorative depth for the hero.
 * It intentionally has no product-state dependencies and never touches the
 * existing video element. This is a no-dependency fallback while the WebGL
 * package is unavailable in the current install environment.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function createAmbientScene(canvas) {
    if (reduceMotion.matches || window.innerWidth < 768) {
        return () => {};
    }

    const context = canvas.getContext('2d', { alpha: true });
    if (!context) return () => {};

    const pointer = { x: 0.72, y: 0.42 };
    let animationFrame;
    let width = 0;
    let height = 0;
    let particles = [];

    const buildParticles = () => {
        const count = Math.min(54, Math.max(28, Math.round((width * height) / 30000)));
        particles = Array.from({ length: count }, () => ({
            x: Math.random() * width,
            y: Math.random() * height,
            radius: Math.random() * 1.8 + 0.45,
            speed: Math.random() * 0.18 + 0.04,
            drift: (Math.random() - 0.5) * 0.12,
            phase: Math.random() * Math.PI * 2,
        }));
    };

    const resize = () => {
        const bounds = canvas.getBoundingClientRect();
        const scale = Math.min(window.devicePixelRatio || 1, 1.5);
        width = Math.max(1, Math.floor(bounds.width));
        height = Math.max(1, Math.floor(bounds.height));
        canvas.width = Math.floor(width * scale);
        canvas.height = Math.floor(height * scale);
        context.setTransform(scale, 0, 0, scale, 0, 0);
        buildParticles();
    };

    const draw = (time) => {
        context.clearRect(0, 0, width, height);
        const wave = time * 0.0003;
        const offsetX = (pointer.x - 0.5) * 18;
        const offsetY = (pointer.y - 0.5) * 12;

        particles.forEach((particle, index) => {
            particle.y -= particle.speed;
            particle.x += Math.sin(wave + particle.phase) * particle.drift;
            if (particle.y < -8) {
                particle.y = height + 8;
                particle.x = Math.random() * width;
            }

            const opacity = .12 + ((Math.sin(wave * 3 + particle.phase) + 1) * .07);
            context.beginPath();
            context.arc(particle.x + offsetX, particle.y + offsetY, particle.radius, 0, Math.PI * 2);
            context.fillStyle = `rgba(111, 225, 255, ${opacity})`;
            context.fill();

            for (let next = index + 1; next < particles.length; next += 1) {
                const other = particles[next];
                const dx = particle.x - other.x;
                const dy = particle.y - other.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if (distance > 118) continue;

                context.beginPath();
                context.moveTo(particle.x + offsetX, particle.y + offsetY);
                context.lineTo(other.x + offsetX, other.y + offsetY);
                context.strokeStyle = `rgba(99, 184, 255, ${.045 * (1 - distance / 118)})`;
                context.lineWidth = 1;
                context.stroke();
            }
        });

        animationFrame = window.requestAnimationFrame(draw);
    };

    const move = (event) => {
        const bounds = canvas.getBoundingClientRect();
        pointer.x = Math.min(1, Math.max(0, (event.clientX - bounds.left) / bounds.width));
        pointer.y = Math.min(1, Math.max(0, (event.clientY - bounds.top) / bounds.height));
    };

    resize();
    window.addEventListener('resize', resize, { passive: true });
    window.addEventListener('pointermove', move, { passive: true });
    animationFrame = window.requestAnimationFrame(draw);

    return () => {
        window.cancelAnimationFrame(animationFrame);
        window.removeEventListener('resize', resize);
        window.removeEventListener('pointermove', move);
    };
}

function bootAmbientScenes() {
    document.querySelectorAll('[data-ambient-canvas]').forEach((canvas) => {
        if (canvas.dataset.ambientReady === 'true') return;
        canvas.dataset.ambientReady = 'true';
        createAmbientScene(canvas);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAmbientScenes, { once: true });
} else {
    bootAmbientScenes();
}
