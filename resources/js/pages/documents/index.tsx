import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileStack, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Документы', href: '/documents' }];

interface DocumentRow {
    id: number;
    name: string;
    created_at: string | null;
}

interface TypeGroup {
    type_label: string;
    documents: DocumentRow[];
}

interface ProjectGroup {
    id: number;
    name: string;
    client: string | null;
    types: TypeGroup[];
}

export default function DocumentsIndex({ projects }: { projects: ProjectGroup[] }) {
    const remove = (id: number) => {
        if (confirm('Удалить документ? Файл будет удалён безвозвратно.')) {
            router.delete(route('documents.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Документы" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Документы</h1>
                    <Button asChild>
                        <Link href={route('documents.create')}>
                            <Plus className="size-4" />
                            Создать документ
                        </Link>
                    </Button>
                </div>

                {projects.length === 0 ? (
                    <div className="border-sidebar-border/70 text-muted-foreground rounded-xl border px-4 py-12 text-center">
                        <FileStack className="mx-auto mb-2 size-8 opacity-40" />
                        Документов пока нет. Нажмите «Создать документ».
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        {projects.map((project) => (
                            <section key={project.id} className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                                <div className="border-sidebar-border/70 bg-muted/50 flex flex-wrap items-baseline gap-x-2 border-b px-4 py-3">
                                    <Link href={route('projects.show', project.id)} className="font-semibold hover:underline">
                                        {project.name}
                                    </Link>
                                    {project.client && <span className="text-muted-foreground text-sm">· {project.client}</span>}
                                </div>

                                <div className="flex flex-col">
                                    {project.types.map((group) => (
                                        <div key={group.type_label} className="border-sidebar-border/70 border-b last:border-b-0">
                                            <div className="text-muted-foreground bg-muted/20 px-4 pt-2 pb-0.5 text-xs font-bold tracking-wide uppercase">
                                                {group.type_label}
                                            </div>
                                            <ul>
                                                {group.documents.map((document) => (
                                                    <li
                                                        key={document.id}
                                                        className="border-sidebar-border/40 hover:bg-muted/30 flex items-center justify-between gap-4 border-t py-2.5 pr-4 pl-8 first:border-t-0"
                                                    >
                                                        <div className="flex min-w-0 flex-col">
                                                            <span className="truncate text-sm font-medium">{document.name}</span>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            <span className="text-muted-foreground hidden text-xs sm:inline">
                                                                {document.created_at ?? '—'}
                                                            </span>
                                                            <a
                                                                href={route('documents.download', document.id)}
                                                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
                                                                title="Скачать"
                                                            >
                                                                <Download className="size-4" />
                                                            </a>
                                                            <button
                                                                type="button"
                                                                onClick={() => remove(document.id)}
                                                                className="text-muted-foreground hover:text-red-600"
                                                                title="Удалить"
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </button>
                                                        </div>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
