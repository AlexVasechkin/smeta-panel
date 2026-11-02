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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Организации', href: '/partners' }];

interface PartnerRow {
    id: number;
    type: string | null;
    type_label: string | null;
    type_color: string | null;
    name: string | null;
    inn: string | null;
    director: string | null;
    phone: string | null;
}

export default function PartnersIndex({
    partners,
    filters,
    organizationTypes,
}: {
    partners: Paginated<PartnerRow>;
    filters: { search: string; type: string };
    organizationTypes: Option[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    // В Select нельзя передать пустое значение, поэтому «все формы» — это 'all'.
    const [type, setType] = useState(filters.type || 'all');

    const reload = (params: { search: string; type: string }) =>
        router.get(
            route('partners.index'),
            { search: params.search, type: params.type === 'all' ? '' : params.type },
            { preserveState: true, replace: true },
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        reload({ search, type });
    };

    const changeType = (value: string) => {
        setType(value);
        reload({ search, type: value });
    };

    const destroy = (partner: PartnerRow) => {
        if (confirm(`Удалить «${partner.name}» из справочника?`)) {
            router.delete(route('partners.destroy', partner.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Организации" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form onSubmit={submit} className="flex flex-wrap gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Поиск по наименованию или ИНН"
                            className="w-72"
                        />

                        <Select value={type} onValueChange={changeType}>
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Все формы</SelectItem>
                                {organizationTypes.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button type="submit" variant="secondary">
                            Найти
                        </Button>
                    </form>

                    <Button asChild>
                        <Link href={route('partners.create')}>
                            <Plus className="size-4" />
                            Добавить организацию
                        </Link>
                    </Button>
                </div>

                <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Наименование</th>
                                <th className="px-4 py-3 font-medium">Форма</th>
                                <th className="px-4 py-3 font-medium">ИНН</th>
                                <th className="px-4 py-3 font-medium">Руководитель</th>
                                <th className="px-4 py-3 font-medium">Телефон</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {partners.data.map((partner) => (
                                <tr key={partner.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={route('partners.show', partner.id)} className="font-medium hover:underline">
                                            {partner.name ?? '—'}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {partner.type_label ? (
                                            <StatusBadge label={partner.type_label} color={partner.type_color ?? 'zinc'} />
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{partner.inn ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{partner.director ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{partner.phone ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button asChild variant="ghost" size="icon">
                                                <Link href={route('partners.edit', partner.id)} aria-label="Редактировать">
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="icon" onClick={() => destroy(partner)} aria-label="Удалить">
                                                <Trash2 className="size-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {partners.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                        Организации не найдены
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={partners.links} />
            </div>
        </AppLayout>
    );
}
