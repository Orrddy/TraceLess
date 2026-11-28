/**
 * First-launch landing intro.
 *
 * Rules:
 * - The intro is shown only until it has played to the end once on this device.
 * - It cannot be skipped from the UI: no native controls, no close/skip button.
 * - Completion is persisted only on `ended`, so refreshing halfway through
 *   starts it again.
 * - Browsers that block audible autoplay fall back to muted playback and offer
 *   one explicit sound toggle; browsers that block all autoplay get one
 *   "Play intro" button (still not a skip).
 */
const COMPLETE_KEY = 'traceless:intro-complete:v1';

export function initIntroGate() {
    const gate = document.querySelector('[data-intro-gate]');
    if (!gate) return;

    const root = document.documentElement;
    if (!root.classList.contains('intro-gate-pending')) {
        gate.remove();
        return;
    }

    const video = gate.querySelector('[data-intro-video]');
    if (!video) {
        root.classList.remove('intro-gate-pending');
        gate.remove();
        window.dispatchEvent(new Event('traceless:intro-complete'));
        return;
    }

    // Keep page links out of the tab order while the film owns the screen.
    const background = [document.querySelector('header'), document.querySelector('main'), document.querySelector('footer')].filter(Boolean);
    background.forEach((element) => { element.inert = true; });
    gate.tabIndex = -1;
    gate.focus({ preventScroll: true });

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const progress = gate.querySelector('[data-intro-progress]');
    const soundButton = gate.querySelector('[data-intro-sound]');
    const soundLabel = gate.querySelector('[data-intro-sound-label]');
    const playButton = gate.querySelector('[data-intro-play]');
    let settled = false;
    let previousTime = 0;

    root.setAttribute('data-intro-booted', '1');

    const markComplete = () => {
        try {
            window.localStorage.setItem(COMPLETE_KEY, '1');
        } catch (error) {
            // Storage can be unavailable in private modes. The intro still ends
            // cleanly; it may replay on a later visit.
        }
    };

    const updateProgress = () => {
        if (!progress) return;
        const duration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 31.168;
        const ratio = Math.max(0, Math.min(1, video.currentTime / duration));
        progress.style.transform = `scaleX(${ratio})`;
    };

    const updateSound = () => {
        if (!soundButton) return;
        soundButton.hidden = false;
        const muted = video.muted;
        soundButton.setAttribute('aria-pressed', muted ? 'false' : 'true');
        soundButton.setAttribute('aria-label', muted ? 'Turn intro sound on' : 'Turn intro sound off');
        if (soundLabel) soundLabel.textContent = muted ? 'Sound off' : 'Sound on';
    };

    const finishGate = ({ completed }) => {
        if (settled) return;
        settled = true;
        document.removeEventListener('keydown', blockMediaKeys, true);
        video.pause();
        if (completed) markComplete();

        background.forEach((element) => { element.inert = false; });
        root.classList.add('intro-gate-complete');
        window.dispatchEvent(
            new CustomEvent('traceless:intro-complete', { detail: { completed } })
        );

        gate.classList.add('is-leaving');
        const removalDelay = reduced ? 0 : 520;
        window.setTimeout(() => {
            root.classList.remove('intro-gate-pending', 'intro-gate-complete', 'intro-gate-active');
            gate.remove();
        }, removalDelay);
    };

    const blockMediaKeys = (event) => {
        if (event.defaultPrevented || !(event.target instanceof Element)) return;
        if (event.target.closest('button, a, input, textarea, select')) return;
        const blocked = new Set([
            'Space', 'Enter', 'ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown',
            'KeyK', 'KeyJ', 'Home', 'End', 'PageUp', 'PageDown',
        ]);
        if (blocked.has(event.code)) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    const start = async () => {
        video.preload = 'auto';
        root.classList.add('intro-gate-active');
        try {
            // Prefer audible playback when the browser permits it.
            video.muted = false;
            await video.play();
        } catch (error) {
            // Audible autoplay is commonly blocked. Try the permitted muted path.
            try {
                video.muted = true;
                await video.play();
            } catch (mutedError) {
                // Some browsers require a gesture even for muted playback.
                if (playButton) {
                    playButton.hidden = false;
                    playButton.addEventListener(
                        'click',
                        async () => {
                            playButton.hidden = true;
                            try {
                                video.muted = true;
                                await video.play();
                            } catch (finalError) {
                                finishGate({ completed: false });
                            }
                        },
                        { once: true }
                    );
                } else {
                    finishGate({ completed: false });
                }
                return;
            }
        }
        updateSound();
    };

    video.addEventListener('timeupdate', () => {
        if (settled) return;
        // Guard against programmatic seeking; there is no UI for it.
        if (video.currentTime < previousTime - 0.8) {
            video.currentTime = previousTime;
        } else {
            previousTime = video.currentTime;
        }
        updateProgress();
    });
    video.addEventListener('ended', () => finishGate({ completed: true }));
    video.addEventListener('error', () => finishGate({ completed: false }));
    video.addEventListener('stalled', () => gate.classList.add('is-buffering'));
    video.addEventListener('playing', () => gate.classList.remove('is-buffering'));
    video.addEventListener('waiting', () => gate.classList.add('is-buffering'));
    video.addEventListener('contextmenu', (event) => event.preventDefault());
    document.addEventListener('keydown', blockMediaKeys, true);

    if (soundButton) {
        soundButton.hidden = true;
        soundButton.addEventListener('click', async () => {
            video.muted = !video.muted;
            updateSound();
            if (video.paused) {
                try {
                    await video.play();
                } catch (error) {
                    // Leave the gate running; the play fallback handles hard blocks.
                }
            }
        });
    }

    if (playButton) playButton.hidden = true;
    updateProgress();
    start();
}
