import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

interface ClientProject {
    id: number;
    name: string;
    address: string;
    status: string;
    status_label: string;
    status_color: string;
}

interface Passport {
    type: string | null;
    series: string | null;
    number: string | null;
    issued_by: string | null;
    issued_at: string | null;
    registration_address: string | null;
}

interface ClientDetail {
    id: number;
    type: string;
    type_label: string;
    name: string;
    surname: string | null;
    father_name: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    passport: Passport | null;
    projects: ClientProject[];
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{value || '—'}</dd>
        </div>
    );
}

export default function ClientShow({ client }: { client: ClientDetail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Клиенты', href: '/clients' },
        { title: client.name, href: `/clients/${client.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={client.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{client.name}</h1>
                        <p className="text-muted-foreground text-sm">{client.type_label}</p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={route('clients.edit', client.id)}>
                            <Pencil className="size-4" />
                            Редактировать
                        </Link>
                    </Button>
                </div>

                <dl className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    {client.type === 'individual' && (
                        <>
                            <Field label="Фамилия" value={client.surname} />
                            <Field label="Отчество" value={client.father_name} />
                        </>
                    )}
                    <Field label="Телефон" value={client.phone} />
                    <Field label="Email" value={client.email} />
                    <Field label="Заметки" value={client.notes} />
                </dl>

                {client.passport && (
                    <div>
                        <h2 className="mb-3 text-lg font-medium">Паспортные данные</h2>
                        <dl className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <Field label="Паспорт" value={client.passport.type} />
                            <Field label="Серия" value={client.passport.series} />
                            <Field label="Номер" value={client.passport.number} />
                            <Field label="Кем выдан" value={client.passport.issued_by} />
                            <Field label="Дата выдачи" value={client.passport.issued_at} />
                            <Field label="Адрес регистрации" value={client.passport.registration_address} />
                        </dl>
                    </div>
                )}

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-lg font-medium">Объекты</h2>
                        <Button asChild size="sm">
                            <Link href={`${route('projects.create')}?client_id=${client.id}`}>
                                <Plus className="size-4" />
                                Добавить объект
                            </Link>
                        </Button>
                    </div>

                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Название</th>
                                    <th className="px-4 py-3 font-medium">Адрес</th>
                                    <th className="px-4 py-3 font-medium">Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                {client.projects.map((project) => (
                                    <tr key={project.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                        <td className="px-4 py-3">
                                            <Link href={route('projects.show', project.id)} className="font-medium hover:underline">
                                                {project.name}
                                            </Link>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{project.address}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge label={project.status_label} color={project.status_color} />
                                        </td>
                                    </tr>
                                ))}
                                {client.projects.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="text-muted-foreground px-4 py-10 text-center">
                                            У клиента пока нет объектов
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
