import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Field = { key: string; label: string; numeric?: boolean; multiline?: boolean };
type Group = { title: string; fields: Field[] };

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
        ],
    },
    {
        title: 'Подрядчик (организация) — принимает средства',
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
        title: 'Заказчик (клиент) — передаёт средства',
        fields: [
            { key: 'customer_surname', label: 'Фамилия' },
            { key: 'customer_name', label: 'Имя' },
            { key: 'customer_father_name', label: 'Отчество' },
            ...passportFields('customer_'),
            { key: 'customer_phone', label: 'Телефон' },
            { key: 'customer_email', label: 'Email' },
        ],
    },
    {
        title: 'Платёж',
        fields: [
            { key: 'cost', label: 'Сумма, ₽', numeric: true },
            { key: 'purpose', label: 'Назначение платежа', multiline: true },
        ],
    },
];

export default function DocumentsCashAct({
    documentType,
    project,
    payload,
}: {
    documentType: { value: string; label: string };
    project: { id: number; name: string };
    payload: Record<string, string | number>;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Документы', href: '/documents' },
        { title: 'Акт приёма-передачи денежных средств', href: '#' },
    ];

    const initial: Record<string, string> = {};
    for (const group of GROUPS) {
        for (const field of group.fields) {
            initial[field.key] = payload[field.key] !== undefined && payload[field.key] !== null ? String(payload[field.key]) : '';
        }
    }

    const form = useForm(initial);
    const { data, setData, processing } = form;

    const submit = () => {
        form.transform((current) => ({ type: documentType.value, project_id: project.id, payload: current }));
        form.post(route('documents.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${documentType.label} — предпросмотр`} />

            <div className="flex flex-col gap-8 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{documentType.label}</h1>
                        <p className="text-muted-foreground text-sm">Объект: {project.name}. Проверьте данные перед сохранением.</p>
                    </div>
                    <Button onClick={submit} disabled={processing}>
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
                                            rows={3}
                                            className="border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                                            value={data[field.key]}
                                            onChange={(e) => setData(field.key, e.target.value)}
                                        />
                                    ) : (
                                        <Input
                                            id={field.key}
                                            inputMode={field.numeric ? 'decimal' : undefined}
                                            className={field.numeric ? 'text-right tabular-nums' : undefined}
                                            value={data[field.key]}
                                            onChange={(e) => setData(field.key, e.target.value)}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                ))}

                <div className="flex justify-end">
                    <Button onClick={submit} disabled={processing}>
                        Принять и сохранить
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
