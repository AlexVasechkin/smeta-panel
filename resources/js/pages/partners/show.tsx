import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { type DirectorPassport } from './partner-form';

interface Partner {
    id: number;
    type: string | null;
    type_label: string | null;
    type_full_label: string | null;
    type_color: string | null;
    registration_number_label: string;
    name: string | null;
    legal_name: string | null;
    inn: string | null;
    kpp: string | null;
    ogrn: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    director: string | null;
    bank_name: string | null;
    bank_bik: string | null;
    bank_account: string | null;
    bank_corr_account: string | null;
    passport: DirectorPassport;
}

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[14rem_1fr] sm:gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="border-sidebar-border/70 rounded-xl border p-4">
            <h2 className="mb-3 text-sm font-medium">{title}</h2>
            <dl className="grid gap-2 text-sm">{children}</dl>
        </section>
    );
}

export default function PartnerShow({ partner }: { partner: Partner }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Организации', href: '/partners' },
        { title: partner.name ?? 'Организация', href: `/partners/${partner.id}` },
    ];

    const isIp = partner.type === 'ip';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={partner.name ?? 'Организация'} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-semibold">{partner.name ?? '—'}</h1>
                        {partner.type_label && <StatusBadge label={partner.type_label} color={partner.type_color ?? 'zinc'} />}
                    </div>

                    <Button asChild variant="outline">
                        <Link href={route('partners.edit', partner.id)}>
                            <Pencil className="size-4" />
                            Редактировать
                        </Link>
                    </Button>
                </div>

                <div className="grid max-w-3xl gap-4">
                    <Section title="Реквизиты">
                        <Row label="Форма" value={partner.type_full_label} />
                        <Row label="Полное наименование" value={partner.legal_name} />
                        <Row label="ИНН" value={partner.inn} />
                        {!isIp && <Row label="КПП" value={partner.kpp} />}
                        <Row label={partner.registration_number_label} value={partner.ogrn} />
                        <Row label={isIp ? 'Адрес' : 'Юридический адрес'} value={partner.address} />
                        <Row label="Телефон" value={partner.phone} />
                        <Row label="Email" value={partner.email} />
                    </Section>

                    <Section title={isIp ? 'Предприниматель' : 'Руководитель'}>
                        <Row label="ФИО" value={partner.director} />
                        <Row label="Паспорт" value={partner.passport.type} />
                        <Row label="Серия и номер" value={[partner.passport.series, partner.passport.number].filter(Boolean).join(' ')} />
                        <Row label="Кем выдан" value={partner.passport.issued_by} />
                        <Row label="Дата выдачи" value={partner.passport.issued_at} />
                        <Row label="Адрес регистрации" value={partner.passport.registration_address} />
                    </Section>

                    <Section title="Банковские реквизиты">
                        <Row label="Банк" value={partner.bank_name} />
                        <Row label="БИК" value={partner.bank_bik} />
                        <Row label="Расчётный счёт" value={partner.bank_account} />
                        <Row label="Корреспондентский счёт" value={partner.bank_corr_account} />
                    </Section>
                </div>
            </div>
        </AppLayout>
    );
}
