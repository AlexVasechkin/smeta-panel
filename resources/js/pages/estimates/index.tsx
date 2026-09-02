import Pagination from '@/components/pagination';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Сметы', href: '/estimates' }];

interface EstimateRow {
    id: number;
    project: { id: number; name: string };
    client: string | null;
    works_total: number;
    materials_total: number;
    total: number;
    updated_at: string | null;
}

const formatMoney = (n: number): string => n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';

export default function EstimatesIndex({ estimates }: { estimates: Paginated<EstimateRow> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Сметы" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Сметы</h1>

                <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Объект</th>
                                <th className="px-4 py-3 font-medium">Клиент</th>
                                <th className="px-4 py-3 text-right font-medium">Работы</th>
                                <th className="px-4 py-3 text-right font-medium">Материалы</th>
                                <th className="px-4 py-3 text-right font-medium">Итого</th>
                                <th className="px-4 py-3 font-medium">Обновлено</th>
                            </tr>
                        </thead>
                        <tbody>
                            {estimates.data.map((estimate) => (
                                <tr key={estimate.id} className="border-sidebar-border/70 hover:bg-muted/30 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={`/projects/${estimate.project.id}/estimate`} className="font-medium hover:underline">
                                            {estimate.project.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{estimate.client ?? '—'}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatMoney(estimate.works_total)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatMoney(estimate.materials_total)}</td>
                                    <td className="px-4 py-3 text-right font-medium tabular-nums">{formatMoney(estimate.total)}</td>
                                    <td className="text-muted-foreground px-4 py-3">{estimate.updated_at ?? '—'}</td>
                                </tr>
                            ))}
                            {estimates.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                        Сметы пока не создавались. Откройте объект и нажмите «Смета».
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={estimates.links} />
            </div>
        </AppLayout>
    );
}
