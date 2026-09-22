import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import process from 'node:process';
import { chromium } from '@playwright/test';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({
    viewport: { width: 1440, height: 1000 },
    colorScheme: 'light',
});
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const css = fs.readFileSync('spikes/pass-0/dist/surface.css', 'utf8');
const js = fs.readFileSync('spikes/pass-0/dist/surface.js', 'utf8');
await page.setContent(
    '<!doctype html><html data-theme="light"><head></head><body><div id="root"></div></body></html>',
);
await page.addStyleTag({ content: css });
await page.addScriptTag({ content: js });
const session = await page.context().newCDPSession(page);
const summary = (values) => {
    const sorted = [...values].sort((a, b) => a - b);

    return {
        median: sorted[Math.floor(sorted.length / 2)],
        p95: sorted[Math.ceil(sorted.length * 0.95) - 1],
        max: sorted.at(-1),
        samples: values,
    };
};
const cell = (row, col, parent = '#meso') =>
    page.locator(`${parent} [data-react-fancy-timegrid-cell="${row}:${col}"]`);
const mount = (days, mode = 'grid') =>
    page.evaluate(({ days, mode }) => window.passZero.mount(days, mode), {
        days,
        mode,
    });
const result = {
    environment: {
        date: new Date().toISOString(),
        platform: os.platform(),
        release: os.release(),
        cpu: os.cpus()[0].model,
        logicalCpus: os.cpus().length,
        node: process.version,
        browser: browser.version(),
        viewport: '1440x1000',
        build: 'Vite production IIFE; React production; injected into isolated browser document, no server/network',
        fancy: '5.17.1',
    },
    budgets: {
        mountLayoutP95Ms: 200,
        updateTwoFrameP95Ms: 50,
        explanation:
            'Predeclared exploratory desktop budgets. Two-frame latency includes frame scheduling. 4x CPU slowdown is a sensitivity check, not a physical mobile measurement.',
    },
    grid: [],
};

try {
    for (const slowdown of [1, 4]) {
        await session.send('Emulation.setCPUThrottlingRate', {
            rate: slowdown,
        });

        for (const days of slowdown === 1 ? [1, 7, 14, 31] : [7, 14]) {
            await mount(days); // Warmup deliberately excluded.
            const mounts = [];

            for (let i = 0; i < 15; i++) {
                mounts.push(await mount(days));
            }

            assert.equal(
                await page
                    .locator('#meso [data-react-fancy-timegrid-cell]')
                    .count(),
                days * 240,
            );
            const updates = [];

            for (let i = 0; i < 30; i++) {
                updates.push(
                    await page.evaluate(async (i) => {
                        const button = document.querySelector(
                            `[data-react-fancy-timegrid-cell="0:${i}"]`,
                        );
                        const start = performance.now();
                        button.dispatchEvent(
                            new MouseEvent('mousedown', {
                                bubbles: true,
                                buttons: 1,
                            }),
                        );
                        button.dispatchEvent(
                            new MouseEvent('mouseup', { bubbles: true }),
                        );
                        await new Promise((resolve) =>
                            requestAnimationFrame(() =>
                                requestAnimationFrame(resolve),
                            ),
                        );

                        return performance.now() - start;
                    }, i),
                );
            }

            assert.equal(
                await page.locator('#meso [aria-pressed="true"]').count(),
                30,
            );
            result.grid.push({
                days,
                cells: days * 240,
                slowdown,
                mountLayoutMs: summary(mounts),
                updateTwoFrameMs: summary(updates),
            });
            console.log(`Measured ${days} days, ${slowdown}x CPU`);
        }
    }

    await session.send('Emulation.setCPUThrottlingRate', { rate: 1 });
    await mount(7);
    const first = await cell(0, 0).boundingBox();
    const last = await cell(0, 20).boundingBox();
    await page.mouse.move(
        first.x + first.width / 2,
        first.y + first.height / 2,
    );
    await page.mouse.down();
    await page.mouse.move(last.x + last.width / 2, last.y + last.height / 2, {
        steps: 40,
    });
    await page.mouse.up();
    const painted = await page.locator('#meso [aria-pressed="true"]').count();
    assert.equal(painted, 21);
    // A discontinuous pointer move tests interpolation, not just slow paint.
    await mount(7);
    await cell(0, 0).hover();
    await page.mouse.down();
    await cell(0, 20).hover();
    await page.mouse.up();
    const jumpPainted = await page
        .locator('#meso [aria-pressed="true"]')
        .count();
    await mount(7);
    await cell(0, 0).focus();
    await page.keyboard.press('Space');
    const keyboardSelected = await cell(0, 0).getAttribute('aria-pressed');
    const sixStateVisuals = await page
        .locator('#meso [data-react-fancy-timegrid-cell]')
        .evaluateAll((elements) => [
            ...new Set(
                elements
                    .slice(0, 6)
                    .map((el) => getComputedStyle(el).backgroundColor),
            ),
        ]);
    result.interactions = {
        slowDragCells: painted,
        expectedInclusiveRangeCells: 21,
        jumpDragCells: jumpPainted,
        keyboardSpaceSelects: keyboardSelected === 'true',
        sixDomainLabelsDistinctBackgrounds: sixStateVisuals.length,
        note: 'Six domain states supplied via ariaCell; no supported per-cell visual state prop. Drag paints visited cells, not an interpolated range.',
    };

    await mount(1, 'builder');
    const dayButtons = page
        .locator('#builder [data-react-fancy-calendar] > div')
        .last()
        .locator('button');
    // Middle of the 42-day grid: guaranteed in current month, no outside-day ambiguity.
    await dayButtons.nth(10).click();
    await dayButtons.nth(12).click();
    const selected = (await page.locator('#selected').textContent()).split(',');
    assert.equal(selected.length, 2);
    await cell(0, 20, '#builder').hover();
    await page.mouse.down();
    const to = await cell(0, 29, '#builder').boundingBox();
    await page.mouse.move(to.x + to.width / 2, to.y + to.height / 2, {
        steps: 20,
    });
    await page.mouse.up();
    await page.getByRole('button', { name: 'Apply bookable rule' }).click();
    let rules = JSON.parse(await page.locator('#rules').textContent());

    for (const id of selected) {
        assert.equal(
            rules[id].filter((value) => value === 'bookable').length,
            10,
        );
        assert.equal(rules[id][19], 'unbookable');
        assert.equal(rules[id][30], 'unbookable');
    }

    await dayButtons.nth(14).click();
    const copyTargets = (await page.locator('#selected').textContent()).split(
        ',',
    );
    await page.getByRole('button', { name: 'Copy first selected day' }).click();
    rules = JSON.parse(await page.locator('#rules').textContent());
    assert.deepEqual(rules[copyTargets[2]], rules[selected[0]]);
    // Mutate the source separately to confirm copied arrays do not alias.
    await dayButtons.nth(12).click();
    await dayButtons.nth(14).click();
    await cell(0, 30, '#builder').click();
    await page.getByRole('button', { name: 'Apply bookable rule' }).click();
    rules = JSON.parse(await page.locator('#rules').textContent());
    assert.equal(
        rules[selected[0]].filter((value) => value === 'bookable').length,
        11,
    );
    assert.equal(
        rules[copyTargets[2]].filter((value) => value === 'bookable').length,
        10,
    );
    result.builder = {
        selectedDays: selected.length,
        slicesPerDayApplied: 10,
        copiedDayCells: 240,
        copyIndependent: true,
        coverage: await page
            .locator('.coverage > div')
            .evaluateAll((elements) =>
                elements.map((el) => ({
                    date: el.dataset.date,
                    count: el.dataset.covered,
                    background: getComputedStyle(el).backgroundColor,
                })),
            ),
        coveragePlacement:
            'External host-owned summary; native Calendar day shading unavailable',
    };
    await page.screenshot({
        path: 'spikes/pass-0/evidence/calendar-builder.png',
        fullPage: true,
    });

    await mount(1, 'theme');
    result.themes = [];

    for (const palette of ['semantic-only', 'mapped']) {
        await page.evaluate(
            (palette) => (document.documentElement.dataset.palette = palette),
            palette,
        );

        for (const theme of ['light', 'dark', 'color']) {
            // This is the ONLY mutation during a mode switch.
            await page.evaluate(
                (theme) => (document.documentElement.dataset.theme = theme),
                theme,
            );
            await page.waitForTimeout(350); // Let component colour transitions settle.
            const colors = await page.evaluate(() => {
                const read = (selector) => {
                    const el = document.querySelector(selector);
                    const style = getComputedStyle(el);

                    return {
                        background: style.backgroundColor,
                        text: style.color,
                        border: style.borderColor,
                    };
                };

                return {
                    semantic: read('#semantic'),
                    button: read('#fancy-button'),
                    badge: read('#fancy-badge'),
                    input: read('#fancy-input'),
                    gridOn: read('[data-react-fancy-timegrid-cell="0:0"]'),
                    gridOff: read('[data-react-fancy-timegrid-cell="0:1"]'),
                    calendar: read('[data-react-fancy-calendar]'),
                    classes: document.documentElement.className,
                };
            });
            assert.equal(colors.classes, '');
            result.themes.push({ palette, theme, colors });
            await page.screenshot({
                path: `spikes/pass-0/evidence/theme-${palette}-${theme}.png`,
            });
        }
    }

    assert.equal(
        new Set(
            result.themes
                .filter((t) => t.palette === 'semantic-only')
                .map((t) => t.colors.semantic.background),
        ).size,
        3,
    );
    assert.equal(
        new Set(
            result.themes
                .filter((t) => t.palette === 'semantic-only')
                .map((t) => t.colors.gridOn.background),
        ).size,
        1,
    );
    assert.equal(
        new Set(
            result.themes
                .filter((t) => t.palette === 'mapped')
                .map((t) => t.colors.gridOn.background),
        ).size,
        3,
    );
    assert.equal(
        result.themes.find((t) => t.palette === 'mapped' && t.theme === 'dark')
            .colors.input.background,
        'rgb(30, 30, 36)',
    );
    assert.deepEqual(errors, []);
    result.browserErrors = errors;
    fs.writeFileSync(
        'spikes/pass-0/evidence/measurements.json',
        JSON.stringify(result, null, 2) + '\n',
    );
    console.log(
        JSON.stringify(
            {
                grid: result.grid.map(
                    ({ days, slowdown, mountLayoutMs, updateTwoFrameMs }) => ({
                        days,
                        slowdown,
                        mountP95: mountLayoutMs.p95,
                        updateP95: updateTwoFrameMs.p95,
                    }),
                ),
                interactions: result.interactions,
                builder: result.builder,
            },
            null,
            2,
        ),
    );
} finally {
    await browser.close();
}
