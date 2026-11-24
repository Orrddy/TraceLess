/**
 * Tiny server-timed countdowns for dashboard / list cards.
 * Usage: <span data-live-deadline="<unix ms>" data-live-server="<unix ms>">--:--:--</span>
 */
const pad = (n) => String(n).padStart(2, '0');

export function initLiveClocks() {
    document.querySelectorAll('[data-live-deadline]').forEach((el) => {
        const deadline = Number(el.dataset.liveDeadline);
        if (!deadline || !Number.isFinite(deadline)) return;

        const serverNow = Number(el.dataset.liveServer);
        const offset = Number.isFinite(serverNow) ? serverNow - Date.now() : 0;

        const paint = () => {
            const remaining = Math.max(0, deadline - (Date.now() + offset));
            const total = Math.floor(remaining / 1000);
            el.textContent = `${pad(Math.floor(total / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(total % 60)}`;
            if (remaining <= 0) el.textContent = '00:00:00';
        };

        paint();
        setInterval(paint, 1000);
    });
}
