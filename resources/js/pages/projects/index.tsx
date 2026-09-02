import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Объекты', href: '/projects' }];

interface ProjectRow {
    id: number;
    name: string;
    address: string;
    area: string | null;
    status: string;
    status_label: string;
    status_color: string;
    client: { id: number; name: string } | null;
}

const ALL = 'all';

export default function ProjectsIndex({
    projects,
    filters,
    statuses,
}: {
    projects: Paginated<ProjectRow>;
    filters: { search: string; status: string };
    statuses: Option[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (params: { search?: string; status?: string }) => {
        router.get(
            route('projects.index'),
            { search: params.search ?? search, status: params.status ?? filters.status },
            { preserveState: true, replace: true },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        apply({});
    };

    const destroy = (project: ProjectRow) => {
        if (confirm(`Удалить объект «${project.name}»?`)) {
            router.delete(route('projects.destroy', project.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Объекты" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        <form onSubmit={submit} className="flex gap-2">
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Поиск по названию или адресу"
                                className="w-64"
                            />
                            <Button type="submit" variant="secondary">
                                Найти
                            </Button>
                        </form>

                        <Select value={filters.status || ALL} onValueChange={(value) => apply({ status: value === ALL ? '' : value })}>
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Все статусы" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все статусы</SelectItem>
                                {statuses.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Button asChild>
                        <Link href={route('projects.create')}>
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
                                <th className="px-4 py-3 font-medium">Клиент</th>
                                <th className="px-4 py-3 font-medium">Адрес</th>
                                <th className="px-4 py-3 font-medium">Площадь</th>
                                <th className="px-4 py-3 font-medium">Статус</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {projects.data.map((project) => (
                                <tr key={project.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={route('projects.show', project.id)} className="font-medium hover:underline">
                                            {project.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {project.client ? (
                                            <Link href={route('clients.show', project.client.id)} className="hover:underline">
                                                {project.client.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{project.address}</td>
                                    <td className="text-muted-foreground px-4 py-3">{project.area ? `${project.area} м²` : '—'}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge label={project.status_label} color={project.status_color} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon">
                                                <Link href={route('projects.edit', project.id)} aria-label="Редактировать">
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => destroy(project)} aria-label="Удалить">
                                                <Trash2 className="size-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {projects.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-10 text-center">
                                        Объекты не найдены
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={projects.links} />
            </div>
        </AppLayout>
    );
}
