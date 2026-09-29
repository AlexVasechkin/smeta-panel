import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface MonthlyTotalItem {
    key: string;
    title: string;
    hint: string;
    amount: number;
}

interface MonthlyTotals {
    month_label: string;
    items: MonthlyTotalItem[];
}

const rubles = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 2,
});

export default function Dashboard({ monthlyTotals }: { monthlyTotals: MonthlyTotals }) {
    // Заполняем ряд из трёх ячеек: карточки показателей + плейсхолдеры до 3 штук.
    const placeholders = Math.max(0, 3 - monthlyTotals.items.length);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {monthlyTotals.items.map((item) => (
                        <div
                            key={item.key}
                            className="border-sidebar-border/70 dark:border-sidebar-border relative flex aspect-video flex-col justify-between overflow-hidden rounded-xl border p-5"
                        >
                            <div className="text-muted-foreground text-sm font-medium">
                                {item.title} за {monthlyTotals.month_label}
                            </div>
                            <div className="text-3xl font-semibold tracking-tight tabular-nums">
                                {rubles.format(item.amount)}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                {item.hint} за текущий месяц
                            </div>
                        </div>
                    ))}
                    {Array.from({ length: placeholders }).map((_, i) => (
                        <div
                            key={`placeholder-${i}`}
                            className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
                        >
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    ))}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}
