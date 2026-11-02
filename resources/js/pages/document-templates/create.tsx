import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import TemplateForm from './template-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Шаблоны документов', href: '/document-templates' },
    { title: 'Новый шаблон', href: '/document-templates/create' },
];

export default function DocumentTemplateCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новый шаблон" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TemplateForm />
            </div>
        </AppLayout>
    );
}
