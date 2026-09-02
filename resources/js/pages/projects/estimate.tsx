import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type EditableRow = {
    name: string;
    unit: string;
    quantity: string;
    price: string;
};

type WorkGroup = {
    category: string;
    rows: EditableRow[];
};

interface ServerRow {
    name: string;
    unit: string | null;
    quantity: number;
    price: number;
    total: number;
}

interface Props {
    project: { id: number; name: string };
    works: Array<{ category: string; rows: ServerRow[] }>;
    materials: ServerRow[];
    totals: { works: number; materials: number };
}

const numToStr = (n: number): string => (n ? String(n) : '');
const toNumber = (s: string): number => parseFloat(s.replace(',', '.')) || 0;
const lineTotal = (row: EditableRow): number => toNumber(row.quantity) * toNumber(row.price);
const formatMoney = (n: number): string => n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';
const emptyRow = (): EditableRow => ({ name: '', unit: '', quantity: '', price: '' });
const toRow = (r: ServerRow): EditableRow => ({ name: r.name, unit: r.unit ?? '', quantity: numToStr(r.quantity), price: numToStr(r.price) });
const isRowEmpty = (r: EditableRow): boolean => !r.name.trim() && !r.unit.trim() && !r.quantity.trim() && !r.price.trim();

// Гарантирует, что внизу всегда есть одна пустая строка для заполнения (как в Excel).
const withTrailingRow = (rows: EditableRow[]): EditableRow[] => (rows.length === 0 || !isRowEmpty(rows[rows.length - 1]) ? [...rows, emptyRow()] : rows);

export default function EstimateEdit({ project, works, materials }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Объекты', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Смета', href: `/projects/${project.id}/estimate` },
    ];

    const form = useForm({
        works: (works.length ? works.map((g) => ({ category: g.category, rows: g.rows.map(toRow) })) : [{ category: '', rows: [] as EditableRow[] }]).map((g) => ({
            ...g,
            rows: withTrailingRow(g.rows),
        })) as WorkGroup[],
        materials: withTrailingRow(materials.map(toRow)),
    });
    const { data, setData, processing, recentlySuccessful } = form;

    const [tab, setTab] = useState<'works' | 'materials'>('works');

    // --- Работы -------------------------------------------------------------
    const setWorks = (next: WorkGroup[]) => setData('works', next);

    const updateGroupCategory = (gi: number, value: string) => setWorks(data.works.map((g, i) => (i === gi ? { ...g, category: value } : g)));

    const updateWorkCell = (gi: number, ri: number, field: keyof EditableRow, value: string) =>
        setWorks(data.works.map((g, i) => (i === gi ? { ...g, rows: withTrailingRow(g.rows.map((r, j) => (j === ri ? { ...r, [field]: value } : r))) } : g)));

    const removeWorkRow = (gi: number, ri: number) => setWorks(data.works.map((g, i) => (i === gi ? { ...g, rows: withTrailingRow(g.rows.filter((_, j) => j !== ri)) } : g)));

    const addWorkGroup = () => setWorks([...data.works, { category: '', rows: [emptyRow()] }]);

    const removeWorkGroup = (gi: number) => setWorks(data.works.filter((_, i) => i !== gi));

    // --- Материалы ----------------------------------------------------------
    const updateMaterialCell = (ri: number, field: keyof EditableRow, value: string) =>
        setData('materials', withTrailingRow(data.materials.map((r, j) => (j === ri ? { ...r, [field]: value } : r))));

    const removeMaterialRow = (ri: number) => setData('materials', withTrailingRow(data.materials.filter((_, j) => j !== ri)));

    // --- Итоги --------------------------------------------------------------
    const worksTotal = data.works.reduce((sum, g) => sum + g.rows.reduce((s, r) => s + lineTotal(r), 0), 0);
    const materialsTotal = data.materials.reduce((sum, r) => sum + lineTotal(r), 0);

    const submit = () => {
        form.transform((d) => ({
            works: d.works.flatMap((g) =>
                g.rows
                    .filter((r) => r.name.trim() !== '')
                    .map((r) => ({ category: g.category.trim(), name: r.name.trim(), unit: r.unit.trim(), quantity: toNumber(r.quantity), price: toNumber(r.price) })),
            ),
            materials: d.materials
                .filter((r) => r.name.trim() !== '')
                .map((r) => ({ name: r.name.trim(), unit: r.unit.trim(), quantity: toNumber(r.quantity), price: toNumber(r.price) })),
        }));
        form.put(route('projects.estimate.update', project.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Смета: ${project.name}`} />

            <div className="flex flex-col gap-8 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">Смета — {project.name}</h1>
                    <div className="flex items-center gap-3">
                        {recentlySuccessful && <span className="text-sm text-green-600">Сохранено</span>}
                        <Button onClick={submit} disabled={processing}>
                            Сохранить смету
                        </Button>
                    </div>
                </div>

                {/* Переключатель таблиц */}
                <div className="border-sidebar-border/70 bg-muted/40 inline-flex w-fit rounded-lg border p-1">
                    <button
                        type="button"
                        onClick={() => setTab('works')}
                        className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${tab === 'works' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                    >
                        Работы
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('materials')}
                        className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${tab === 'materials' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                    >
                        Материалы
                    </button>
                </div>

                {/* Смета работ */}
                <section className={`flex-col gap-3 ${tab === 'works' ? 'flex' : 'hidden'}`}>
                    <h2 className="text-lg font-medium">Смета работ</h2>

                    {data.works.map((group, gi) => {
                        const groupTotal = group.rows.reduce((s, r) => s + lineTotal(r), 0);
                        return (
                            <div key={gi} className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                                <div className="border-sidebar-border/70 bg-muted/30 flex items-center gap-2 border-b px-3 py-2">
                                    <input
                                        value={group.category}
                                        onChange={(e) => updateGroupCategory(gi, e.target.value)}
                                        placeholder="Подкатегория (напр. Кухня, Ванная)…"
                                        className="w-full max-w-md bg-transparent px-1 py-1 text-sm font-medium focus:outline-none"
                                    />
                                    <button type="button" onClick={() => removeWorkGroup(gi)} className="text-muted-foreground hover:text-red-600" title="Удалить подкатегорию">
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground text-left">
                                            <tr>
                                                <th className="w-12 px-3 py-2 font-medium">№</th>
                                                <th className="px-3 py-2 font-medium">Наименование работ</th>
                                                <th className="w-24 px-3 py-2 font-medium">Ед. изм.</th>
                                                <th className="w-28 px-3 py-2 text-right font-medium">Кол-во</th>
                                                <th className="w-32 px-3 py-2 text-right font-medium">Стоимость</th>
                                                <th className="w-36 px-3 py-2 text-right font-medium">Итого</th>
                                                <th className="w-10 px-2 py-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {group.rows.map((row, ri) => (
                                                <tr key={ri} className="border-sidebar-border/70 border-t">
                                                    <td className="text-muted-foreground px-3 py-1 text-center">{ri + 1}</td>
                                                    <td className="px-1 py-1">
                                                        <Cell value={row.name} onChange={(v) => updateWorkCell(gi, ri, 'name', v)} />
                                                    </td>
                                                    <td className="px-1 py-1">
                                                        <Cell value={row.unit} onChange={(v) => updateWorkCell(gi, ri, 'unit', v)} />
                                                    </td>
                                                    <td className="px-1 py-1">
                                                        <Cell value={row.quantity} onChange={(v) => updateWorkCell(gi, ri, 'quantity', v)} numeric />
                                                    </td>
                                                    <td className="px-1 py-1">
                                                        <Cell value={row.price} onChange={(v) => updateWorkCell(gi, ri, 'price', v)} numeric />
                                                    </td>
                                                    <td className="px-3 py-1 text-right tabular-nums">{isRowEmpty(row) ? '' : formatMoney(lineTotal(row))}</td>
                                                    <td className="px-2 py-1 text-center">
                                                        {!isRowEmpty(row) && (
                                                            <button type="button" onClick={() => removeWorkRow(gi, ri)} className="text-muted-foreground hover:text-red-600" title="Удалить строку">
                                                                <Trash2 className="size-4" />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-sidebar-border/70 border-t">
                                                <td colSpan={5} className="px-3 py-2 text-right text-xs text-muted-foreground">Итого по разделу</td>
                                                <td className="px-3 py-2 text-right font-medium tabular-nums">{formatMoney(groupTotal)}</td>
                                                <td />
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex items-center justify-between">
                        <Button type="button" variant="outline" size="sm" onClick={addWorkGroup}>
                            <Plus className="size-4" />
                            Подкатегория
                        </Button>
                        <div className="text-base">
                            Итого по работам: <span className="font-semibold tabular-nums">{formatMoney(worksTotal)}</span>
                        </div>
                    </div>
                </section>

                {/* Смета материалов */}
                <section className={`flex-col gap-3 ${tab === 'materials' ? 'flex' : 'hidden'}`}>
                    <h2 className="text-lg font-medium">Смета отделочных материалов</h2>

                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Наименование</th>
                                    <th className="w-24 px-3 py-2 font-medium">Ед. изм.</th>
                                    <th className="w-28 px-3 py-2 text-right font-medium">Кол-во</th>
                                    <th className="w-32 px-3 py-2 text-right font-medium">Стоимость</th>
                                    <th className="w-36 px-3 py-2 text-right font-medium">Итого</th>
                                    <th className="w-10 px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.materials.map((row, ri) => (
                                    <tr key={ri} className="border-sidebar-border/70 border-t">
                                        <td className="px-1 py-1">
                                            <Cell value={row.name} onChange={(v) => updateMaterialCell(ri, 'name', v)} />
                                        </td>
                                        <td className="px-1 py-1">
                                            <Cell value={row.unit} onChange={(v) => updateMaterialCell(ri, 'unit', v)} />
                                        </td>
                                        <td className="px-1 py-1">
                                            <Cell value={row.quantity} onChange={(v) => updateMaterialCell(ri, 'quantity', v)} numeric />
                                        </td>
                                        <td className="px-1 py-1">
                                            <Cell value={row.price} onChange={(v) => updateMaterialCell(ri, 'price', v)} numeric />
                                        </td>
                                        <td className="px-3 py-1 text-right tabular-nums">{isRowEmpty(row) ? '' : formatMoney(lineTotal(row))}</td>
                                        <td className="px-2 py-1 text-center">
                                            {!isRowEmpty(row) && (
                                                <button type="button" onClick={() => removeMaterialRow(ri)} className="text-muted-foreground hover:text-red-600" title="Удалить строку">
                                                    <Trash2 className="size-4" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-sidebar-border/70 border-t-2">
                                    <td colSpan={4} className="px-3 py-2 text-right font-medium">Итого по материалам</td>
                                    <td className="px-3 py-2 text-right font-semibold tabular-nums">{formatMoney(materialsTotal)}</td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Cell({ value, onChange, numeric = false }: { value: string; onChange: (value: string) => void; numeric?: boolean }) {
    return (
        <input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            inputMode={numeric ? 'decimal' : undefined}
            className={`focus:ring-ring w-full rounded bg-transparent px-2 py-1 text-sm focus:ring-1 focus:outline-none ${numeric ? 'text-right tabular-nums' : ''}`}
        />
    );
}
