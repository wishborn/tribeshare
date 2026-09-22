# Pass 0 verification spikes

Throwaway work on `spike/pass-0`; never merge this branch into `main`.
Written fresh against installed Fancy packages; no old prototype code was read or copied.
Synthetic local fixtures only. No application routes or backend behavior changed.

Read the [meso-grid](notes/pass-0-meso-grid.md), [calendar-builder](notes/pass-0-calendar-builder.md), and [theming](notes/pass-0-theming.md) verdicts. Copies are also delivered to the envelope's `.ai/plans/spikes/` directory.

From `C:\_Projects\Tribeshare.agi\repos\tribeshare`:

```powershell
npm ci
npx playwright install chromium
node spikes/pass-0/audit-colors.mjs
npx vite build --config spikes/pass-0/vite.config.ts
node spikes/pass-0/measure.mjs
npx tsc -p spikes/pass-0/tsconfig.json
npm run lint:check
npm run types:check
npm run build
composer test
```

The browser runner loads the production IIFE and compiled Tailwind/Fancy CSS into an isolated Chromium document, exercises real React components with Playwright, saves raw measurements/screenshots, and closes Chromium. It opens no listening socket. Failed assertions exit nonzero; exceeding the exploratory performance budgets is reported as evidence, because failure is a valid spike outcome.

Timing method: warm up each size once, then measure 15 fresh mounts (React commit plus forced layout) and 30 independent cell updates (mousedown/up to two animation frames). Network and script parsing are excluded. The nearest-rank p95 of 15 mounts is the maximum; all samples remain in JSON. CPU throttling is Chrome's synthetic 4x slowdown. It does not certify mobile devices, touch accessibility, paint smoothness while scrolling, or the six-state grid that the component cannot represent. Each mode switch changes only `data-theme`; `data-palette` selects a fixed diagnostic CSS configuration before each three-mode sequence.

Recorded validation: seed succeeded; `composer test` passed Pint, PHPStan, 343 Pest tests / 766 assertions. App and spike builds, app/spike TypeScript, and ESLint passed. Screenshots were inspected. `composer dev` could not be started: Genie process creation returned “Denied by user — the process was not created.” ForceTheQuestion `b75cd15c8b3a19b23a` is pending. No alternate server was started. Application HTTP/queue/Vite runtime verification remains outstanding; component browser measurements above are complete.

Registry discovery used Fancy MCP `list_components`, `search_components`, `get_component` and `install_instructions`, with installed `react-fancy` 5.17.1 source maps/types checked against the Calendar and TimeGrid contracts. Registry names are recorded in `registry.json`. The colour audit covers 10 installed Fancy packages, 373 source-map files and five distributed CSS files. The inventory distinguishes palette token choices, literal colours, inherited shared styles, and class/media-query theme hooks. It does not certify third-party rendering engines or absent packages.
