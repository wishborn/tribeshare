# Pass 0 — three-mode theming

**Verdict: fails** for the blanket “change only `data-theme` and every Fancy component follows semantic tokens” claim. Most DOM surfaces can fit after upstream token/selector work. Tested installed packages on 2026-09-22; branch `spike/pass-0`.

Production Tailwind v4 + Fancy CSS browser probes switched only `data-theme` among light/dark/color; no `.dark` class or React props changed. `@custom-variant dark` was explicitly bound to that attribute. Semantic `--color-primary` changed on all three switches, but TimeGrid's on-cell stayed violet. Mapping **palette** `--color-violet-500`, `--color-zinc-*` and `--color-white` changed grid/calendar colours correctly. It did not recolour all Button/Badge shades. Input's dark background remained literal **rgb(30,30,36)** despite token changes. [Computed styles and assertions](../evidence/measurements.json), [dark screenshot](../evidence/theme-mapped-dark.png).

The [complete component/file inventory](../evidence/color-inventory.md) and [line-level evidence](../evidence/color-audit.json) scan **378 files across ten installed packages**, including distributed CSS. Fixed Tailwind palette choices remain token-backed; literal colours do not. Literal owners include:

- Core: Autocomplete, Avatar, ChartBar/Line/Pie/Sparkline, ColorPicker, EmojiSelect, FileBrowser, Input/Textarea/Select/DatePicker, Checkbox/CheckboxGroup/RadioGroup, MoodMeter, OtpInput, Pillbox, PromptInput, ReasonTag, Timeline and TreeNav.
- Distributed CSS additionally supplies literals to TimePicker, Field, Switch, Dropdown, Menu, Popover, ContextMenu, MobileMenu, Modal, Toast, input affixes and ImageViewer's checkerboard. It uses `.dark` selectors that a Tailwind custom variant does not rewrite.
- Other installed packages: AppUpdateAlert; EChart3D and ECharts presets; syntax highlighting; FlowCanvas, Action/Decision/Note/Output/Subgraph/Trigger/Registry nodes and shared flow defaults; Google/Leaflet marker providers. See the inventory for each palette choice, literal value and source line, including inherited helpers and bundled Flow CSS.

ECharts also follows `prefers-color-scheme` or a theme prop and reinitializes on theme changes; it does not observe `data-theme`. Some colours intentionally encode data/file types; that still disproves automatic token coverage.

**Alternative:** upstream semantic colour variables and attribute-aware selectors into the affected DOM components/styles, with complete three-mode token sets. Adapt canvas/map engines through a central theme adapter that reads resolved tokens and updates their APIs; that requires relaxing the “nothing else” constraint, or choosing CSS/SVG-only renderers. Do not apply broad descendant overrides or globally remap every violet/white token as a production substitute for semantic roles. Prototype palette fidelity and system-mode precedence remain Pass 1 work.
