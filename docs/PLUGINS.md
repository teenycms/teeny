# Make your own plugin

A plugin adds a new kind of section to the editor. Plugins can't run code on your server, and any interactive part runs in a sealed sandbox. Three examples are included: Opening hours, Testimonials and Countdown.

## What's in a plugin

A plugin is a folder:

```
my-plugin/
├── plugin.json     name and fields (required)
├── template.html   layout with {{placeholders}} (required)
├── style.css       styles, applied only inside your section (optional)
├── widget.js       interactive code, runs in a sealed sandbox (optional)
├── widget.css      extra styles for the interactive frame (optional)
└── images/         pictures used by the template (optional)
```

## plugin.json

```json
{
  "id": "my-plugin",
  "name": "Team members",
  "description": "Photos and names of your team",
  "version": "1.0",
  "author": "Your name",
  "section": {
    "label": "Team",
    "desc": "Photos and names",
    "fields": {
      "title":  { "type": "text", "label": "Title", "max": 80 },
      "people": { "type": "list", "label": "People", "max": 12, "item": {
        "photo": { "type": "image", "label": "Photo" },
        "name":  { "type": "text",  "label": "Name" },
        "link":  { "type": "url",   "label": "Profile link" }
      }},
      "style":  { "type": "select", "label": "Style", "options": { "round": "Round photos", "square": "Square photos" } }
    },
    "defaults": { "title": "Meet the team", "people": [], "style": "round" }
  }
}
```

- **Field types:** `text`, `textarea`, `rich` (formatted text), `url`, `image`, `select` and `list`. List items can use the first five.
- **Interactive plugins:** add `"widget": { "height": 200 }` and a `widget.js` file.

## template.html

The template has no code, only placeholders:

| Placeholder | Meaning |
|---|---|
| `{{name}}` | Value of a field. Always made safe (escaped) |
| `{{{body}}}` | Formatted HTML. Only works for `rich` fields |
| `{{#people}} … {{/people}}` | Repeat for every item in a list, or show the block only if a field has a value |
| `{{^people}} … {{/people}}` | Show only when the field is empty |
| `{{@index}}` | Position inside a list (1, 2, 3…) |
| `{{@assets}}` | Address of your plugin folder, e.g. `<img src="{{@assets}}/images/logo.png">` |

```html
<section class="team team--{{style}}">
  {{#title}}<h2>{{title}}</h2>{{/title}}
  <ul>{{#people}}<li><img src="{{photo}}" alt="{{name}}"><a href="{{link}}">{{name}}</a></li>{{/people}}</ul>
</section>
```

## style.css

Write normal CSS. Teeny automatically limits every rule to your section, so a plugin can
never restyle the rest of the site. Use `&` (or `:scope`) to style the section box itself. Theme colours
such as `var(--tc-accent)` are available.

## widget.js

`widget.js` runs inside a sealed sandbox:

- It receives your fields as `window.teeny.data`.
- Call `window.teeny.resize()` after changing the layout, so the frame resizes to fit.
- It can draw and animate inside its own frame.
- It **cannot** read cookies or the page around it, contact the Studio or other websites, open pop-ups or redirect the visitor.

See `plugins/countdown/` for a working example.

## Install it

Zip the folder and upload it in **Plugins**, or copy it into `plugins/` by FTP. New plugins
start **switched off**, so an Owner can review them first.

Every uploaded plugin is checked file by file before it goes live. See [SECURITY.md](../SECURITY.md) for what's allowed.
