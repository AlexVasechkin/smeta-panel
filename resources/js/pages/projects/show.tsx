import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Download, FileText, Pencil, Plus } from 'lucide-react';

interface ProjectDetail {
    id: number;
    name: string;
    order_number: string | null;
    city: string | null;
    address: string;
    area: string | null;
    rooms: number | null;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    notes: string | null;
    summary: ProjectSummary;
    client: { id: number; name: string; phone: string | null; email: string | null } | null;
    documents: DocumentRow[];
}

interface ProjectSummary {
    cash_received: number;
    contract_total: number;
    contract_total_discounted: number;
    positions_total: number;
    positions_closed: number;
}

const rubles = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 2,
});

function SummaryStat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 text-2xl font-semibold tracking-tight tabular-nums">{value}</dd>
            {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
        </div>
    );
}

interface DocumentRow {
    id: number;
    type_label: string;
    title: string;
    created_at: string | null;
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{value || '—'}</dd>
        </div>
    );
}

export default function ProjectShow({ project }: { project: ProjectDetail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Объекты', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <h1 className="text-xl font-semibold">{project.name}</h1>
                        <StatusBadge label={project.status_label} color={project.status_color} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Button asChild>
                            <Link href={`/projects/${project.id}/estimate`}>
                                <FileText className="size-4" />
                                Смета
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('projects.edit', project.id)}>
                                <Pencil className="size-4" />
                                Редактировать
                            </Link>
                        </Button>
                    </div>
                </div>

                <dl className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Номер договора" value={project.order_number} />
                    <Field label="Город" value={project.city} />
                    <Field label="Адрес" value={project.address} />
                    <Field label="Площадь" value={project.area ? `${project.area} м²` : null} />
                    <Field label="Комнат" value={project.rooms ? String(project.rooms) : null} />
                    <Field label="Дата начала" value={project.start_date} />
                    <Field label="Заметки" value={project.notes} />
                </dl>

                <div>
                    <h2 className="mb-3 text-lg font-medium">Сводка</h2>
                    <dl className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryStat
                            label="Сумма по договору"
                            value={rubles.format(project.summary.contract_total)}
                            hint="Работы + материалы + доп."
                        />
                        <SummaryStat
                            label="Сумма по договору со скидкой"
                            value={rubles.format(project.summary.contract_total_discounted)}
                            hint="С учётом скидки на работы"
                        />
                        <SummaryStat
                            label="Получено по объекту"
                            value={rubles.format(project.summary.cash_received)}
                            hint="По актам приёма-передачи денег"
                        />
                        <SummaryStat
                            label="Закрыто позиций"
                            value={`${project.summary.positions_closed} из ${project.summary.positions_total}`}
                            hint="Полностью закрытые позиции"
                        />
                    </dl>
                </div>

                <div>
                    <h2 className="mb-3 text-lg font-medium">Клиент</h2>
                    {project.client ? (
                        <dl className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground text-xs">Имя</dt>
                                <dd className="mt-0.5">
                                    <Link href={route('clients.show', project.client.id)} className="font-medium hover:underline">
                                        {project.client.name}
                                    </Link>
                                </dd>
                            </div>
                            <Field label="Телефон" value={project.client.phone} />
                            <Field label="Email" value={project.client.email} />
                        </dl>
                    ) : (
                        <p className="text-muted-foreground text-sm">Клиент не указан</p>
                    )}
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-lg font-medium">Документы</h2>
                        <Button asChild variant="outline" size="sm">
                            <Link href={route('documents.preview', { type: 'preliminary_estimate', project: project.id })}>
                                <Plus className="size-4" />
                                Предварительная смета
                            </Link>
                        </Button>
                    </div>

                    {project.documents.length > 0 ? (
                        <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Тип</th>
                                        <th className="px-4 py-2 font-medium">Создан</th>
                                        <th className="px-4 py-2 text-right font-medium">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {project.documents.map((document) => (
                                        <tr key={document.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                            <td className="px-4 py-2 font-medium">{document.type_label}</td>
                                            <td className="text-muted-foreground px-4 py-2">{document.created_at ?? '—'}</td>
                                            <td className="px-4 py-2 text-right">
                                                <a
                                                    href={route('documents.download', document.id)}
                                                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
                                                    title="Скачать"
                                                >
                                                    <Download className="size-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">Документов по объекту пока нет.</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
