import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Download, Pencil, TriangleAlert } from 'lucide-react';

interface TemplateTable {
    name: string;
    columns: string[];
}

interface Template {
    id: number;
    name: string;
    description: string | null;
    original_name: string;
    size: number;
    checksum: string;
    fields: string[];
    tables: TemplateTable[];
    warnings: string[];
    updated_at: string | null;
}

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[14rem_1fr] sm:gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

function Placeholder({ name }: { name: string }) {
    return <code className="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">{`{{${name}}}`}</code>;
}

export default function DocumentTemplateShow({ template }: { template: Template }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Шаблоны документов', href: '/document-templates' },
        { title: template.name, href: `/document-templates/${template.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={template.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{template.name}</h1>
                        {template.description && <p className="text-muted-foreground mt-1 text-sm">{template.description}</p>}
                    </div>

                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a href={route('document-templates.download', template.id)}>
                                <Download className="size-4" />
                                Скачать
                            </a>
                        </Button>
                        <Button asChild>
                            <Link href={route('document-templates.edit', template.id)}>
                                <Pencil className="size-4" />
                                Редактировать
                            </Link>
                        </Button>
                    </div>
                </div>

                {template.warnings.length > 0 && (
                    <Alert>
                        <TriangleAlert className="size-4" />
                        <AlertTitle>Замечания к разметке</AlertTitle>
                        <AlertDescription>
                            <ul className="mt-1 list-disc space-y-1 pl-5">
                                {template.warnings.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <section className="border-sidebar-border/70 rounded-xl border p-4">
                    <h2 className="mb-3 font-medium">Файл</h2>
                    <dl className="grid gap-2 text-sm">
                        <Row label="Исходное имя" value={template.original_name} />
                        <Row label="Размер" value={formatBytes(template.size)} />
                        <Row label="Контрольная сумма" value={template.checksum.slice(0, 16) + '…'} />
                        <Row label="Обновлён" value={template.updated_at} />
                    </dl>
                </section>

                <section className="border-sidebar-border/70 rounded-xl border p-4">
                    <h2 className="mb-1 font-medium">Поля</h2>
                    <p className="text-muted-foreground mb-3 text-sm">Одиночные плейсхолдеры: подставляется одно значение на документ.</p>

                    {template.fields.length > 0 ? (
                        <div className="flex flex-wrap gap-1.5">
                            {template.fields.map((field) => (
                                <Placeholder key={field} name={field} />
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">Одиночных полей в шаблоне нет.</p>
                    )}
                </section>

                <section className="border-sidebar-border/70 rounded-xl border p-4">
                    <h2 className="mb-1 font-medium">Таблицы</h2>
                    <p className="text-muted-foreground mb-3 text-sm">
                        Группы вида <code className="font-mono">{'{{группа[].колонка}}'}</code>: строка таблицы повторится столько раз, сколько будет
                        передано записей.
                    </p>

                    {template.tables.length > 0 ? (
                        <div className="flex flex-col gap-3">
                            {template.tables.map((table) => (
                                <div key={table.name}>
                                    <p className="mb-1.5 text-sm font-medium">
                                        {table.name}
                                        <span className="text-muted-foreground ml-2 font-normal">{table.columns.length} колонок</span>
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {table.columns.map((column) => (
                                            <Placeholder key={column} name={`${table.name}[].${column}`} />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">Повторяющихся таблиц в шаблоне нет.</p>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
