import { Badge } from '@particle-academy/react-fancy/badge';
import { Button } from '@particle-academy/react-fancy/button';
import { Calendar } from '@particle-academy/react-fancy/calendar';
import { Input } from '@particle-academy/react-fancy/input';
import { TimeGrid } from '@particle-academy/react-fancy/time-grid';
import { useState } from 'react';
import { flushSync } from 'react-dom';
import { createRoot } from 'react-dom/client';
import './theme.css';

const states = [
    'bookable',
    'unbookable',
    'booked',
    'bookend buffer',
    'priority-restricted',
    'price-adjusted',
];
const cols = Array.from(
    { length: 240 },
    (_, i) =>
        `${Math.floor(i / 10)
            .toString()
            .padStart(2, '0')}:${((i % 10) * 6).toString().padStart(2, '0')}`,
);
const blank = (days: number) =>
    Array.from({ length: days }, () => Array<boolean>(240).fill(false));
const key = (date: Date) =>
    `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`;

function GridProbe({ days }: { days: number }) {
    const [value, setValue] = useState(() => blank(days));

    return (
        <section id="meso">
            <TimeGrid
                rows={Array.from({ length: days }, (_, i) => `Day ${i + 1}`)}
                cols={cols}
                value={value}
                onChange={setValue}
                ariaCell={(r, c, on) =>
                    `Day ${r + 1}, ${cols[c]}, domain=${states[(r * 240 + c) % 6]}, selected=${on}`
                }
            />
        </section>
    );
}

function BuilderProbe() {
    const [selected, setSelected] = useState<Date[]>([]);
    const [rules, setRules] = useState<Record<string, string[]>>({});
    const [range, setRange] = useState<boolean[][]>(() => blank(1));
    const apply = () =>
        setRules((previous) => {
            const next = { ...previous };

            for (const day of selected) {
                const id = key(day);
                next[id] = Array.from({ length: 240 }, (_, i) =>
                    range[0][i]
                        ? 'bookable'
                        : (previous[id]?.[i] ?? 'unbookable'),
                );
            }

            return next;
        });
    const copy = () =>
        setRules((previous) => {
            if (selected.length < 2) {
                return previous;
            }

            const next = { ...previous };
            const source =
                previous[key(selected[0])] ??
                Array<string>(240).fill('unbookable');

            for (const day of selected.slice(1)) {
                next[key(day)] = source.slice();
            }

            return next;
        });

    return (
        <section id="builder">
            <h2>Calendar authoring probe</h2>
            <Calendar
                mode="multiple"
                value={selected}
                onChange={(next) =>
                    setSelected(Array.isArray(next) ? next : [])
                }
            />
            <output id="selected">{selected.map(key).join(',')}</output>
            <TimeGrid
                rows={['Meso rule']}
                cols={cols}
                value={range}
                onChange={setRange}
            />
            <div className="controls">
                <Button onClick={apply}>Apply bookable rule</Button>
                <Button onClick={copy}>Copy first selected day</Button>
            </div>
            <p>
                Coverage below is host-owned. Calendar exposes no day renderer
                or day class hook.
            </p>
            <div className="coverage">
                {Object.entries(rules).map(([date, cells]) => {
                    const count = cells.filter((s) => s === 'bookable').length;

                    return (
                        <div
                            key={date}
                            data-date={date}
                            data-covered={count}
                            style={{
                                background: `color-mix(in srgb, var(--color-primary) ${(count / 240) * 100}%, var(--color-background))`,
                            }}
                        >
                            {date}: {count}/240
                        </div>
                    );
                })}
            </div>
            <output id="rules" hidden>
                {JSON.stringify(rules)}
            </output>
        </section>
    );
}

function ThemeProbe() {
    const [selection, setSelection] = useState([[true, false]]);

    return (
        <section id="theme-probe" className="bg-background text-foreground">
            <span id="semantic" className="bg-primary">
                Semantic primary control
            </span>
            <Button id="fancy-button" color="violet">
                Fancy button
            </Button>
            <Badge id="fancy-badge" color="violet">
                Fancy badge
            </Badge>
            <Input
                id="fancy-input"
                aria-label="Literal colour probe"
                placeholder="Literal dark input background"
            />
            <TimeGrid
                rows={['Theme']}
                cols={['On', 'Off']}
                value={selection}
                onChange={setSelection}
            />
            <Calendar />
        </section>
    );
}

function Surface({ days, mode }: { days: number; mode: string }) {
    return (
        <main>
            {mode === 'grid' ? (
                <GridProbe days={days} />
            ) : mode === 'builder' ? (
                <BuilderProbe />
            ) : (
                <ThemeProbe />
            )}
        </main>
    );
}

const root = createRoot(document.getElementById('root')!);
let generation = 0;
declare global {
    interface Window {
        passZero: { mount: (days: number, mode?: string) => number };
    }
}
window.passZero = {
    mount(days, mode = 'grid') {
        const start = performance.now();
        flushSync(() =>
            root.render(<Surface key={++generation} days={days} mode={mode} />),
        );
        // Include synchronous layout, not just React's reconciliation time.
        document.body.getBoundingClientRect();

        return performance.now() - start;
    },
};
