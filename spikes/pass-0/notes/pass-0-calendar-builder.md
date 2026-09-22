# Pass 0 — calendar builder

**Verdict: fails** as an unmodified, coverage-shaded authoring surface. Its day-selection portion fits. Tested `react-fancy` **5.17.1** on 2026-09-22; branch `spike/pass-0`.

[Calendar's registry contract](https://ui.particle.academy/packages/react-fancy/calendar) supports controlled single/multiple/range selection and disabled dates. It has no day renderer, day class/style callback, coverage data, or controlled visible month. Its built-in range shading represents selection, not rule coverage.

The browser spike selected two nonadjacent dates, drag-selected slices **20–29** (02:00–03:00), and bulk-applied bookability to exactly ten of each date's 240 slices. Adjacent slices remained unbookable. A third date received a copy of all 240 source cells. Editing the source afterwards changed its coverage to 11/240 while copied dates stayed 10/240: copying is independent. [Assertions and measurements](../evidence/measurements.json) and [screenshot](../evidence/calendar-builder.png) record this.

Bulk application, copying and coverage calculation are fresh **host-owned** code, not Calendar features. Coverage colours update correctly in an external summary. They cannot be placed on Calendar's day cells through the public API. Thus the screenshot proves the useful subset and the missing integration point; it does not prove native coverage shading.

**Alternative:** extend Fancy Calendar upstream with stable date IDs, `renderDay`/`dayClassName` (including accessible coverage labels), and controlled visible month, then retain host-owned rules and copy semantics. If that extension is unavailable, use a purpose-built month authoring grid, keeping Fancy Calendar for date picking. Couple it to the typed meso surface from the meso spike. Avoid nth-child styling, text matching, or DOM mutation as production integration.

No server persistence, publishing, authorization or conflict semantics were tested by this UI-only spike. The existing backend suite passed independently; app startup remains blocked as recorded in the spike README.
