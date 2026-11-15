import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/View/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                // Obsidian / graphite world
                obsidian: '#080A0D',
                graphite: '#101419',
                elevated: '#171D24',
                silver: '#A6B0BC',

                // Legacy semantic names, remapped to the dark world so
                // untouched inner views stay legible.
                canvas: '#080A0D',
                'canvas-alt': '#0B0E12',
                mist: '#101419',
                ink: '#F1F4F7',
                charcoal: '#C6CFD9',
                muted: '#A6B0BC',
                hairline: 'rgba(255,255,255,0.09)',
                'electric-blue': '#55D6E8',
                'cyan-glow': '#55D6E8',
                emerald: '#4ADE9B',
                amber: '#E9B45D',
                coral: '#EF6262',
                violet: '#9C8CFF',
                midnight: '#080A0D',
                'deep-navy': '#101419',
            },
            fontFamily: {
                display: ["'Schibsted Grotesk'", "'Inter'", ...defaultTheme.fontFamily.sans],
                // Legacy alias kept so existing `font-editorial` markup
                // renders the new editorial sans, not the old serif.
                editorial: ["'Schibsted Grotesk'", "'Inter'", ...defaultTheme.fontFamily.sans],
                body: ["'Inter'", ...defaultTheme.fontFamily.sans],
                sans: ["'Inter'", ...defaultTheme.fontFamily.sans],
                mono: ["'JetBrains Mono'", ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                glass: '0 30px 80px -40px rgba(0,0,0,0.95), inset 0 1px 0 rgba(255,255,255,0.04)',
                'glass-sm': '0 12px 34px -22px rgba(0,0,0,0.9)',
                'glow-blue': '0 0 0 1px rgba(85,214,232,.25), 0 18px 44px -18px rgba(85,214,232,.38)',
                'glow-cyan': '0 0 0 1px rgba(85,214,232,.25), 0 18px 44px -18px rgba(85,214,232,.38)',
            },
            borderRadius: {
                '4xl': '28px',
                '5xl': '32px',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(14px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'scan-sweep': {
                    '0%': { transform: 'translateY(-120%)' },
                    '100%': { transform: 'translateY(420%)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                'pulse-node': {
                    '0%, 100%': { opacity: '0.55', transform: 'scale(1)' },
                    '50%': { opacity: '1', transform: 'scale(1.35)' },
                },
                'dash-flow': {
                    to: { strokeDashoffset: '-120' },
                },
                'ring-pulse': {
                    '0%': { boxShadow: '0 0 0 0 rgba(85,214,232,.22)' },
                    '70%': { boxShadow: '0 0 0 18px rgba(85,214,232,0)' },
                    '100%': { boxShadow: '0 0 0 0 rgba(85,214,232,0)' },
                },
            },
            animation: {
                'fade-up': 'fade-up .7s cubic-bezier(.22,.8,.24,1) both',
                scan: 'scan-sweep 5s linear infinite',
                shimmer: 'shimmer 2.4s linear infinite',
                node: 'pulse-node 3.2s ease-in-out infinite',
                'dash-flow': 'dash-flow 6s linear infinite',
                'ring-pulse': 'ring-pulse 2.8s ease-out infinite',
            },
        },
    },
    plugins: [forms],
};
