import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

interface ServerItem {
    name: string;
    unit: string;
    quantity: number;
    price: number;
}
interface ServerGroup {
    title: string;
    items: ServerItem[];
}
interface ServerExtra {
    label: string;
    amount: number;
}
interface ServerHeader {
    date: string;
    title: string;
    object: string;
    customer: string;
    address: string;
    contractor_signatory: string;
    customer_signatory: string;
}
interface ServerPayload {
    header: ServerHeader;
    work_groups: ServerGroup[];
    materials: ServerItem[];
    extras: ServerExtra[];
    discount_percent: number;
}

type Row = {
    name: string;
    unit: string;
    quantity: string;
    price: string;
};
type Group = {
    title: string;
    items: Row[];
};
type Extra = {
    label: string;
    amount: string;
};
type HeaderForm = {
    date: string;
    title: string;
    object: string;
    customer: string;
    address: string;
    contractor_signatory: string;
    customer_signatory: string;
};

type FormShape = {
    header: HeaderForm;
    work_groups: Group[];
    materials: Row[];
    extras: Extra[];
    discount_percent: string;
};

const numToStr = (n: number): string => (n ? String(n) : '');
const toNumber = (s: string): number => parseFloat(String(s).replace(',', '.')) || 0;
const lineTotal = (r: Row): number => toNumber(r.quantity) * toNumber(r.price);
const formatMoney = (n: number): string => n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';

const toRow = (i: ServerItem): Row => ({ name: i.name, unit: i.unit, quantity: numToStr(i.quantity), price: numToStr(i.price) });
const emptyRow = (): Row => ({ name: '', unit: '', quantity: '', price: '' });

export default function DocumentsPreview({
    documentType,
    project,
    payload,
}: {
    documentType: { value: string; label: string };
    project: { id: number; name: string };
    payload: ServerPayload;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Документы', href: '/documents' },
        { title: 'Предпросмотр', href: '#' },
    ];

    const form = useForm<FormShape>({
        header: { ...payload.header },
        work_groups: payload.work_groups.map((g) => ({ title: g.title, items: g.items.map(toRow) })),
        materials: payload.materials.map(toRow),
        extras: payload.extras.map((e) => ({ label: e.label, amount: numToStr(e.amount) })),
        discount_percent: numToStr(payload.discount_percent),
    });
    const { data, setData, processing } = form;

    const setHeader = (field: keyof ServerHeader, value: string) => setData('header', { ...data.header, [field]: value });

    // --- Работы ---
    const setGroups = (next: Group[]) => setData('work_groups', next);
    const updateGroupTitle = (gi: number, v: string) => setGroups(data.work_groups.map((g, i) => (i === gi ? { ...g, title: v } : g)));
    const updateWorkCell = (gi: number, ri: number, f: keyof Row, v: string) =>
        setGroups(data.work_groups.map((g, i) => (i === gi ? { ...g, items: g.items.map((r, j) => (j === ri ? { ...r, [f]: v } : r)) } : g)));
    const addWorkRow = (gi: number) => setGroups(data.work_groups.map((g, i) => (i === gi ? { ...g, items: [...g.items, emptyRow()] } : g)));
    const removeWorkRow = (gi: number, ri: number) =>
        setGroups(data.work_groups.map((g, i) => (i === gi ? { ...g, items: g.items.filter((_, j) => j !== ri) } : g)));
    const addGroup = () => setGroups([...data.work_groups, { title: '', items: [emptyRow()] }]);
    const removeGroup = (gi: number) => setGroups(data.work_groups.filter((_, i) => i !== gi));

    // --- Материалы ---
    const updateMaterial = (ri: number, f: keyof Row, v: string) => setData('materials', data.materials.map((r, j) => (j === ri ? { ...r, [f]: v } : r)));
    const addMaterial = () => setData('materials', [...data.materials, emptyRow()]);
    const removeMaterial = (ri: number) => setData('materials', data.materials.filter((_, j) => j !== ri));

    // --- Доп. расходы ---
    const updateExtra = (ei: number, f: keyof Extra, v: string) => setData('extras', data.extras.map((e, j) => (j === ei ? { ...e, [f]: v } : e)));
    const addExtra = () => setData('extras', [...data.extras, { label: '', amount: '' }]);
    const removeExtra = (ei: number) => setData('extras', data.extras.filter((_, j) => j !== ei));

    // --- Итоги ---
    const worksTotal = data.work_groups.reduce((s, g) => s + g.items.reduce((a, r) => a + lineTotal(r), 0), 0);
    const materialsTotal = data.materials.reduce((s, r) => s + lineTotal(r), 0);
    const discount = toNumber(data.discount_percent);
    const worksDiscounted = worksTotal * (1 - discount / 100);
    const extrasTotal = data.extras.reduce((s, e) => s + toNumber(e.amount), 0);
    const grand = worksDiscounted + materialsTotal + extrasTotal;

    const submit = () => {
        form.transform(() => ({
            type: documentType.value,
            project_id: project.id,
            payload: {
                header: data.header,
                work_groups: data.work_groups.map((g) => ({
                    title: g.title.trim(),
                    items: g.items
                        .filter((r) => r.name.trim() !== '')
                        .map((r) => ({ name: r.name.trim(), unit: r.unit.trim(), quantity: toNumber(r.quantity), price: toNumber(r.price) })),
                })),
                materials: data.materials
                    .filter((r) => r.name.trim() !== '')
                    .map((r) => ({ name: r.name.trim(), unit: r.unit.trim(), quantity: toNumber(r.quantity), price: toNumber(r.price) })),
                extras: data.extras
                    .filter((e) => e.label.trim() !== '')
                    .map((e) => ({ label: e.label.trim(), amount: toNumber(e.amount) })),
                discount_percent: discount,
            },
        }));
        form.post(route('documents.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${documentType.label} — предпросмотр`} />

            <div className="flex flex-col gap-8 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{documentType.label}</h1>
                        <p className="text-muted-foreground text-sm">Объект: {project.name}. Проверьте и отредактируйте данные перед сохранением.</p>
                    </div>
                    <Button onClick={submit} disabled={processing}>
                        Принять и сохранить
                    </Button>
                </div>

                {/* Шапка */}
                <section className="border-sidebar-border/70 grid gap-4 rounded-xl border p-5 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label>Дата</Label>
                        <Input value={data.header.date} onChange={(e) => setHeader('date', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Описание объекта</Label>
                        <Input value={data.header.object} onChange={(e) => setHeader('object', e.target.value)} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label>Заголовок</Label>
                        <Input value={data.header.title} onChange={(e) => setHeader('title', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Заказчик</Label>
                        <Input value={data.header.customer} onChange={(e) => setHeader('customer', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Адрес</Label>
                        <Input value={data.header.address} onChange={(e) => setHeader('address', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Подписант (подрядчик)</Label>
                        <Input value={data.header.contractor_signatory} onChange={(e) => setHeader('contractor_signatory', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Подписант (заказчик)</Label>
                        <Input value={data.header.customer_signatory} onChange={(e) => setHeader('customer_signatory', e.target.value)} />
                    </div>
                </section>

                {/* Работы */}
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Ведомость работ</h2>

                    {data.work_groups.map((group, gi) => {
                        const groupTotal = group.items.reduce((s, r) => s + lineTotal(r), 0);
                        return (
                            <div key={gi} className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                                <div className="border-sidebar-border/70 bg-muted/30 flex items-center gap-2 border-b px-3 py-2">
                                    <input
                                        value={group.title}
                                        onChange={(e) => updateGroupTitle(gi, e.target.value)}
                                        placeholder="Раздел (напр. Квартира, Сантехнические работы)…"
                                        className="w-full max-w-md bg-transparent px-1 py-1 text-sm font-medium focus:outline-none"
                                    />
                                    <button type="button" onClick={() => removeGroup(gi)} className="text-muted-foreground hover:text-red-600" title="Удалить раздел">
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground text-left">
                                            <tr>
                                                <th className="w-10 px-3 py-2 font-medium">№</th>
                                                <th className="px-3 py-2 font-medium">Наименование работ</th>
                                                <th className="w-24 px-3 py-2 font-medium">Ед.изм.</th>
                                                <th className="w-24 px-3 py-2 text-right font-medium">Объём</th>
                                                <th className="w-28 px-3 py-2 text-right font-medium">Цена</th>
                                                <th className="w-32 px-3 py-2 text-right font-medium">Стоимость</th>
                                                <th className="w-10 px-2 py-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {group.items.map((row, ri) => (
                                                <tr key={ri} className="border-sidebar-border/70 border-t">
                                                    <td className="text-muted-foreground px-3 py-1 text-center">{ri + 1}</td>
                                                    <td className="px-1 py-1"><Cell value={row.name} onChange={(v) => updateWorkCell(gi, ri, 'name', v)} /></td>
                                                    <td className="px-1 py-1"><Cell value={row.unit} onChange={(v) => updateWorkCell(gi, ri, 'unit', v)} /></td>
                                                    <td className="px-1 py-1"><Cell value={row.quantity} onChange={(v) => updateWorkCell(gi, ri, 'quantity', v)} numeric /></td>
                                                    <td className="px-1 py-1"><Cell value={row.price} onChange={(v) => updateWorkCell(gi, ri, 'price', v)} numeric /></td>
                                                    <td className="px-3 py-1 text-right tabular-nums">{formatMoney(lineTotal(row))}</td>
                                                    <td className="px-2 py-1 text-center">
                                                        <button type="button" onClick={() => removeWorkRow(gi, ri)} className="text-muted-foreground hover:text-red-600" title="Удалить строку">
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-sidebar-border/70 border-t">
                                                <td colSpan={5} className="px-3 py-2 text-right text-xs text-muted-foreground">Итог по разделу</td>
                                                <td className="px-3 py-2 text-right font-medium tabular-nums">{formatMoney(groupTotal)}</td>
                                                <td className="px-2 py-1 text-center">
                                                    <button type="button" onClick={() => addWorkRow(gi)} className="text-muted-foreground hover:text-foreground" title="Добавить строку">
                                                        <Plus className="size-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex items-center justify-between">
                        <Button type="button" variant="outline" size="sm" onClick={addGroup}>
                            <Plus className="size-4" />
                            Раздел работ
                        </Button>
                        <div className="text-base">Итого по работам: <span className="font-semibold tabular-nums">{formatMoney(worksTotal)}</span></div>
                    </div>
                </section>

                {/* Материалы */}
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-medium">Ведомость подготовительных материалов</h2>
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Наименование материала</th>
                                    <th className="w-24 px-3 py-2 font-medium">Ед.изм.</th>
                                    <th className="w-24 px-3 py-2 text-right font-medium">Кол-во</th>
                                    <th className="w-28 px-3 py-2 text-right font-medium">Цена</th>
                                    <th className="w-32 px-3 py-2 text-right font-medium">Стоимость</th>
                                    <th className="w-10 px-2 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {data.materials.map((row, ri) => (
                                    <tr key={ri} className="border-sidebar-border/70 border-t">
                                        <td className="px-1 py-1"><Cell value={row.name} onChange={(v) => updateMaterial(ri, 'name', v)} /></td>
                                        <td className="px-1 py-1"><Cell value={row.unit} onChange={(v) => updateMaterial(ri, 'unit', v)} /></td>
                                        <td className="px-1 py-1"><Cell value={row.quantity} onChange={(v) => updateMaterial(ri, 'quantity', v)} numeric /></td>
                                        <td className="px-1 py-1"><Cell value={row.price} onChange={(v) => updateMaterial(ri, 'price', v)} numeric /></td>
                                        <td className="px-3 py-1 text-right tabular-nums">{formatMoney(lineTotal(row))}</td>
                                        <td className="px-2 py-1 text-center">
                                            <button type="button" onClick={() => removeMaterial(ri)} className="text-muted-foreground hover:text-red-600" title="Удалить строку">
                                                <Trash2 className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-sidebar-border/70 border-t">
                                    <td colSpan={4} className="px-3 py-2 text-right font-medium">Итого по материалам</td>
                                    <td className="px-3 py-2 text-right font-semibold tabular-nums">{formatMoney(materialsTotal)}</td>
                                    <td className="px-2 py-1 text-center">
                                        <button type="button" onClick={addMaterial} className="text-muted-foreground hover:text-foreground" title="Добавить строку">
                                            <Plus className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                {/* Итоги и доп. расходы */}
                <section className="border-sidebar-border/70 grid gap-4 rounded-xl border p-5">
                    <div className="flex items-center gap-3">
                        <Label htmlFor="discount" className="whitespace-nowrap">Скидка на работы, %</Label>
                        <Input
                            id="discount"
                            inputMode="decimal"
                            className="w-28 text-right tabular-nums"
                            value={data.discount_percent}
                            onChange={(e) => setData('discount_percent', e.target.value)}
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <span className="text-sm font-medium">Дополнительные статьи</span>
                        {data.extras.map((extra, ei) => (
                            <div key={ei} className="flex items-center gap-2">
                                <input
                                    value={extra.label}
                                    onChange={(e) => updateExtra(ei, 'label', e.target.value)}
                                    placeholder="Наименование статьи…"
                                    className="border-sidebar-border/70 focus:ring-ring w-full rounded border bg-transparent px-2 py-1 text-sm focus:ring-1 focus:outline-none"
                                />
                                <input
                                    value={extra.amount}
                                    onChange={(e) => updateExtra(ei, 'amount', e.target.value)}
                                    inputMode="decimal"
                                    placeholder="Сумма"
                                    className="border-sidebar-border/70 focus:ring-ring w-36 rounded border bg-transparent px-2 py-1 text-right text-sm tabular-nums focus:ring-1 focus:outline-none"
                                />
                                <button type="button" onClick={() => removeExtra(ei)} className="text-muted-foreground hover:text-red-600" title="Удалить">
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}
                        <div>
                            <Button type="button" variant="outline" size="sm" onClick={addExtra}>
                                <Plus className="size-4" />
                                Статья
                            </Button>
                        </div>
                    </div>

                    <div className="border-sidebar-border/70 ml-auto grid w-full max-w-sm gap-1 border-t pt-3 text-sm">
                        <Row2 label="Работы:" value={formatMoney(worksTotal)} />
                        <Row2 label={`Работы со скидкой ${discount || 0}%:`} value={formatMoney(worksDiscounted)} />
                        <Row2 label="Материалы:" value={formatMoney(materialsTotal)} />
                        <Row2 label="Доп. статьи:" value={formatMoney(extrasTotal)} />
                        <div className="border-sidebar-border/70 mt-1 flex justify-between border-t pt-2 text-base font-semibold">
                            <span>Всего:</span>
                            <span className="tabular-nums">{formatMoney(grand)}</span>
                        </div>
                    </div>
                </section>

                <div className="flex justify-end">
                    <Button onClick={submit} disabled={processing}>
                        Принять и сохранить
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

function Row2({ label, value }: { label: string; value: string }) {
    return (
        <div className="text-muted-foreground flex justify-between">
            <span>{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
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
