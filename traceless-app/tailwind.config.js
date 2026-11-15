import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * One semantic palette, two themes.
 *
 * Colours resolve to CSS variables defined in resources/css/app.css, so a view
 * never needs to know which theme is active. `darkMode: 'class'` switches the
 * variables on <html>.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                canvas: 'rgb(var(--c-canvas) / <alpha-value>)',
                surface: 'rgb(var(--c-surface) / <alpha-value>)',
                elevated: 'rgb(var(--c-elevated) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                ink: 'rgb(var(--c-ink) / <alpha-value>)',
                body: 'rgb(var(--c-body) / <alpha-value>)',
                muted: 'rgb(var(--c-muted) / <alpha-value>)',
                faint: 'rgb(var(--c-faint) / <alpha-value>)',
                accent: 'rgb(var(--c-accent) / <alpha-value>)',
                success: 'rgb(var(--c-success) / <alpha-value>)',
                warning: 'rgb(var(--c-warning) / <alpha-value>)',
                danger: 'rgb(var(--c-danger) / <alpha-value>)',

                // Legacy names kept so untouched markup still resolves.
                obsidian: 'rgb(var(--c-canvas) / <alpha-value>)',
                graphite: 'rgb(var(--c-surface) / <alpha-value>)',
                silver: 'rgb(var(--c-muted) / <alpha-value>)',
                charcoal: 'rgb(var(--c-body) / <alpha-value>)',
                mist: 'rgb(var(--c-surface) / <alpha-value>)',
                hairline: 'rgb(var(--c-line) / <alpha-value>)',
                'electric-blue': 'rgb(var(--c-accent) / <alpha-value>)',
                'cyan-glow': 'rgb(var(--c-accent) / <alpha-value>)',
                'canvas-alt': 'rgb(var(--c-surface) / <alpha-value>)',
                emerald: 'rgb(var(--c-success) / <alpha-value>)',
                amber: 'rgb(var(--c-warning) / <alpha-value>)',
                coral: 'rgb(var(--c-danger) / <alpha-value>)',
                violet: 'rgb(var(--c-accent) / <alpha-value>)',
                midnight: 'rgb(var(--c-canvas) / <alpha-value>)',
                'deep-navy': 'rgb(var(--c-surface) / <alpha-value>)',
            },
            fontFamily: {
                sans: ["'Inter'", ...defaultTheme.fontFamily.sans],
                body: ["'Inter'", ...defaultTheme.fontFamily.sans],
                display: ["'Schibsted Grotesk'", "'Inter'", ...defaultTheme.fontFamily.sans],
                mono: ["'JetBrains Mono'", ...defaultTheme.fontFamily.mono],
            },
            borderRadius: {
                card: '12px',
                control: '8px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(16, 24, 40, 0.04), 0 1px 3px rgba(16, 24, 40, 0.06)',
                raised: '0 4px 12px -2px rgba(16, 24, 40, 0.08), 0 2px 6px -2px rgba(16, 24, 40, 0.05)',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0', transform: 'translateY(4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in .25s ease-out both',
            },
        },
    },
    plugins: [forms],
};
