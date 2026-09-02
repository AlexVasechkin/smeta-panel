import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Field = { key: string; label: string; multiline?: boolean };
type Group = { title: string; fields: Field[] };

type AvailablePosition = {
    key: string;
    number: number;
    group: string;
    name: string;
    unit: string;
    price: number;
    estimate_quantity: number;
    remaining: number;
};

type PositionRow = AvailablePosition & { checked: boolean; quantity: string };

const passportFields = (prefix: string): Field[] => [
    { key: `${prefix}passport_type`, label: 'Паспорт' },
    { key: `${prefix}passport_series`, label: 'Серия' },
    { key: `${prefix}passport_number`, label: 'Номер' },
    { key: `${prefix}passport_issued_by`, label: 'Кем выдан' },
    { key: `${prefix}passport_issued_at`, label: 'Дата выдачи' },
    { key: `${prefix}passport_registration_address`, label: 'Адрес регистрации' },
];

const GROUPS: Group[] = [
    {
        title: 'Акт',
        fields: [
            { key: 'number', label: 'Номер акта' },
            { key: 'city', label: 'Город' },
            { key: 'day', label: 'День' },
            { key: 'month', label: 'Месяц' },
            { key: 'year', label: 'Год' },
            { key: 'order_number', label: 'Номер заказа' },
            { key: 'order_created_at', label: 'Дата заказа' },
            { key: 'square', label: 'Площадь, м²' },
            { key: 'address', label: 'Адрес объекта', multiline: true },
        ],
    },
    {
        title: 'Подрядчик (организация) — сдаёт работы',
        fields: [
            { key: 'contractor_surname', label: 'Фамилия' },
            { key: 'contractor_name', label: 'Имя' },
            { key: 'contractor_father_name', label: 'Отчество' },
            ...passportFields('contractor_'),
            { key: 'org_bank_name', label: 'Банк' },
            { key: 'org_card_number', label: 'Номер карты' },
            { key: 'org_phone', label: 'Телефон' },
            { key: 'org_email', label: 'Email' },
        ],
    },
    {
        title: 'Заказчик (клиент) — принимает работы',
        fields: [
            { key: 'customer_surname', label: 'Фамилия' },
            { key: 'customer_name', label: 'Имя' },
            { key: 'customer_father_name', label: 'Отчество' },
            ...passportFields('customer_'),
            { key: 'customer_phone', label: 'Телефон' },
            { key: 'customer_email', label: 'Email' },
        ],
    },
];

const money = (value: number): string =>
    value.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const num = (value: number): string =>
    value.toLocaleString('ru-RU', { maximumFractionDigits: 3 });

export default function DocumentsWorksAct({
    documentType,
    project,
    payload,
}: {
    documentType: { value: string; label: string };
    project: { id: number; name: string };
    payload: Record<string, string | number> & { available_positions?: AvailablePosition[] };
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Документы', href: '/documents' },
        { title: 'Акт выполненных работ', href: '#' },
    ];

    const initial: Record<string, string> = {};
    for (const group of GROUPS) {
        for (const field of group.fields) {
            initial[field.key] = payload[field.key] !== undefined && payload[field.key] !== null ? String(payload[field.key]) : '';
        }
    }

    const form = useForm(initial);
    const { data, setData, processing } = form;

    const [advance, setAdvance] = useState<string>(
        payload.advance_percent !== undefined && payload.advance_percent !== null ? String(payload.advance_percent) : '20',
    );

    // По умолчанию все позиции сняты; объём подставляется на весь остаток.
    const [positions, setPositions] = useState<PositionRow[]>(
        (payload.available_positions ?? []).map((position) => ({
            ...position,
            checked: false,
            quantity: String(position.remaining),
        })),
    );

    const setPosition = (index: number, patch: Partial<PositionRow>) => {
        setPositions((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const toggleAll = (value: boolean) => {
        setPositions((rows) => rows.map((row) => ({ ...row, checked: value })));
    };

    const worksSum = useMemo(
        () =>
            positions.reduce((sum, position) => {
                if (!position.checked) return sum;
                const qty = parseFloat(position.quantity.replace(',', '.')) || 0;
                return sum + qty * position.price;
            }, 0),
        [positions],
    );

    const advancePercent = parseFloat(advance.replace(',', '.')) || 0;
    const cost = worksSum * (1 - advancePercent / 100);
    const selectedCount = positions.filter((position) => position.checked).length;
    const allChecked: boolean | 'indeterminate' =
        positions.length > 0 && selectedCount === positions.length
            ? true
            : selectedCount === 0
              ? false
              : 'indeterminate';

    // Разбивка по разделам (как в смете), с сохранением исходного индекса позиции.
    const groups = useMemo(() => {
        const map = new Map<string, { index: number; position: PositionRow }[]>();
        positions.forEach((position, index) => {
            const key = position.group || 'Без раздела';
            if (!map.has(key)) map.set(key, []);
            map.get(key)!.push({ index, position });
        });
        return Array.from(map, ([title, rows]) => ({ title, rows }));
    }, [positions]);

    const toggleGroup = (title: string, value: boolean) => {
        setPositions((rows) => rows.map((row) => ((row.group || 'Без раздела') === title ? { ...row, checked: value } : row)));
    };

    const submit = () => {
        form.transform((current) => ({
            type: documentType.value,
            project_id: project.id,
            payload: {
                ...current,
                advance_percent: advancePercent,
                positions: positions
                    .filter((position) => position.checked && (parseFloat(position.quantity.replace(',', '.')) || 0) > 0)
                    .map((position) => ({
                        key: position.key,
                        group: position.group,
                        name: position.name,
                        unit: position.unit,
                        price: position.price,
                        quantity: parseFloat(position.quantity.replace(',', '.')) || 0,
                    })),
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
                        <p className="text-muted-foreground text-sm">Объект: {project.name}. Отметьте выполненные работы и проверьте данные.</p>
                    </div>
                    <Button onClick={submit} disabled={processing || selectedCount === 0}>
                        Принять и сохранить
                    </Button>
                </div>

                {GROUPS.map((group) => (
                    <section key={group.title} className="border-sidebar-border/70 flex flex-col gap-4 rounded-xl border p-5">
                        <h2 className="text-lg font-medium">{group.title}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {group.fields.map((field) => (
                                <div key={field.key} className={`grid gap-2 ${field.multiline ? 'sm:col-span-2' : ''}`}>
                                    <Label htmlFor={field.key}>{field.label}</Label>
                                    {field.multiline ? (
                                        <textarea
                                            id={field.key}
                                            rows={2}
                                            className="border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                                            value={data[field.key]}
                                            onChange={(e) => setData(field.key, e.target.value)}
                                        />
                                    ) : (
                                        <Input id={field.key} value={data[field.key]} onChange={(e) => setData(field.key, e.target.value)} />
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                ))}

                <section className="border-sidebar-border/70 flex flex-col gap-4 rounded-xl border p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-3">
                        <h2 className="text-lg font-medium">Выполненные работы</h2>
                        <span className="text-muted-foreground text-sm">Отмечено позиций: {selectedCount}</span>
                    </div>

                    {positions.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Нет позиций с остатком: все работы по смете уже закрыты в предыдущих актах либо смета пуста.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-sidebar-border/70 border-b text-left">
                                        <th className="w-10 py-2 pr-2">
                                            <Checkbox
                                                aria-label="Выделить все позиции"
                                                checked={allChecked}
                                                onCheckedChange={(value) => toggleAll(value === true)}
                                            />
                                        </th>
                                        <th className="w-12 py-2 pr-2 text-right">№</th>
                                        <th className="py-2 pr-2">Наименование</th>
                                        <th className="py-2 pr-2">Ед.</th>
                                        <th className="py-2 pr-2 text-right">Остаток</th>
                                        <th className="py-2 pr-2 text-right">Цена</th>
                                        <th className="py-2 pr-2 text-right">Объём в акте</th>
                                        <th className="py-2 pr-2 text-right">Сумма</th>
                                    </tr>
                                </thead>
                                {groups.map((group) => {
                                    const groupSelected = group.rows.filter(({ position }) => position.checked).length;
                                    const groupChecked: boolean | 'indeterminate' =
                                        groupSelected === group.rows.length ? true : groupSelected === 0 ? false : 'indeterminate';
                                    const groupSum = group.rows.reduce((sum, { position }) => {
                                        if (!position.checked) return sum;
                                        const qty = parseFloat(position.quantity.replace(',', '.')) || 0;
                                        return sum + qty * position.price;
                                    }, 0);

                                    return (
                                        <tbody key={group.title}>
                                            <tr className="border-sidebar-border/70 bg-muted/40 border-b">
                                                <td className="py-2 pr-2">
                                                    <Checkbox
                                                        aria-label={`Выделить раздел «${group.title}»`}
                                                        checked={groupChecked}
                                                        onCheckedChange={(value) => toggleGroup(group.title, value === true)}
                                                    />
                                                </td>
                                                <td className="py-2 pr-2 font-medium" colSpan={7}>
                                                    {group.title}
                                                </td>
                                            </tr>
                                            {group.rows.map(({ index, position }) => {
                                                const qty = parseFloat(position.quantity.replace(',', '.')) || 0;
                                                const overflow = qty > position.remaining + 1e-6;
                                                return (
                                                    <tr key={position.key} className="border-sidebar-border/40 border-b align-top">
                                                        <td className="py-2 pr-2">
                                                            <Checkbox
                                                                checked={position.checked}
                                                                onCheckedChange={(value) => setPosition(index, { checked: value === true })}
                                                            />
                                                        </td>
                                                        <td className="text-muted-foreground py-2 pr-2 text-right tabular-nums">{position.number}</td>
                                                        <td className="py-2 pr-2">{position.name}</td>
                                                        <td className="py-2 pr-2">{position.unit}</td>
                                                        <td className="py-2 pr-2 text-right tabular-nums">{num(position.remaining)}</td>
                                                        <td className="py-2 pr-2 text-right tabular-nums">{money(position.price)}</td>
                                                        <td className="py-2 pr-2 text-right">
                                                            <Input
                                                                inputMode="decimal"
                                                                disabled={!position.checked}
                                                                className={`ml-auto w-24 text-right tabular-nums ${overflow ? 'border-destructive' : ''}`}
                                                                value={position.quantity}
                                                                onChange={(e) => setPosition(index, { quantity: e.target.value })}
                                                            />
                                                        </td>
                                                        <td className="py-2 pr-2 text-right tabular-nums">
                                                            {position.checked ? money(qty * position.price) : '—'}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                            <tr className="border-sidebar-border/70 border-b">
                                                <td />
                                                <td className="text-muted-foreground py-2 pr-2 text-right text-xs" colSpan={6}>
                                                    Итого по разделу
                                                </td>
                                                <td className="py-2 pr-2 text-right font-medium tabular-nums">{money(groupSum)}</td>
                                            </tr>
                                        </tbody>
                                    );
                                })}
                            </table>
                        </div>
                    )}
                </section>

                <section className="border-sidebar-border/70 flex flex-col gap-4 rounded-xl border p-5">
                    <h2 className="text-lg font-medium">Итог</h2>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label>Сумма выполненных работ, ₽</Label>
                            <div className="tabular-nums text-lg font-medium">{money(worksSum)}</div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="advance_percent">Аванс, %</Label>
                            <Input
                                id="advance_percent"
                                inputMode="decimal"
                                className="text-right tabular-nums"
                                value={advance}
                                onChange={(e) => setAdvance(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>К оплате (за вычетом аванса), ₽</Label>
                            <div className="tabular-nums text-lg font-semibold">{money(cost)}</div>
                        </div>
                    </div>
                </section>

                <div className="flex justify-end">
                    <Button onClick={submit} disabled={processing || selectedCount === 0}>
                        Принять и сохранить
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
