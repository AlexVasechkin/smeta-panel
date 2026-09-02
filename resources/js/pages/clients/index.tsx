import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Клиенты', href: '/clients' }];

interface ClientRow {
    id: number;
    type: string;
    type_label: string;
    name: string;
    phone: string | null;
    email: string | null;
    projects_count: number;
}

export default function ClientsIndex({ clients, filters }: { clients: Paginated<ClientRow>; filters: { search: string } }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('clients.index'), { search }, { preserveState: true, replace: true });
    };

    const destroy = (client: ClientRow) => {
        if (confirm(`Удалить клиента «${client.name}»? Связанные объекты также будут удалены.`)) {
            router.delete(route('clients.destroy', client.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Клиенты" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form onSubmit={submit} className="flex gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Поиск по имени, телефону, email"
                            className="w-72"
                        />
                        <Button type="submit" variant="secondary">
                            Найти
                        </Button>
                    </form>

                    <Button asChild>
                        <Link href={route('clients.create')}>
                            <Plus className="size-4" />
                            Добавить клиента
                        </Link>
                    </Button>
                </div>

                <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Имя</th>
                                <th className="px-4 py-3 font-medium">Тип</th>
                                <th className="px-4 py-3 font-medium">Телефон</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Объектов</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.data.map((client) => (
                                <tr key={client.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={route('clients.show', client.id)} className="font-medium hover:underline">
                                            {client.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{client.type_label}</td>
                                    <td className="text-muted-foreground px-4 py-3">{client.phone ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{client.email ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{client.projects_count}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon">
                                                <Link href={route('clients.edit', client.id)} aria-label="Редактировать">
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => destroy(client)} aria-label="Удалить">
                                                <Trash2 className="size-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {clients.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                        Клиенты не найдены
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={clients.links} />
            </div>
        </AppLayout>
    );
}
