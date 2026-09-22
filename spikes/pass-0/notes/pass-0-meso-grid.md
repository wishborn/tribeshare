# Pass 0 — meso grid

**Verdict: fails** for the required six-state, range-selecting booking grid. Tested `react-fancy` **5.17.1** on 2026-09-22; branch `spike/pass-0`.

[TimeGrid's registry contract](https://ui.particle.academy/packages/react-fancy/time-grid) accepts `boolean[][]`, one global `toneOn`, labels and IDs. There is no per-cell state renderer, tone, disabled flag or range callback. Six distinct domain labels rendered with one background; attaching labels does not implement six visual states.

Measured in production React/Chromium 153, Windows, Ryzen 7 5800X, 1440×1000. Fifteen mounts and 30 updates per configuration, one warmup. p95 milliseconds:

| Days × 240 | CPU slowdown | Mount + layout | Update to two frames |
|---|---:|---:|---:|
| 1 | 1× | 9.1 | 33.3 |
| 7 | 1× | 31.6 | 33.2 |
| 14 | 1× | 52.4 | 32.8 |
| 31 | 1× | 171.3 | 38.1 |
| 7 | 4× | 219.1 | 47.7 |
| 14 | 4× | 425.6 | 70.6 |

Boolean rendering meets the predeclared desktop budgets (200 ms mount, 50 ms update). Throttled mounting fails both sizes; 14-day updates also fail. These are component measurements, not app-load timings or six-state performance claims; see [raw samples and environment](../evidence/measurements.json).

Real mouse drag over slices 0–20 painted 21 cells; jumping directly between those endpoints painted **two**, leaving 19 gaps. It paints visited cells, not a continuous range. Space on a focused cell did not select it. Source uses mouse handlers, has no pointer capture/touch-range implementation, and clones the whole matrix per cell change.

**Alternative:** a purpose-built controlled meso surface with typed cell metadata, independent selection, disabled reasons, keyboard/pointer range semantics, and windowed columns for long spans; or upstream those contracts into TimeGrid first. Re-measure that actual six-state implementation before Band C. Do not decorate private DOM selectors to pretend this component supports it.
