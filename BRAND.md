# TraceLess brand

## The mark

An **eroding trace**: concentric scan rings around a solid record, with the
outermost ring breaking into dots. It says what the product does — reads the
public trace, then removes what should not be there — without a shield, a lock or
a fingerprint, which every privacy product already uses.

- **Core** — the record itself (one solid dot).
- **Inner ring** — what TraceLess can see (cyan).
- **Middle ring** — the public surface (cool white).
- **Outer dots** — the trace dissolving (cyan, fading).

## Files

| File | Use |
| --- | --- |
| `public/brand/traceless-mark.svg` | Primary mark on dark surfaces |
| `public/brand/traceless-mark-mono.svg` | Single-colour mark for light surfaces |
| `public/favicon.svg` | Browser tab icon (mark on dark tile) |
| `public/favicon.ico` | Legacy tab icon, multi-size |
| `public/apple-touch-icon.png` | Home screen icon |

## Lockup

`<x-brand-logo />` renders mark + wordmark. Props: `size` (`sm`, `md`, `lg`),
`href` (defaults to the home route), `tagline` (optional second line).

The wordmark is Space Grotesk, semibold, `tracking-[-0.045em]`. Do not redraw it
as paths, and do not set it in another typeface.

## Colour

| Token | Value | Use |
| --- | --- | --- |
| Canvas | `#05070C` | Tile behind the mark, page background |
| Cyan | `#49D7FF` | Inner ring, outer dots |
| Ink | `#F5F8FC` | Core dot, middle ring, wordmark |

The mark is two-tone only. It never uses gradients, and it is never recoloured to
match a third-party palette.

## Clear space and minimum size

- Clear space on all sides: half the mark's width.
- Minimum size: **24 px** for the full mark; below that use the favicon tile.
- At 16 px the outer dots merge into a ring, which is acceptable; the inner ring
  and core must stay readable.

## Misuse

- No drop shadows, glows or strokes around the mark.
- No rotating, squashing or stretching.
- Do not place the colour mark on a mid-tone background; use the mono mark.
- Do not pair it with a second icon in the same lockup.
