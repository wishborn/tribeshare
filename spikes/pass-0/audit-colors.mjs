import fs from 'node:fs';
import path from 'node:path';

const sources = new Map();
const packages = {};
const root = 'node_modules/@particle-academy';

for (const name of fs.readdirSync(root)) {
    const directory = path.join(root, name, 'dist');

    if (!fs.existsSync(directory)) {
        continue;
    }

    packages[name] = JSON.parse(
        fs.readFileSync(path.join(root, name, 'package.json'), 'utf8'),
    ).version;

    for (const file of fs
        .readdirSync(directory, { recursive: true })
        .filter((name) => name.endsWith('.css'))) {
        sources.set(
            `${name}/dist/${file}`,
            fs.readFileSync(path.join(directory, file), 'utf8'),
        );
    }

    for (const file of fs
        .readdirSync(directory, { recursive: true })
        .filter((name) => name.endsWith('.js.map'))) {
        const map = JSON.parse(
            fs.readFileSync(path.join(directory, file), 'utf8'),
        );
        map.sources.forEach((source, index) => {
            if (
                /^(?:\.\.\/)+src\//.test(source) &&
                map.sourcesContent?.[index]
            ) {
                sources.set(
                    `${name}/${source.split('/src/')[1]}`,
                    map.sourcesContent[index],
                );
            }
        });
    }
}

const palette =
    /\b(?:bg|text|border|ring|outline|divide|shadow|fill|stroke|from|via|to|decoration|accent|caret|placeholder)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}(?:\/\d+)?|\b(?:bg|text|border|ring|fill|stroke|from|via|to)-(?:white|black)(?:\/\d+)?/g;
const literal =
    /#[\da-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch)\([^\n)]+\)|\b(?:black|white)\b/g;
const rows = [];

for (const [source, content] of [...sources].sort(([a], [b]) =>
    a.localeCompare(b),
)) {
    const hits = content.split('\n').flatMap((line, index) => {
        if (/^\s*(?:\/\/|\*|\/\*)/.test(line)) {
            return [];
        }

        const tokens = [...new Set(line.match(palette) ?? [])];
        // Named white/black only count as literal values, not Tailwind token names.
        const literals = [...new Set(line.match(literal) ?? [])].filter(
            (value) =>
                !['white', 'black'].includes(value) ||
                new RegExp(`["']${value}["']|:\\s*${value}\\s*[;}]`).test(line),
        );

        return tokens.length || literals.length
            ? [{ line: index + 1, tokens, literals, evidence: line.trim() }]
            : [];
    });
    const themeHooks = content
        .split('\n')
        .flatMap((line, index) =>
            /\.dark\b|prefers-color-scheme|data-theme/.test(line)
                ? [{ line: index + 1, evidence: line.trim() }]
                : [],
        );
    rows.push({ source, hits, themeHooks });
}

fs.mkdirSync('spikes/pass-0/evidence', { recursive: true });
fs.writeFileSync(
    'spikes/pass-0/evidence/color-audit.json',
    JSON.stringify({ packages, sourceFiles: rows.length, rows }, null, 2) +
        '\n',
);
const matching = rows.filter((row) => row.hits.length);
const report = [
    '# Installed Fancy packages: colour inventory',
    '',
    ...Object.entries(packages).map(
        ([name, version]) => `- ${name}: ${version}`,
    ),
    '',
    'All shipped src files recovered from ESM source maps, plus distributed CSS, deduplicated by original path. Palette utilities are fixed palette choices but remain Tailwind token-backed; literal CSS/SVG values bypass those tokens. CSS custom-property defaults are configurable through their own variables. This inventory includes utilities and auxiliary component files, not only public exports. Evidence JSON includes every matching line, theme selector/media-query hooks and all scanned files with zero hits. Comments are excluded from colour hits; inspect remaining examples/context before treating a hit as rendered UI. Bundled React Flow CSS is included; other third-party engine internals are not. Fancy ECharts follows system preference or an explicit theme prop, while react-fancy CSS still contains :where(.dark) selectors; neither automatically follows data-theme.',
    '',
    '| Source | Fixed palette utilities | Literal values |',
    '|---|---|---|',
    ...matching.map(
        (row) =>
            `| ${row.source} | ${[...new Set(row.hits.flatMap((hit) => hit.tokens))].join(', ') || '—'} | ${[...new Set(row.hits.flatMap((hit) => hit.literals))].join(', ').replaceAll('|', '\\|') || '—'} |`,
    ),
    '',
    '## No direct colour hits',
    '',
    ...rows.filter((row) => !row.hits.length).map((row) => `- ${row.source}`),
    '',
    'No direct hit does not mean colour-independent: composed components and imported shared colour maps can supply colours. In react-fancy, Input, Textarea, Select and DatePicker inherit the literal dark background from inputs.utils.ts. ChartBar, ChartLine and ChartPie inherit DEFAULT_COLORS from chart.utils.ts. Charts, file types and colour-picker defaults may be intentional data colours rather than surfaces, but they do not follow semantic @theme tokens automatically.',
    'Distributed react-fancy CSS also owns literal colours for Input, Textarea, Select, DatePicker, Autocomplete, Pillbox, OtpInput, TimePicker, Field, Checkbox, RadioGroup, Switch, input affixes, Dropdown, Menu, Popover, ContextMenu (including submenu), MobileMenu flyout, Modal, Toast, and ImageViewer checkerboards. Avatar glow fallback is a literal behind --fancy-glow. See themeHooks in the JSON for selectors and line numbers. These owners must be considered even when their TSX files contain no literal colour.',
    '',
];
fs.writeFileSync(
    'spikes/pass-0/evidence/color-inventory.md',
    report.join('\n'),
);
console.log(
    JSON.stringify({
        packages,
        scanned: rows.length,
        withColors: matching.length,
        literalFiles: matching
            .filter((row) => row.hits.some((hit) => hit.literals.length))
            .map((row) => row.source),
    }),
);
