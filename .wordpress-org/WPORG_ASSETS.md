# WordPress.org assets (NOT shipped in the plugin ZIP)

This directory deploys to the plugin's SVN `assets/` (banners, icon,
screenshots) via the 10up deploy action. It is excluded from the ZIP by
`.distignore` and rejected from the payload by `bin/verify-dist.sh`.

## Needed before submission

- `icon.svg` (present) — 256×256 viewBox, simple shapes, no embedded fonts.
- `banner-772x250.png` + `banner-1544x500.png` — same artwork, exact sizes.
- `screenshot-1.png` — block in the editor with search + live preview.
- `screenshot-2.png` — frontend chart with overlay + quote panel.
- `screenshot-3.png` — Settings screen (providers + cache).

Screenshot captions live in `readme.txt` (`== Screenshots ==`) and must
match these files 1:1 in order.

## Page copy source of truth

`readme.txt` sections (Description, Installation, FAQ, Screenshots,
Changelog, Upgrade Notice, External Services) render the directory page.
Keep marketing claims identical between `readme.txt` and any GitHub docs.
