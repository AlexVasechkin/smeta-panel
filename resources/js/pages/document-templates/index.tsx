import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Шаблоны документов', href: '/document-templates' }];

interface TemplateRow {
    id: number;
    name: string;
    description: string | null;
    size: number;
    field_count: number;
    table_count: number;
    warning_count: number;
    updated_at: string | null;
}

export default function DocumentTemplatesIndex({ templates, filters }: { templates: Paginated<TemplateRow>; filters: { search: string } }) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('document-templates.index'), { search }, { preserveState: true, replace: true });
    };

    const destroy = (template: TemplateRow) => {
        if (confirm(`Удалить шаблон «${template.name}»?`)) {
            router.delete(route('document-templates.destroy', template.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Шаблоны документов" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form onSubmit={submit} className="flex flex-wrap gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Поиск по названию или описанию"
                            className="w-72"
                        />
                        <Button type="submit" variant="secondary">
                            Найти
                        </Button>
                    </form>

                    <Button asChild>
                        <Link href={route('document-templates.create')}>
                            <Plus className="size-4" />
                            Загрузить шаблон
                        </Link>
                    </Button>
                </div>

                <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Название</th>
                                <th className="px-4 py-3 font-medium">Поля</th>
                                <th className="px-4 py-3 font-medium">Таблицы</th>
                                <th className="px-4 py-3 font-medium">Размер</th>
                                <th className="px-4 py-3 font-medium">Обновлён</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.data.map((template) => (
                                <tr key={template.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Link href={route('document-templates.show', template.id)} className="font-medium hover:underline">
                                                {template.name}
                                            </Link>
                                            {template.warning_count > 0 && (
                                                <span
                                                    className="text-amber-600 dark:text-amber-400"
                                                    title={`Замечаний к разметке: ${template.warning_count}`}
                                                >
                                                    <TriangleAlert className="size-4" />
                                                </span>
                                            )}
                                        </div>
                                        {template.description && <p className="text-muted-foreground mt-0.5 text-xs">{template.description}</p>}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{template.field_count}</td>
                                    <td className="text-muted-foreground px-4 py-3">{template.table_count}</td>
                                    <td className="text-muted-foreground px-4 py-3">{formatBytes(template.size)}</td>
                                    <td className="text-muted-foreground px-4 py-3">{template.updated_at ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon">
                                                <a href={route('document-templates.download', template.id)} aria-label="Скачать">
                                                    <Download className="size-4" />
                                                </a>
                                            </Button>
                                            <Button asChild variant="ghost" size="icon">
                                                <Link href={route('document-templates.edit', template.id)} aria-label="Редактировать">
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => destroy(template)} aria-label="Удалить">
                                                <Trash2 className="size-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {templates.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                        Шаблоны не найдены
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={templates.links} />
            </div>
        </AppLayout>
    );
}
