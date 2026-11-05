# WordPress.org assets (NOT shipped in the plugin ZIP)

This directory deploys to the plugin's SVN `assets/` (banners, icon,
screenshots) via the 10up deploy action. It is excluded from the ZIP by
`.distignore` and rejected from the payload by `bin/verify-dist.sh`.

## Inventory (all present)

- `icon.svg` — 256×256 viewBox, simple shapes, no embedded fonts.
- `banner.svg` — textless master artwork (brand gradient + chart motif).
- `banner-772x250.png` + `banner-1544x500.png` — exported from `banner.svg`
  at exact sizes (`inkscape banner.svg -o <file> -w <w> -h <h>`).
- `screenshot-1.png` — block in the editor with search + live preview.
- `screenshot-2.png` — frontend chart (logged-out capture).
- `screenshot-3.png` — Settings screen (providers + cache).

Screenshot captions live in `readme.txt` (`== Screenshots ==`) and must
match these files 1:1 in order.

## Page copy source of truth

`readme.txt` sections (Description, Installation, FAQ, Screenshots,
Changelog, Upgrade Notice, External Services) render the directory page.
Keep marketing claims identical between `readme.txt` and any GitHub docs.
