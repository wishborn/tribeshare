# Installed Fancy packages: colour inventory

- fancy-app-update: 0.2.0
- fancy-auto-common: 0.2.0
- fancy-echarts: 6.0.0
- fancy-file-commons: 0.3.0
- fancy-flow: 0.43.0
- fancy-inertia: 0.11.0
- fancy-map: 0.2.0
- fancy-pwa: 0.2.0
- fancy-query: 0.8.0
- react-fancy: 5.17.1

All shipped src files recovered from ESM source maps, plus distributed CSS, deduplicated by original path. Palette utilities are fixed palette choices but remain Tailwind token-backed; literal CSS/SVG values bypass those tokens. CSS custom-property defaults are configurable through their own variables. This inventory includes utilities and auxiliary component files, not only public exports. Evidence JSON includes every matching line, theme selector/media-query hooks and all scanned files with zero hits. Comments are excluded from colour hits; inspect remaining examples/context before treating a hit as rendered UI. Bundled React Flow CSS is included; other third-party engine internals are not. Fancy ECharts follows system preference or an explicit theme prop, while react-fancy CSS still contains :where(.dark) selectors; neither automatically follows data-theme.

| Source | Fixed palette utilities | Literal values |
|---|---|---|
| fancy-app-update/AppUpdateAlert.tsx | — | #7c3aed, rgba(0,0,0,0.18), #ffffff, #18181b, rgba(0,0,0,0.08) |
| fancy-echarts/components/EChart3D.tsx | — | #9ca3af |
| fancy-echarts/utils/themes.ts | — | #1a1a2e, #e0e0e0, #ffffff, #cccccc, #444, #333, #aaa, #5470c6, #91cc75, #fac858, #ee6666, #73c0de, #3ba272, #fc8452, #9a60b4, #ea7ccc, #d87c7c, #919e8b, #d7ab82, #6e7074, #61a0a8, #efa18d, #787464, #cc7e63, #724e58, #4b565b, #fef8ef, #333333, #ccc, #eee, #c4b5fd, #a5f3fc, #fca5a5, #fde68a, #a7f3d0, #fbcfe8, #c7d2fe, #fed7aa, #fafafa, #555 |
| fancy-file-commons/syntax/theme.ts | — | #ffffff, #1e1e2e, #f8fafc, #94a3b8, #e2e8f0, #dbeafe, #3b82f6, #1a7f37, #0969da, #cf222e, #8b5cf6, #059669, #d97706, #0891b2, #2563eb, #dc2626, #64748b, #18181b, #e4e4e7, #52525b, #27272a, #1e3a5f, #60a5fa, #3fb950, #4184e4, #f85149, #c084fc, #34d399, #71717a, #fbbf24, #22d3ee, #f87171, #a1a1aa |
| fancy-flow/components/canvas/FlowCanvas.tsx | — | rgba(0,0,0,0.18) |
| fancy-flow/components/nodes/ActionNode.tsx | — | #3b82f6 |
| fancy-flow/components/nodes/DecisionNode.tsx | — | #f59e0b |
| fancy-flow/components/nodes/NoteNode.tsx | — | rgba(234, 179, 8, 0.14), rgba(234, 179, 8, 0.55), #a16207, rgba(14, 165, 233, 0.12), rgba(14, 165, 233, 0.5), #0369a1, rgba(139, 92, 246, 0.13), rgba(139, 92, 246, 0.5), #6d28d9, rgba(16, 185, 129, 0.13), rgba(16, 185, 129, 0.5), #047857, rgba(244, 63, 94, 0.12), rgba(244, 63, 94, 0.5), #be123c, rgba(100, 116, 139, 0.12), rgba(100, 116, 139, 0.5), #475569 |
| fancy-flow/components/nodes/OutputNode.tsx | — | #a855f7 |
| fancy-flow/components/nodes/SubgraphNode.tsx | — | #0ea5e9 |
| fancy-flow/components/nodes/TriggerNode.tsx | — | #10b981 |
| fancy-flow/dist/index.css | — | #b1b1b7, #555, rgba(255, 255, 255, 0.5), #fff, rgba(240, 240, 240, 0.6), #e2e2e2, #91919a, #eee, #1a192b, rgba(240, 240, 240, 0.25), rgba(0, 0, 0, 0.08), rgba(0, 89, 220, 0.08), rgba(0, 89, 220, 0.8), #fefefe, #f4f4f4, #ffffff, #3367d9, #3e3e3e, #727272, rgba(150, 150, 150, 0.25), #141414, rgba(60, 60, 60, 0.6), #2b2b2b, #777, #f8f8f8, #3c3c3c, #1e1e1e, rgba(255, 255, 255, 0.08), #999, #bebebe, rgba(200, 200, 220, 0.08), rgba(200, 200, 220, 0.8), #5b5b5b |
| fancy-flow/dist/screens.css | — | #b1b1b7, #555, rgba(255, 255, 255, 0.5), #fff, rgba(240, 240, 240, 0.6), #e2e2e2, #91919a, #eee, #1a192b, rgba(240, 240, 240, 0.25), rgba(0, 0, 0, 0.08), rgba(0, 89, 220, 0.08), rgba(0, 89, 220, 0.8), #fefefe, #f4f4f4, #ffffff, #3367d9, #3e3e3e, #727272, rgba(150, 150, 150, 0.25), #141414, rgba(60, 60, 60, 0.6), #2b2b2b, #777, #f8f8f8, #3c3c3c, #1e1e1e, rgba(255, 255, 255, 0.08), #999, #bebebe, rgba(200, 200, 220, 0.08), rgba(200, 200, 220, 0.8), #5b5b5b |
| fancy-flow/dist/styles.css | — | #b1b1b7, #555, rgba(255, 255, 255, 0.5), #fff, rgba(240, 240, 240, 0.6), #e2e2e2, #91919a, #eee, #1a192b, rgba(240, 240, 240, 0.25), rgba(0, 0, 0, 0.08), rgba(0, 89, 220, 0.08), rgba(0, 89, 220, 0.8), #fefefe, #f4f4f4, #ffffff, #3367d9, #3e3e3e, #727272, rgba(150, 150, 150, 0.25), #141414, rgba(60, 60, 60, 0.6), #2b2b2b, #777, #f8f8f8, #3c3c3c, #1e1e1e, rgba(255, 255, 255, 0.08), #999, #bebebe, rgba(200, 200, 220, 0.08), rgba(200, 200, 220, 0.8), #5b5b5b, #f4f4f5, #fafafa, rgba(9, 9, 11, 0.09), #18181b, #52525b, #71717a, #a1a1aa, #e4e4e7, #d4d4d8, #9ca3af, #8b5cf6, rgba(139, 92, 246, 0.12), rgba(139, 92, 246, 0.22), #10b981, #059669, #ef4444, #d97706, rgba(245, 158, 11, 0.15), #7c3aed, rgba(0,0,0,.05), rgba(0,0,0,.04), rgba(0,0,0,.06), rgba(0,0,0,.12), rgba(0,0,0,.08), #1c1c22, #141419, #27272a, #0a0a0c, rgba(255, 255, 255, 0.10), #3f3f46, #a78bfa, rgba(167, 139, 250, 0.16), rgba(167, 139, 250, 0.30), #fb923c, rgba(251, 146, 60, 0.16), #c4b5fd, #34d399, rgba(0,0,0,.4), rgba(0,0,0,.5), rgba(0,0,0,.6), #fbbf24, #fde68a, #f87171, white, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.12), rgba(9, 9, 11, 0.5), #10b98122, #3b82f622, #60a5fa, #ef444422, #71717a22, #a855f722, #c084fc, rgb(0 0 0 / 18%) |
| fancy-flow/registry/registry.ts | — | #10b981, #f59e0b, #0ea5e9, #8b5cf6, #3b82f6, #ec4899, #a855f7, #64748b, #eab308, #71717a |
| fancy-flow/registry/RegistryNode.tsx | — | #71717a |
| fancy-flow/ux.ts | — | #8b5cf6 |
| fancy-map/google.ts | — | #2563eb, #1d4ed8, #ffffff |
| fancy-map/leaflet.ts | — | #2563eb, rgba(0,0,0,.4), #fff, rgba(37,99,235,.5) |
| fancy-pwa/components/UpdateToast.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Accordion/AccordionContent.tsx | text-zinc-600, text-zinc-400 | — |
| react-fancy/components/Accordion/AccordionItem.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Accordion/AccordionTrigger.tsx | text-zinc-600, text-zinc-300 | — |
| react-fancy/components/AccordionPanel/AccordionPanel.Trigger.tsx | text-zinc-500, text-zinc-900, text-zinc-100, bg-zinc-200, bg-zinc-700, bg-zinc-400, bg-zinc-500, text-zinc-400, bg-zinc-100, bg-zinc-800 | — |
| react-fancy/components/AudioViewer/AudioViewer.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, text-zinc-700, text-zinc-200, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Autocomplete/Autocomplete.tsx | text-zinc-900, text-zinc-100, text-zinc-400, text-zinc-500, bg-zinc-100, ring-blue-500/40, bg-zinc-800, border-zinc-200, bg-white, border-blue-500, border-zinc-700, border-blue-400, ring-blue-400/20, bg-zinc-900, shadow-zinc-950/50, text-zinc-700, text-zinc-200, bg-zinc-50, bg-zinc-800/50, text-white | #1e1e24 |
| react-fancy/components/Avatar/Avatar.tsx | bg-green-500, bg-zinc-400, bg-red-500, bg-amber-500, bg-zinc-200, bg-zinc-700, text-zinc-600, text-zinc-300, ring-white, ring-zinc-900 | #a855f7, #22c55e, #f59e0b |
| react-fancy/components/Badge/Badge.colors.ts | bg-slate-600, text-white, bg-slate-500, bg-gray-600, bg-gray-500, bg-zinc-600, bg-zinc-500, bg-neutral-600, bg-neutral-500, bg-stone-600, bg-stone-500, bg-red-600, bg-red-500, bg-orange-600, bg-orange-500, bg-amber-400, text-amber-950, bg-amber-300, bg-yellow-400, text-yellow-950, bg-yellow-300, bg-lime-400, text-lime-950, bg-lime-300, bg-green-600, bg-green-500, bg-emerald-600, bg-emerald-500, bg-teal-600, bg-teal-500, bg-cyan-600, bg-cyan-500, bg-sky-600, bg-sky-500, bg-blue-600, bg-blue-500, bg-indigo-600, bg-indigo-500, bg-violet-600, bg-violet-500, bg-purple-600, bg-purple-500, bg-fuchsia-600, bg-fuchsia-500, bg-pink-600, bg-pink-500, bg-rose-600, bg-rose-500, border-slate-300, text-slate-700, border-slate-600, text-slate-300, border-gray-300, text-gray-700, border-gray-600, text-gray-300, border-zinc-300, text-zinc-700, border-zinc-600, text-zinc-300, border-neutral-300, text-neutral-700, border-neutral-600, text-neutral-300, border-stone-300, text-stone-700, border-stone-600, text-stone-300, border-red-300, text-red-700, border-red-600, text-red-300, border-orange-300, text-orange-700, border-orange-600, text-orange-300, border-amber-300, text-amber-700, border-amber-600, text-amber-300, border-yellow-300, text-yellow-700, border-yellow-600, text-yellow-300, border-lime-300, text-lime-700, border-lime-600, text-lime-300, border-green-300, text-green-700, border-green-600, text-green-300, border-emerald-300, text-emerald-700, border-emerald-600, text-emerald-300, border-teal-300, text-teal-700, border-teal-600, text-teal-300, border-cyan-300, text-cyan-700, border-cyan-600, text-cyan-300, border-sky-300, text-sky-700, border-sky-600, text-sky-300, border-blue-300, text-blue-700, border-blue-600, text-blue-300, border-indigo-300, text-indigo-700, border-indigo-600, text-indigo-300, border-violet-300, text-violet-700, border-violet-600, text-violet-300, border-purple-300, text-purple-700, border-purple-600, text-purple-300, border-fuchsia-300, text-fuchsia-700, border-fuchsia-600, text-fuchsia-300, border-pink-300, text-pink-700, border-pink-600, text-pink-300, border-rose-300, text-rose-700, border-rose-600, text-rose-300, bg-slate-100, bg-slate-900/30, bg-gray-100, bg-gray-900/30, bg-zinc-100, bg-zinc-900/30, bg-neutral-100, bg-neutral-900/30, bg-stone-100, bg-stone-900/30, bg-red-100, bg-red-900/30, bg-orange-100, bg-orange-900/30, bg-amber-100, bg-amber-900/30, bg-yellow-100, bg-yellow-900/30, bg-lime-100, bg-lime-900/30, bg-green-100, bg-green-900/30, bg-emerald-100, bg-emerald-900/30, bg-teal-100, bg-teal-900/30, bg-cyan-100, bg-cyan-900/30, bg-sky-100, bg-sky-900/30, bg-blue-100, bg-blue-900/30, bg-indigo-100, bg-indigo-900/30, bg-violet-100, bg-violet-900/30, bg-purple-100, bg-purple-900/30, bg-fuchsia-100, bg-fuchsia-900/30, bg-pink-100, bg-pink-900/30, bg-rose-100, bg-rose-900/30, bg-amber-500, bg-yellow-500, bg-lime-500 | — |
| react-fancy/components/Brand/Brand.tsx | text-zinc-900, text-zinc-100, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Brand/BrandMark.tsx | text-white, bg-zinc-900, bg-zinc-100, text-zinc-900 | — |
| react-fancy/components/Breadcrumbs/Breadcrumbs.tsx | text-zinc-400, text-zinc-600, text-zinc-300 | — |
| react-fancy/components/Breadcrumbs/BreadcrumbsDropdown.tsx | text-zinc-900, bg-zinc-100, text-white, bg-zinc-800, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, text-zinc-500, bg-zinc-50, text-zinc-700, text-zinc-400, text-zinc-300 | — |
| react-fancy/components/Breadcrumbs/BreadcrumbsItem.tsx | text-zinc-900, text-white, text-zinc-500, text-zinc-700, text-zinc-400, text-zinc-300 | — |
| react-fancy/components/Button/Button.colors.ts | bg-slate-500, text-white, border-slate-600, bg-slate-600, border-slate-500, bg-gray-500, border-gray-600, bg-gray-600, border-gray-500, bg-zinc-500, border-zinc-600, bg-zinc-600, border-zinc-500, bg-neutral-500, border-neutral-600, bg-neutral-600, border-neutral-500, bg-stone-500, border-stone-600, bg-stone-600, border-stone-500, bg-red-500, border-red-600, bg-red-600, border-red-500, bg-orange-500, border-orange-600, bg-orange-600, border-orange-500, bg-amber-400, text-amber-950, border-amber-500, bg-amber-500, bg-amber-300, border-amber-400, bg-yellow-400, text-yellow-950, border-yellow-500, bg-yellow-500, bg-yellow-300, border-yellow-400, bg-lime-400, text-lime-950, border-lime-500, bg-lime-500, bg-lime-300, border-lime-400, bg-green-500, border-green-600, bg-green-600, border-green-500, bg-emerald-500, border-emerald-600, bg-emerald-600, border-emerald-500, bg-teal-500, border-teal-600, bg-teal-600, border-teal-500, bg-cyan-500, border-cyan-600, bg-cyan-600, border-cyan-500, bg-sky-500, border-sky-600, bg-sky-600, border-sky-500, bg-blue-500, border-blue-600, bg-blue-600, border-blue-500, bg-indigo-500, border-indigo-600, bg-indigo-600, border-indigo-500, bg-violet-500, border-violet-600, bg-violet-600, border-violet-500, bg-purple-500, border-purple-600, bg-purple-600, border-purple-500, bg-fuchsia-500, border-fuchsia-600, bg-fuchsia-600, border-fuchsia-500, bg-pink-500, border-pink-600, bg-pink-600, border-pink-500, bg-rose-500, border-rose-600, bg-rose-600, border-rose-500, text-slate-600, bg-slate-50, text-slate-400, bg-slate-950/50, text-gray-600, bg-gray-50, text-gray-400, bg-gray-950/50, text-zinc-600, bg-zinc-50, text-zinc-400, bg-zinc-950/50, text-neutral-600, bg-neutral-50, text-neutral-400, bg-neutral-950/50, text-stone-600, bg-stone-50, text-stone-400, bg-stone-950/50, text-red-600, bg-red-50, text-red-400, bg-red-950/50, text-orange-600, bg-orange-50, text-orange-400, bg-orange-950/50, text-amber-600, bg-amber-50, text-amber-400, bg-amber-950/50, text-yellow-600, bg-yellow-50, text-yellow-400, bg-yellow-950/50, text-lime-600, bg-lime-50, text-lime-400, bg-lime-950/50, text-green-600, bg-green-50, text-green-400, bg-green-950/50, text-emerald-600, bg-emerald-50, text-emerald-400, bg-emerald-950/50, text-teal-600, bg-teal-50, text-teal-400, bg-teal-950/50, text-cyan-600, bg-cyan-50, text-cyan-400, bg-cyan-950/50, text-sky-600, bg-sky-50, text-sky-400, bg-sky-950/50, text-blue-600, bg-blue-50, text-blue-400, bg-blue-950/50, text-indigo-600, bg-indigo-50, text-indigo-400, bg-indigo-950/50, text-violet-600, bg-violet-50, text-violet-400, bg-violet-950/50, text-purple-600, bg-purple-50, text-purple-400, bg-purple-950/50, text-fuchsia-600, bg-fuchsia-50, text-fuchsia-400, bg-fuchsia-950/50, text-pink-600, bg-pink-50, text-pink-400, bg-pink-950/50, text-rose-600, bg-rose-50, text-rose-400, bg-rose-950/50 | — |
| react-fancy/components/Button/Button.tsx | bg-white, text-zinc-700, border-zinc-200, bg-zinc-50, border-zinc-300, bg-zinc-800, text-zinc-300, border-zinc-700, bg-zinc-700, bg-blue-500, text-white, border-blue-600, bg-blue-600, border-blue-500, bg-emerald-500, border-emerald-600, bg-emerald-600, border-emerald-500, bg-amber-50, text-amber-700, border-amber-200, bg-amber-100, bg-amber-900/30, text-amber-300, border-amber-700, bg-amber-900/50, bg-zinc-100, bg-blue-50, text-blue-700, bg-blue-100, bg-blue-950/50, text-blue-300, bg-blue-950, bg-emerald-50, text-emerald-700, bg-emerald-100, bg-emerald-950/50, text-emerald-300, bg-emerald-950, bg-amber-950/50, text-amber-600, text-amber-400, text-zinc-500, text-zinc-400, bg-white/20, bg-amber-200, text-amber-800, bg-amber-800, text-amber-200, bg-zinc-200, bg-zinc-600, text-zinc-200, text-red-500, text-red-400, text-red-300 | — |
| react-fancy/components/Calendar/Calendar.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, text-zinc-400, bg-zinc-100, bg-zinc-800, text-zinc-300, text-zinc-600, text-zinc-700, bg-blue-50, text-blue-700, bg-blue-950, text-blue-300, text-white, text-zinc-900 | — |
| react-fancy/components/Callout/Callout.colors.ts | border-slate-500, bg-slate-50, text-slate-900, border-slate-400, bg-slate-950/50, text-slate-100, border-gray-500, bg-gray-50, text-gray-900, border-gray-400, bg-gray-950/50, text-gray-100, border-zinc-500, bg-zinc-50, text-zinc-900, border-zinc-400, bg-zinc-950/50, text-zinc-100, border-neutral-500, bg-neutral-50, text-neutral-900, border-neutral-400, bg-neutral-950/50, text-neutral-100, border-stone-500, bg-stone-50, text-stone-900, border-stone-400, bg-stone-950/50, text-stone-100, border-red-500, bg-red-50, text-red-900, border-red-400, bg-red-950/50, text-red-100, border-orange-500, bg-orange-50, text-orange-900, border-orange-400, bg-orange-950/50, text-orange-100, border-amber-500, bg-amber-50, text-amber-900, border-amber-400, bg-amber-950/50, text-amber-100, border-yellow-500, bg-yellow-50, text-yellow-900, border-yellow-400, bg-yellow-950/50, text-yellow-100, border-lime-500, bg-lime-50, text-lime-900, border-lime-400, bg-lime-950/50, text-lime-100, border-green-500, bg-green-50, text-green-900, border-green-400, bg-green-950/50, text-green-100, border-emerald-500, bg-emerald-50, text-emerald-900, border-emerald-400, bg-emerald-950/50, text-emerald-100, border-teal-500, bg-teal-50, text-teal-900, border-teal-400, bg-teal-950/50, text-teal-100, border-cyan-500, bg-cyan-50, text-cyan-900, border-cyan-400, bg-cyan-950/50, text-cyan-100, border-sky-500, bg-sky-50, text-sky-900, border-sky-400, bg-sky-950/50, text-sky-100, border-blue-500, bg-blue-50, text-blue-900, border-blue-400, bg-blue-950/50, text-blue-100, border-indigo-500, bg-indigo-50, text-indigo-900, border-indigo-400, bg-indigo-950/50, text-indigo-100, border-violet-500, bg-violet-50, text-violet-900, border-violet-400, bg-violet-950/50, text-violet-100, border-purple-500, bg-purple-50, text-purple-900, border-purple-400, bg-purple-950/50, text-purple-100, border-fuchsia-500, bg-fuchsia-50, text-fuchsia-900, border-fuchsia-400, bg-fuchsia-950/50, text-fuchsia-100, border-pink-500, bg-pink-50, text-pink-900, border-pink-400, bg-pink-950/50, text-pink-100, border-rose-500, bg-rose-50, text-rose-900, border-rose-400, bg-rose-950/50, text-rose-100, text-slate-500, text-slate-400, text-gray-500, text-gray-400, text-zinc-500, text-zinc-400, text-neutral-500, text-neutral-400, text-stone-500, text-stone-400, text-red-500, text-red-400, text-orange-500, text-orange-400, text-amber-500, text-amber-400, text-yellow-500, text-yellow-400, text-lime-500, text-lime-400, text-green-500, text-green-400, text-emerald-500, text-emerald-400, text-teal-500, text-teal-400, text-cyan-500, text-cyan-400, text-sky-500, text-sky-400, text-blue-500, text-blue-400, text-indigo-500, text-indigo-400, text-violet-500, text-violet-400, text-purple-500, text-purple-400, text-fuchsia-500, text-fuchsia-400, text-pink-500, text-pink-400, text-rose-500, text-rose-400 | — |
| react-fancy/components/Card/Card.tsx | border-zinc-200, border-zinc-700, border-zinc-100, border-zinc-800, bg-zinc-50, bg-zinc-800/50, bg-white, bg-zinc-900, border-zinc-300, border-zinc-600 | — |
| react-fancy/components/Card/CardFooter.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Card/CardHeader.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Carousel/CarouselControls.tsx | border-zinc-600, border-blue-500, bg-blue-500, text-white, bg-blue-600 | — |
| react-fancy/components/Carousel/CarouselSteps.tsx | text-blue-600, text-blue-400, text-green-600, text-green-400, text-zinc-400, text-zinc-500, bg-blue-100, text-blue-700, bg-blue-900, text-blue-300, bg-green-100, text-green-700, bg-green-900, text-green-300, bg-zinc-100, bg-zinc-800, bg-white, bg-zinc-300, bg-zinc-600 | — |
| react-fancy/components/Chart/chart.utils.ts | — | #3b82f6, #10b981, #f59e0b, #ef4444, #8b5cf6, #06b6d4, #ec4899, #f97316 |
| react-fancy/components/Chart/ChartAxis.tsx | text-zinc-200, text-zinc-700, text-zinc-300, text-zinc-600, fill-zinc-500, fill-zinc-400 | — |
| react-fancy/components/Chart/ChartBar.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Chart/ChartDonut.tsx | text-zinc-600, text-zinc-400 | — |
| react-fancy/components/Chart/ChartGrid.tsx | text-zinc-100, text-zinc-800 | — |
| react-fancy/components/Chart/ChartHorizontalBar.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Chart/ChartLine.tsx | text-zinc-300, text-zinc-600, text-zinc-500, text-zinc-400 | white |
| react-fancy/components/Chart/ChartPie.tsx | fill-white, text-zinc-600, text-zinc-400 | white |
| react-fancy/components/Chart/ChartSparkline.tsx | — | #3b82f6 |
| react-fancy/components/Chart/ChartStackedBar.tsx | text-zinc-500, border-zinc-200, border-zinc-700, text-zinc-900, text-white, text-zinc-600, text-zinc-400 | — |
| react-fancy/components/Chart/ChartTooltip.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/ChatDrawer/ChatDrawer.tsx | border-violet-500, bg-violet-500, text-white, border-zinc-200, text-zinc-700, border-zinc-300, bg-zinc-50, border-zinc-700, text-zinc-300, border-zinc-600, bg-zinc-800, bg-white/20, bg-zinc-200, text-zinc-600, bg-zinc-300, bg-zinc-700, text-zinc-400, bg-zinc-100, text-zinc-200 | — |
| react-fancy/components/ColorPicker/ColorPicker.tsx | ring-blue-500/40, ring-zinc-300, ring-zinc-600, text-zinc-700, text-zinc-300, ring-zinc-400, ring-zinc-500 | #3b82f6 |
| react-fancy/components/Command/Command.tsx | bg-black/50, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Command/CommandEmpty.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Command/CommandGroup.tsx | text-zinc-400 | — |
| react-fancy/components/Command/CommandInput.tsx | border-zinc-200, border-zinc-700, text-zinc-400 | — |
| react-fancy/components/Command/CommandItem.tsx | text-zinc-700, bg-zinc-100, text-zinc-300, bg-zinc-800 | — |
| react-fancy/components/Composer/Composer.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, text-zinc-400, border-zinc-100, border-zinc-800, text-white, bg-zinc-800, text-zinc-900, bg-zinc-100 | — |
| react-fancy/components/ContextMenu/ContextMenuContent.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/ContextMenu/ContextMenuItem.tsx | text-red-600, bg-red-50, text-red-400, bg-red-950, text-zinc-700, bg-zinc-100, text-zinc-300, bg-zinc-800 | — |
| react-fancy/components/ContextMenu/ContextMenuSeparator.tsx | bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/ContextMenu/ContextMenuSub.tsx | text-zinc-700, bg-zinc-100, text-zinc-300, bg-zinc-800, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Drawer/Drawer.tsx | bg-black/40, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Drawer/DrawerBody.tsx | text-zinc-700, text-zinc-300 | — |
| react-fancy/components/Drawer/DrawerFooter.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Drawer/DrawerHeader.tsx | border-zinc-200, border-zinc-700, text-zinc-900, text-zinc-100, text-zinc-400, bg-zinc-100, text-zinc-600, bg-zinc-800, text-zinc-300 | — |
| react-fancy/components/Dropdown/DropdownItem.tsx | text-red-600, bg-red-50, text-red-400, bg-red-950, text-zinc-700, bg-zinc-100, text-zinc-300, bg-zinc-800 | — |
| react-fancy/components/Dropdown/DropdownItems.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Dropdown/DropdownSeparator.tsx | bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/Editor/Editor.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Editor/editor.utils.ts | text-blue-500, border-zinc-300, text-zinc-600, border-zinc-600, text-zinc-400, bg-zinc-100, bg-zinc-800, bg-zinc-950, text-zinc-100, bg-zinc-50, bg-zinc-800/50 | — |
| react-fancy/components/Editor/EditorContent.tsx | text-zinc-400 | — |
| react-fancy/components/Editor/EditorSourceToggle.tsx | bg-zinc-200, bg-zinc-700, bg-zinc-100, bg-zinc-800 | — |
| react-fancy/components/Editor/EditorToolbar.tsx | border-zinc-200, border-zinc-700, bg-zinc-200, bg-zinc-700, bg-zinc-100, bg-zinc-800 | — |
| react-fancy/components/Editor/EditorToolbarSeparator.tsx | bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/EmojiSelect/EmojiSelect.tsx | border-zinc-300, border-zinc-700, text-zinc-400, border-zinc-200, bg-white, bg-zinc-800, text-zinc-100, border-zinc-100, bg-zinc-100, bg-zinc-700, text-zinc-500, bg-amber-400 | #1e1e24 |
| react-fancy/components/Eyebrow/Eyebrow.tsx | text-zinc-500, text-zinc-400, border-zinc-200, border-zinc-800, text-zinc-900, text-zinc-100 | — |
| react-fancy/components/FauxClient/FauxClient.tsx | bg-rose-300, bg-amber-300, bg-green-300, bg-white, bg-zinc-900, border-zinc-200, border-zinc-800, border-zinc-700, bg-zinc-50, text-zinc-500, bg-zinc-950, text-zinc-400 | — |
| react-fancy/components/FileBrowser/FileBrowser.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/FileBrowser/FileBrowserNode.tsx | text-red-600, text-red-400, text-zinc-500, bg-zinc-100, text-zinc-700, text-zinc-400, bg-zinc-800, text-zinc-200, bg-blue-500/15, text-blue-600, text-blue-400, text-zinc-300, ring-blue-500/40, text-zinc-600 | #3178c6, #f7df1e, #777bb4, #e34c26, #264de4, #a1a1aa, #71717a, #cb171e, #fbbf24 |
| react-fancy/components/FileBrowser/FileBrowserPathBar.tsx | text-zinc-500, text-zinc-700, text-zinc-400, text-zinc-300, text-zinc-900, text-white, border-zinc-200, border-zinc-700, border-zinc-300, border-blue-400, border-zinc-600, bg-zinc-100, text-zinc-600, bg-zinc-800 | — |
| react-fancy/components/FileBrowser/FileBrowserToolbar.tsx | border-zinc-200, border-zinc-700, text-zinc-400, text-zinc-700, text-zinc-300, text-zinc-600, bg-zinc-100, text-zinc-900, bg-zinc-800, text-white, text-zinc-500 | — |
| react-fancy/components/FileUpload/FileUploadDropzone.tsx | border-blue-400, bg-blue-50, border-blue-500, bg-blue-950, border-zinc-300, border-zinc-400, border-zinc-700, border-zinc-500, text-zinc-400, text-zinc-500 | — |
| react-fancy/components/FileUpload/FileUploadList.tsx | border-zinc-200, bg-zinc-50, border-zinc-700, bg-zinc-800, text-zinc-400, text-zinc-500, bg-black/60, text-white, bg-black/80, text-zinc-600, text-zinc-300 | — |
| react-fancy/components/Heading/Heading.tsx | text-zinc-900, text-zinc-100 | — |
| react-fancy/components/ImageViewer/ImageViewer.tsx | bg-zinc-100, bg-zinc-900, text-zinc-500, text-zinc-400, border-zinc-200, bg-white/90, text-zinc-700, border-zinc-700, bg-zinc-900/90, text-zinc-200, bg-zinc-800 | — |
| react-fancy/components/IndexList/IndexList.tsx | border-zinc-200, border-zinc-800, text-zinc-400, text-zinc-500, text-zinc-900, text-zinc-100 | — |
| react-fancy/components/inputs/Checkbox/Checkbox.tsx | text-zinc-700, text-zinc-200, text-zinc-900, ring-blue-500/40, text-white, border-zinc-300, bg-white, text-blue-600, border-zinc-700, border-red-500, text-zinc-100, text-red-500, text-zinc-500, text-zinc-400 | #1e1e24 |
| react-fancy/components/inputs/CheckboxGroup/CheckboxGroup.tsx | border-zinc-300, bg-white, text-blue-600, ring-blue-500/40, border-zinc-700, border-red-500, text-zinc-700, text-zinc-100, text-zinc-500, text-zinc-400 | #1e1e24 |
| react-fancy/components/inputs/DatePicker/DatePicker.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/inputs/Field/Field.tsx | text-zinc-700, text-zinc-100, text-red-500, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/inputs/Input/Input.tsx | text-zinc-400, text-zinc-500, text-zinc-600, ring-blue-500/40, text-zinc-300 | — |
| react-fancy/components/inputs/inputs.utils.ts | ring-amber-400/50, border-red-500, ring-red-500, border-zinc-300, bg-white, text-zinc-900, text-zinc-400, ring-blue-500/40, border-blue-500, border-zinc-700, text-zinc-100, text-zinc-500, border-blue-400, ring-blue-400/20 | #1e1e24 |
| react-fancy/components/inputs/InputWrapper.tsx | border-zinc-300, bg-zinc-50, text-zinc-500, border-zinc-700, bg-zinc-800, text-zinc-400 | — |
| react-fancy/components/inputs/mode/DisplayValue.tsx | text-zinc-900, text-zinc-100, bg-zinc-100, ring-blue-500/40, bg-zinc-800, text-zinc-400, text-zinc-500 | — |
| react-fancy/components/inputs/MultiSwitch/MultiSwitch.tsx | border-zinc-300, bg-zinc-100, border-zinc-700, bg-zinc-800, ring-amber-400/50, ring-red-500/50, bg-blue-600, text-white, text-zinc-600, text-zinc-900, text-zinc-400, text-zinc-100 | — |
| react-fancy/components/inputs/RadioGroup/RadioGroup.tsx | border-zinc-300, bg-white, text-blue-600, ring-blue-500/40, border-zinc-700, border-red-500, text-zinc-700, text-zinc-100, text-zinc-500, text-zinc-400 | #1e1e24 |
| react-fancy/components/inputs/Select/Select.tsx | text-zinc-400, text-zinc-500, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, shadow-zinc-950/50, bg-zinc-100, text-zinc-900, bg-zinc-800, text-zinc-100, text-blue-600, bg-blue-50, text-blue-400, bg-blue-500/10, bg-zinc-50, bg-zinc-800/50, text-zinc-700, text-zinc-300, border-blue-500, bg-blue-500, text-white, border-blue-400, bg-blue-400, border-zinc-300, border-zinc-600, text-blue-500 | — |
| react-fancy/components/inputs/Slider/Slider.tsx | accent-blue-600, text-zinc-700, text-zinc-300, bg-zinc-200, bg-zinc-700, bg-blue-500, bg-blue-600, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/inputs/Switch/Switch.colors.ts | bg-slate-500, bg-gray-500, bg-zinc-500, bg-neutral-500, bg-stone-500, bg-red-500, bg-orange-500, bg-amber-500, bg-yellow-500, bg-lime-500, bg-green-500, bg-emerald-500, bg-teal-500, bg-cyan-500, bg-sky-500, bg-blue-500, bg-indigo-500, bg-violet-500, bg-purple-500, bg-fuchsia-500, bg-pink-500, bg-rose-500 | — |
| react-fancy/components/inputs/Switch/Switch.tsx | text-zinc-700, text-zinc-200, text-zinc-900, ring-blue-500/40, text-white, bg-zinc-200, bg-zinc-600, ring-red-500/50, bg-white, text-zinc-100, text-red-500, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/InputTag/InputTag.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, border-zinc-100, bg-zinc-50, text-zinc-500, border-zinc-800, bg-zinc-950, text-zinc-400, bg-violet-100, bg-violet-900/30, bg-zinc-800 | — |
| react-fancy/components/JsonEditor/JsonEditor.tsx | border-zinc-200, bg-white, text-zinc-900, border-zinc-700, bg-zinc-900, text-zinc-100, border-zinc-100, border-zinc-800 | — |
| react-fancy/components/JsonEditor/JsonEditorRow.tsx | border-zinc-100, border-zinc-800, bg-red-50/60, bg-red-950/20, bg-violet-50/60, bg-violet-950/20 | — |
| react-fancy/components/Kanban/KanbanCard.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Kanban/KanbanColumn.tsx | bg-zinc-50, bg-zinc-800/50, ring-blue-400, text-zinc-600, text-zinc-400, bg-red-100, text-red-700, bg-red-900/40, text-red-300, bg-zinc-200, text-zinc-700, bg-zinc-700, text-zinc-300, bg-blue-500/80 | — |
| react-fancy/components/Kbd/Kbd.tsx | border-zinc-300, bg-zinc-50, text-zinc-700, border-zinc-700, bg-zinc-800, text-zinc-300, text-zinc-400, text-zinc-500 | — |
| react-fancy/components/MagicWand/MagicWand.tsx | border-violet-300, bg-white, ring-violet-200, border-violet-700, bg-zinc-900, ring-violet-900, text-violet-700, text-violet-300, bg-zinc-100, text-zinc-500, bg-zinc-800 | — |
| react-fancy/components/MediaViewer/MediaFallback.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, text-zinc-700, text-zinc-200, text-zinc-500, text-zinc-400, text-blue-600, text-blue-400 | — |
| react-fancy/components/Menu/MenuGroup.tsx | text-zinc-400, text-zinc-500 | — |
| react-fancy/components/Menu/MenuItem.tsx | ring-blue-500, bg-zinc-100, text-zinc-900, bg-zinc-800, text-zinc-100, text-zinc-600, bg-zinc-50, text-zinc-400, bg-zinc-800/50 | — |
| react-fancy/components/Menu/MenuSubmenu.tsx | text-zinc-600, bg-zinc-50, text-zinc-900, text-zinc-400, bg-zinc-800/50, text-zinc-100, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/MobileMenu/MobileMenuBottomBar.tsx | border-zinc-200, bg-white/95, border-zinc-700, bg-zinc-900/95 | — |
| react-fancy/components/MobileMenu/MobileMenuFlyout.tsx | bg-black/40, bg-white, bg-zinc-900, border-zinc-200, border-zinc-700, text-zinc-900, text-zinc-100, text-zinc-400, bg-zinc-100, text-zinc-600, bg-zinc-800, text-zinc-300 | — |
| react-fancy/components/MobileMenu/MobileMenuItem.tsx | ring-blue-500, text-blue-600, text-blue-400, text-zinc-500, text-zinc-900, text-zinc-400, text-zinc-100, bg-zinc-100, bg-zinc-800, text-zinc-600, bg-zinc-50, bg-zinc-800/50 | — |
| react-fancy/components/Modal/Modal.tsx | bg-black/50, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Modal/ModalBody.tsx | text-zinc-700, text-zinc-300 | — |
| react-fancy/components/Modal/ModalFooter.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Modal/ModalHeader.tsx | border-zinc-200, border-zinc-700, text-zinc-900, text-zinc-100, text-zinc-400, bg-zinc-100, text-zinc-600, bg-zinc-800, text-zinc-300 | — |
| react-fancy/components/MoodMeter/MoodMeter.tsx | border-zinc-200, bg-zinc-50, border-zinc-800, bg-zinc-950, text-zinc-400, ring-white, ring-zinc-900 | #0ea5e9, #a855f7, #a1a1aa |
| react-fancy/components/Navbar/Navbar.tsx | border-zinc-200, bg-white, border-zinc-800, bg-zinc-900 | — |
| react-fancy/components/Navbar/NavbarItem.tsx | bg-zinc-100, text-zinc-900, bg-zinc-800, text-white, text-zinc-600, bg-zinc-50, text-zinc-400 | — |
| react-fancy/components/Navbar/NavbarItems.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Navbar/NavbarToggle.tsx | text-zinc-500, bg-zinc-100, bg-zinc-800 | — |
| react-fancy/components/OtpInput/OtpInput.tsx | text-zinc-900, text-zinc-100, ring-blue-500/40, border-zinc-200, bg-white, border-blue-500, border-zinc-700, border-blue-400, ring-blue-400/20 | #1e1e24 |
| react-fancy/components/Pagination/Pagination.tsx | border-zinc-200, bg-white, bg-zinc-50, border-zinc-700, bg-zinc-900, bg-zinc-800, border-zinc-900, text-white, border-white, text-zinc-900, text-zinc-400 | — |
| react-fancy/components/PdfViewer/PdfViewer.tsx | bg-zinc-100, bg-zinc-900, text-zinc-500, text-zinc-400, text-blue-600, text-blue-400 | — |
| react-fancy/components/Pillbox/Pillbox.tsx | border-zinc-200, bg-white, border-blue-500, ring-blue-500/40, border-zinc-700, text-zinc-100, border-blue-400, ring-blue-400/20, bg-zinc-100, bg-zinc-800, text-zinc-400, text-zinc-600, text-zinc-300 | #1e1e24 |
| react-fancy/components/Popover/PopoverContent.tsx | border-zinc-200, bg-white, text-zinc-700, border-zinc-700, bg-zinc-900, text-zinc-200, shadow-zinc-950/50 | — |
| react-fancy/components/Profile/Profile.tsx | text-zinc-900, text-zinc-100, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Progress/Progress.colors.ts | bg-slate-500, bg-slate-400, bg-gray-500, bg-gray-400, bg-zinc-500, bg-zinc-400, bg-neutral-500, bg-neutral-400, bg-stone-500, bg-stone-400, bg-red-500, bg-red-400, bg-orange-500, bg-orange-400, bg-amber-500, bg-amber-400, bg-yellow-500, bg-yellow-400, bg-lime-500, bg-lime-400, bg-green-500, bg-green-400, bg-emerald-500, bg-emerald-400, bg-teal-500, bg-teal-400, bg-cyan-500, bg-cyan-400, bg-sky-500, bg-sky-400, bg-blue-500, bg-blue-400, bg-indigo-500, bg-indigo-400, bg-violet-500, bg-violet-400, bg-purple-500, bg-purple-400, bg-fuchsia-500, bg-fuchsia-400, bg-pink-500, bg-pink-400, bg-rose-500, bg-rose-400, stroke-slate-500, stroke-slate-400, stroke-gray-500, stroke-gray-400, stroke-zinc-500, stroke-zinc-400, stroke-neutral-500, stroke-neutral-400, stroke-stone-500, stroke-stone-400, stroke-red-500, stroke-red-400, stroke-orange-500, stroke-orange-400, stroke-amber-500, stroke-amber-400, stroke-yellow-500, stroke-yellow-400, stroke-lime-500, stroke-lime-400, stroke-green-500, stroke-green-400, stroke-emerald-500, stroke-emerald-400, stroke-teal-500, stroke-teal-400, stroke-cyan-500, stroke-cyan-400, stroke-sky-500, stroke-sky-400, stroke-blue-500, stroke-blue-400, stroke-indigo-500, stroke-indigo-400, stroke-violet-500, stroke-violet-400, stroke-purple-500, stroke-purple-400, stroke-fuchsia-500, stroke-fuchsia-400, stroke-pink-500, stroke-pink-400, stroke-rose-500, stroke-rose-400, text-slate-600, text-slate-400, text-gray-600, text-gray-400, text-zinc-600, text-zinc-400, text-neutral-600, text-neutral-400, text-stone-600, text-stone-400, text-red-600, text-red-400, text-orange-600, text-orange-400, text-amber-600, text-amber-400, text-yellow-600, text-yellow-400, text-lime-600, text-lime-400, text-green-600, text-green-400, text-emerald-600, text-emerald-400, text-teal-600, text-teal-400, text-cyan-600, text-cyan-400, text-sky-600, text-sky-400, text-blue-600, text-blue-400, text-indigo-600, text-indigo-400, text-violet-600, text-violet-400, text-purple-600, text-purple-400, text-fuchsia-600, text-fuchsia-400, text-pink-600, text-pink-400, text-rose-600, text-rose-400 | — |
| react-fancy/components/Progress/Progress.tsx | stroke-zinc-200, stroke-zinc-700, bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/PromptInput/PromptInput.tsx | border-violet-400, bg-violet-50/50, border-violet-600, bg-violet-950/30, border-zinc-200, border-zinc-800, bg-white, bg-zinc-900, bg-zinc-100, bg-zinc-800, text-zinc-400, border-zinc-700, border-zinc-100, bg-zinc-50, text-zinc-500, bg-zinc-950, bg-violet-100, bg-violet-900/30, text-violet-700, text-violet-300, bg-zinc-50/60, bg-zinc-900/40, bg-zinc-200, bg-zinc-700, border-zinc-300, text-zinc-700, text-zinc-300 | #a855f7, #10b981, #3b82f6, #f59e0b, #ef4444, #71717a |
| react-fancy/components/PullQuote/PullQuote.tsx | text-zinc-900, text-zinc-100, border-zinc-200, border-zinc-800, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/ReasonTag/ReasonTag.tsx | text-zinc-600, text-zinc-300, text-zinc-400, text-zinc-700, text-zinc-200, text-violet-600 | #10b981, #f59e0b, #ef4444 |
| react-fancy/components/Section/Section.tsx | border-zinc-200, border-zinc-800 | — |
| react-fancy/components/Separator/Separator.tsx | border-zinc-200, border-zinc-700, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Sidebar/Sidebar.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900 | — |
| react-fancy/components/Sidebar/SidebarGroup.tsx | text-zinc-400, text-zinc-500, bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/Sidebar/SidebarItem.tsx | ring-blue-500, bg-zinc-100, text-zinc-900, bg-zinc-800, text-zinc-100, text-zinc-600, bg-zinc-50, text-zinc-400, bg-zinc-800/50 | — |
| react-fancy/components/Sidebar/SidebarSubmenu.tsx | text-zinc-600, bg-zinc-50, text-zinc-900, text-zinc-400, bg-zinc-800/50, text-zinc-100, border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Sidebar/SidebarToggle.tsx | text-zinc-400, bg-zinc-100, text-zinc-600, bg-zinc-800, text-zinc-300 | — |
| react-fancy/components/Skeleton/Skeleton.tsx | bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/Stat/Stat.tsx | text-zinc-900, text-zinc-100, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/StatList/StatList.tsx | text-zinc-500, text-zinc-400, text-zinc-900, text-zinc-100 | — |
| react-fancy/components/StickyNote/StickyNote.tsx | bg-amber-100, text-amber-950, bg-amber-200, bg-sky-100, text-sky-950, bg-sky-200, bg-emerald-100, text-emerald-950, bg-emerald-200, bg-pink-100, text-pink-950, bg-pink-200, bg-violet-100, text-violet-950, bg-violet-200, text-zinc-900, ring-blue-500 | — |
| react-fancy/components/Table/Table.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Table/TableBody.tsx | divide-zinc-100, divide-zinc-800 | — |
| react-fancy/components/Table/TableCell.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Table/TableColumn.tsx | text-zinc-500, text-zinc-400 | — |
| react-fancy/components/Table/TableHead.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Table/TablePagination.tsx | text-zinc-500, border-zinc-600 | — |
| react-fancy/components/Table/TableRow.tsx | text-zinc-400, text-zinc-700, text-zinc-500, text-zinc-300, bg-zinc-50, bg-zinc-800/50, bg-zinc-50/50, bg-zinc-800/30 | — |
| react-fancy/components/Table/TableRowTray.tsx | border-zinc-200, border-zinc-700 | — |
| react-fancy/components/Table/TableSearch.tsx | border-zinc-200, border-zinc-600, bg-zinc-800 | — |
| react-fancy/components/Tabs/TabsList.tsx | border-zinc-200, border-zinc-700, bg-zinc-100, bg-zinc-800 | — |
| react-fancy/components/Tabs/TabsTab.tsx | border-zinc-900, text-zinc-900, border-white, text-white, text-zinc-500, border-zinc-300, text-zinc-700, text-zinc-400, text-zinc-300, bg-white, bg-zinc-900 | — |
| react-fancy/components/Text/Text.tsx | text-zinc-900, text-zinc-100, text-zinc-500, text-zinc-400, text-blue-600, text-blue-400, text-red-600, text-red-400, text-emerald-600, text-emerald-400 | — |
| react-fancy/components/TimeGrid/TimeGrid.tsx | bg-violet-500, bg-violet-600, bg-emerald-500, bg-emerald-600, bg-sky-500, bg-sky-600, bg-rose-500, bg-rose-600, bg-amber-500, bg-amber-600, bg-indigo-500, bg-indigo-600, bg-blue-500, bg-blue-600, bg-zinc-500, bg-zinc-600, bg-zinc-100, bg-zinc-200, bg-zinc-800, bg-zinc-700, text-zinc-400, text-zinc-700, text-zinc-200, text-zinc-500, text-zinc-800 | — |
| react-fancy/components/Timeline/Timeline.tsx | text-zinc-900, text-white, text-zinc-600, text-zinc-400, text-zinc-500, bg-zinc-200, bg-zinc-700 | rgb(161 161 170) |
| react-fancy/components/Timeline/TimelineBlock.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, ring-blue-500/20, ring-blue-400/20, text-zinc-900, text-zinc-100, text-zinc-600, text-zinc-400 | — |
| react-fancy/components/Timeline/TimelineItem.tsx | bg-slate-400, bg-slate-600, bg-gray-400, bg-gray-600, bg-zinc-300, bg-zinc-600, bg-neutral-400, bg-neutral-600, bg-stone-400, bg-stone-600, bg-red-500, bg-orange-500, bg-amber-500, bg-yellow-500, bg-lime-500, bg-green-500, bg-emerald-500, bg-teal-500, bg-cyan-500, bg-sky-500, bg-blue-500, bg-indigo-500, bg-violet-500, bg-purple-500, bg-fuchsia-500, bg-pink-500, bg-rose-500, ring-slate-400/30, ring-gray-400/30, ring-zinc-400/30, ring-zinc-500/30, ring-neutral-400/30, ring-stone-400/30, ring-red-500/30, ring-orange-500/30, ring-amber-500/30, ring-yellow-500/30, ring-lime-500/30, ring-green-500/30, ring-emerald-500/30, ring-teal-500/30, ring-cyan-500/30, ring-sky-500/30, ring-blue-500/30, ring-indigo-500/30, ring-violet-500/30, ring-purple-500/30, ring-fuchsia-500/30, ring-pink-500/30, ring-rose-500/30, border-zinc-200, bg-zinc-100, border-zinc-700, bg-zinc-800, text-white, bg-zinc-200, bg-zinc-700, text-zinc-500, text-zinc-400 | — |
| react-fancy/components/TimePicker/TimePicker.tsx | text-zinc-900, text-zinc-100, bg-zinc-100, ring-blue-500/40, bg-zinc-800, text-zinc-400, text-zinc-600, text-zinc-300, border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, bg-zinc-200, bg-zinc-700 | — |
| react-fancy/components/Toast/ToastItem.tsx | border-zinc-200, bg-white, border-zinc-700, bg-zinc-900, border-emerald-200, bg-emerald-50, border-emerald-800, bg-emerald-950, border-red-200, bg-red-50, border-red-800, bg-red-950, border-amber-200, bg-amber-50, border-amber-800, bg-amber-950, border-blue-200, bg-blue-50, border-blue-800, bg-blue-950, text-zinc-400, text-emerald-500, text-red-500, text-amber-500, text-blue-500, text-zinc-900, text-zinc-100, text-zinc-500, text-zinc-700, bg-zinc-100, text-zinc-200, bg-zinc-800, text-zinc-600, text-zinc-300 | — |
| react-fancy/components/Tooltip/Tooltip.tsx | bg-zinc-900, text-white, bg-zinc-700, text-zinc-100 | — |
| react-fancy/components/TreeNav/TreeNode.tsx | bg-blue-500, bg-blue-500/15, text-blue-600, text-blue-400, text-zinc-700, bg-zinc-100, text-zinc-300, bg-zinc-800, bg-blue-500/10, ring-blue-500/30 | #3178c6, #f7df1e, #777bb4, #e34c26, #264de4, #a1a1aa, #71717a, #cb171e, #fbbf24 |
| react-fancy/components/VideoViewer/VideoViewer.tsx | bg-black, text-zinc-400 | — |
| react-fancy/dist/styles.css | — | #1e1e24, #3f3f46, #60a5fa, rgba(96, 165, 250, 0.15), #f4f4f5, #27272a, #18181b, #d4d4d8, #ffffff, #e5e7eb, #a855f7 |

## No direct colour hits

- fancy-app-update/useAppUpdate.ts
- fancy-auto-common/activity.ts
- fancy-auto-common/effects.ts
- fancy-auto-common/react.ts
- fancy-auto-common/undo.ts
- fancy-echarts/components/EChart.tsx
- fancy-echarts/components/EChartGraphic.tsx
- fancy-echarts/hooks/use-echarts.ts
- fancy-echarts/hooks/use-resize-observer.ts
- fancy-echarts/utils/register.ts
- fancy-file-commons/diff/annotations.ts
- fancy-file-commons/diff/engine.ts
- fancy-file-commons/diff/hash.ts
- fancy-file-commons/diff/lcs.ts
- fancy-file-commons/diff/merge.ts
- fancy-file-commons/diff/segments.ts
- fancy-file-commons/diff/source.ts
- fancy-file-commons/diff/types.ts
- fancy-file-commons/diff/unified.ts
- fancy-file-commons/language.ts
- fancy-file-commons/path.ts
- fancy-file-commons/syntax/highlight.ts
- fancy-file-commons/syntax/html.ts
- fancy-file-commons/syntax/tokenizer.ts
- fancy-flow/components/canvas/helper-lines.ts
- fancy-flow/components/canvas/HelperLines.tsx
- fancy-flow/components/canvas/use-resolved-color-mode.ts
- fancy-flow/components/FlowEditor/api.ts
- fancy-flow/components/FlowEditor/FlowEditor.tsx
- fancy-flow/components/FlowEditor/graph-ops.ts
- fancy-flow/components/FlowEditor/HumanPrompt.tsx
- fancy-flow/components/FlowRunControls.tsx
- fancy-flow/components/FlowRunFeed.tsx
- fancy-flow/components/FlowViewer/FlowViewer.tsx
- fancy-flow/components/NodeConfigPanel/ConfigFieldRenderer.tsx
- fancy-flow/components/NodeConfigPanel/ExpressionField.tsx
- fancy-flow/components/NodeConfigPanel/NodeConfigPanel.tsx
- fancy-flow/components/NodePalette/NodePalette.tsx
- fancy-flow/components/nodes/api.tsx
- fancy-flow/components/nodes/index.ts
- fancy-flow/components/nodes/LaneNode.tsx
- fancy-flow/components/nodes/NodeShell.tsx
- fancy-flow/expressions/expr.ts
- fancy-flow/expressions/variables.ts
- fancy-flow/fields/react-fancy.tsx
- fancy-flow/index.ts
- fancy-flow/layout/index.ts
- fancy-flow/live.ts
- fancy-flow/llm/prism.ts
- fancy-flow/llm/vercel-ai.ts
- fancy-flow/marketplace/fixtures.ts
- fancy-flow/marketplace/manifest.ts
- fancy-flow/registry/builtin.ts
- fancy-flow/registry/capabilities.ts
- fancy-flow/registry/connection.ts
- fancy-flow/registry/index.ts
- fancy-flow/registry/llm-router.ts
- fancy-flow/registry/pause.ts
- fancy-flow/registry/ports.ts
- fancy-flow/registry/rich-input.tsx
- fancy-flow/registry/subflow.ts
- fancy-flow/rich-input.tsx
- fancy-flow/runtime/history.ts
- fancy-flow/runtime/run-cohort.ts
- fancy-flow/runtime/run-flow.ts
- fancy-flow/runtime/use-flow-history.ts
- fancy-flow/runtime/use-flow-run.ts
- fancy-flow/runtime/use-flow-state.ts
- fancy-flow/schema/workflow-schema.ts
- fancy-flow/screens.ts
- fancy-flow/shims/use-sync-external-store-with-selector.ts
- fancy-inertia/AppUpdateAlert.tsx
- fancy-inertia/buildFancyAppTree.tsx
- fancy-inertia/FancyAppRoot.tsx
- fancy-inertia/FancyClientOnly.tsx
- fancy-inertia/InertiaSchemaScreen.tsx
- fancy-inertia/PageTransition.tsx
- fancy-inertia/pwa/FancyInertiaPwa.tsx
- fancy-inertia/pwa/online.ts
- fancy-inertia/pwa/useOfflineGuard.ts
- fancy-inertia/pwa/usePwaUpdate.ts
- fancy-inertia/registerFancyComponents.ts
- fancy-inertia/seo/context.tsx
- fancy-inertia/seo/json-ld.ts
- fancy-inertia/seo/Seo.tsx
- fancy-inertia/server/index.ts
- fancy-inertia/setupFancyApp.tsx
- fancy-inertia/useAppUpdate.ts
- fancy-inertia/useFancyForm.ts
- fancy-map/Map.tsx
- fancy-map/useGeolocationTrack.ts
- fancy-pwa/activity.ts
- fancy-pwa/components/InstallBanner.tsx
- fancy-pwa/components/OfflineBanner.tsx
- fancy-pwa/dist/styles.css
- fancy-pwa/install.ts
- fancy-pwa/online.ts
- fancy-pwa/provider.tsx
- fancy-pwa/service-worker.ts
- fancy-pwa/sw/index.ts
- fancy-pwa/update.ts
- fancy-pwa/vite/index.ts
- fancy-query/channel.ts
- fancy-query/client.ts
- fancy-query/FancyDataRoot.tsx
- fancy-query/keys.ts
- fancy-query/live.ts
- fancy-query/useFancyEchoInvalidation.ts
- fancy-query/useFancyMutation.ts
- fancy-query/useFancyQuery.ts
- fancy-query/useFancyStream.ts
- fancy-query/useFancyTable.ts
- fancy-query/useInertiaHydration.ts
- react-fancy/components/Accordion/Accordion.context.ts
- react-fancy/components/Accordion/Accordion.tsx
- react-fancy/components/AccordionPanel/AccordionPanel.Content.tsx
- react-fancy/components/AccordionPanel/AccordionPanel.context.ts
- react-fancy/components/AccordionPanel/AccordionPanel.Section.tsx
- react-fancy/components/AccordionPanel/AccordionPanel.tsx
- react-fancy/components/Badge/Badge.tsx
- react-fancy/components/Callout/Callout.tsx
- react-fancy/components/Card/CardBody.tsx
- react-fancy/components/Card/CardMedia.tsx
- react-fancy/components/Carousel/Carousel.context.ts
- react-fancy/components/Carousel/Carousel.tsx
- react-fancy/components/Carousel/CarouselPanels.tsx
- react-fancy/components/Carousel/CarouselSlide.tsx
- react-fancy/components/Chart/Chart.tsx
- react-fancy/components/Chart/ChartArea.tsx
- react-fancy/components/CodeView/CodeView.tsx
- react-fancy/components/Command/Command.context.ts
- react-fancy/components/Command/CommandList.tsx
- react-fancy/components/Container/Container.tsx
- react-fancy/components/ContentRenderer/ContentRenderer.tsx
- react-fancy/components/ContentRenderer/extensions.ts
- react-fancy/components/ContentRenderer/RenderedContent.tsx
- react-fancy/components/ContextMenu/ContextMenu.context.ts
- react-fancy/components/ContextMenu/ContextMenu.tsx
- react-fancy/components/ContextMenu/ContextMenuTrigger.tsx
- react-fancy/components/Drawer/Drawer.context.ts
- react-fancy/components/Drawer/DrawerContainer.tsx
- react-fancy/components/Dropdown/Dropdown.context.ts
- react-fancy/components/Dropdown/Dropdown.tsx
- react-fancy/components/Dropdown/DropdownTrigger.tsx
- react-fancy/components/Editor/Editor.context.ts
- react-fancy/components/Emoji/Emoji.tsx
- react-fancy/components/FileBrowser/FileBrowser.context.ts
- react-fancy/components/FileBrowser/FileBrowser.utils.ts
- react-fancy/components/FileBrowser/FileBrowserTree.tsx
- react-fancy/components/FileUpload/FileUpload.context.ts
- react-fancy/components/FileUpload/FileUpload.tsx
- react-fancy/components/Grid/Grid.tsx
- react-fancy/components/Icon/icon-config.ts
- react-fancy/components/Icon/Icon.tsx
- react-fancy/components/inputs/mode/FieldMode.context.ts
- react-fancy/components/inputs/mode/Form.tsx
- react-fancy/components/inputs/mode/useInlineEdit.ts
- react-fancy/components/inputs/Textarea/Textarea.tsx
- react-fancy/components/InputTag/adapters.ts
- react-fancy/components/JsonEditor/JsonEditor.context.ts
- react-fancy/components/JsonEditor/JsonEditor.keymap.ts
- react-fancy/components/JsonEditor/JsonEditor.paths.ts
- react-fancy/components/JsonEditor/JsonEditor.types.ts
- react-fancy/components/JsonEditor/JsonEditorValue.tsx
- react-fancy/components/Kanban/Kanban.context.ts
- react-fancy/components/Kanban/Kanban.tsx
- react-fancy/components/Kanban/KanbanColumnHandle.tsx
- react-fancy/components/Marquee/Marquee.tsx
- react-fancy/components/MediaViewer/MediaViewer.tsx
- react-fancy/components/Menu/Menu.context.ts
- react-fancy/components/Menu/Menu.tsx
- react-fancy/components/MobileMenu/MobileMenu.context.ts
- react-fancy/components/MobileMenu/MobileMenu.tsx
- react-fancy/components/Modal/Modal.context.ts
- react-fancy/components/Navbar/Navbar.context.ts
- react-fancy/components/Navbar/NavbarBrand.tsx
- react-fancy/components/Popover/Popover.context.ts
- react-fancy/components/Popover/Popover.tsx
- react-fancy/components/Popover/PopoverTrigger.tsx
- react-fancy/components/Portal/Portal.tsx
- react-fancy/components/Sidebar/Sidebar.context.ts
- react-fancy/components/Stat/StatBand.tsx
- react-fancy/components/Table/Table.context.ts
- react-fancy/components/Table/TableTray.tsx
- react-fancy/components/Tabs/Tabs.context.ts
- react-fancy/components/Tabs/Tabs.tsx
- react-fancy/components/Tabs/TabsPanel.tsx
- react-fancy/components/Tabs/TabsPanels.tsx
- react-fancy/components/Timeline/Timeline.context.ts
- react-fancy/components/Toast/Toast.context.ts
- react-fancy/components/Toast/Toast.tsx
- react-fancy/components/TreeNav/TreeNav.context.ts
- react-fancy/components/TreeNav/TreeNav.tsx
- react-fancy/data/emoji-data.ts
- react-fancy/data/emoji-utils.ts
- react-fancy/hooks/use-animation.ts
- react-fancy/hooks/use-controllable-state.ts
- react-fancy/hooks/use-escape-key.ts
- react-fancy/hooks/use-floating-position.ts
- react-fancy/hooks/use-focus-trap.ts
- react-fancy/hooks/use-id.ts
- react-fancy/hooks/use-node-registry.ts
- react-fancy/hooks/use-outside-click.ts
- react-fancy/hooks/use-pan-zoom.ts
- react-fancy/utils/cn.ts
- react-fancy/utils/media-type.ts
- react-fancy/utils/sanitize.ts

No direct hit does not mean colour-independent: composed components and imported shared colour maps can supply colours. In react-fancy, Input, Textarea, Select and DatePicker inherit the literal dark background from inputs.utils.ts. ChartBar, ChartLine and ChartPie inherit DEFAULT_COLORS from chart.utils.ts. Charts, file types and colour-picker defaults may be intentional data colours rather than surfaces, but they do not follow semantic @theme tokens automatically.
Distributed react-fancy CSS also owns literal colours for Input, Textarea, Select, DatePicker, Autocomplete, Pillbox, OtpInput, TimePicker, Field, Checkbox, RadioGroup, Switch, input affixes, Dropdown, Menu, Popover, ContextMenu (including submenu), MobileMenu flyout, Modal, Toast, and ImageViewer checkerboards. Avatar glow fallback is a literal behind --fancy-glow. See themeHooks in the JSON for selectors and line numbers. These owners must be considered even when their TSX files contain no literal colour.
