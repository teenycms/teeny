# Make your own theme

A theme sets the colours, fonts and shapes of your whole site. Teeny comes with three (Minimal, Dark and Luxury), and you can add your own.

## The easy way

Go to **Style**, adjust colours, fonts and shapes, press **Save style**, then use
**Your themes → Save my current style as a theme**. Download it as a `.zip` to keep it or share it.

## By hand

A theme is a folder with these files:

```
my-theme/
├── theme.json      name, colours, fonts (required)
├── theme.css       styles (required)
├── fonts/          optional .woff2 / .woff files
└── images/         optional .jpg .png .gif .webp files
```

### theme.json

```json
{
  "id": "my-theme",
  "name": "My Theme",
  "description": "Short description",
  "author": "Your name",
  "dark": false,
  "defaults": {
    "bg": "#ffffff", "surface": "#f5f5f5", "text": "#111111", "muted": "#666666",
    "accent": "#111111", "accentText": "#ffffff", "border": "#e5e5e5",
    "headingFont": "modern", "bodyFont": "modern",
    "radius": 8, "width": 1080, "fontSize": 17
  },
  "alt": { "bg": "#111111", "surface": "#1b1b1b", "text": "#f2f2f2", "muted": "#a0a0a0",
           "accent": "#f2f2f2", "accentText": "#111111", "border": "#2b2b2b" }
}
```

- **`alt`** is the palette for the visitor light/dark switch.
- **Fonts:** pick `headingFont` and `bodyFont` from `classic`, `didone`, `modern`, `humanist`, `geometric` or `mono`. To use your own font, put the file in `fonts/`, add an `@font-face` rule in `theme.css` (`src: url(fonts/my.woff2)`), then set `font-family`.
- **Selectors:** start every rule in `theme.css` with `.theme-my-theme` (your theme id).

### theme.css

The shared layout lives in `assets/site.css`, and all colours come from CSS variables:
`--tc-bg`, `--tc-surface`, `--tc-text`, `--tc-muted`, `--tc-accent`, `--tc-accent-text`, `--tc-border`,
`--tc-font-heading`, `--tc-font-body`, `--tc-radius`, `--tc-wide`, `--tc-font-size`.

## Install it

Zip the folder and upload it in **Style → Your themes**, or copy the folder into `themes/` by FTP.
See `examples/theme-sand/` for a complete example.

Every uploaded theme is checked file by file before it goes live. See [SECURITY.md](../SECURITY.md) for what's allowed.
