import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import TemplateForm, { type TemplateFormData } from './template-form';

export default function DocumentTemplateEdit({ template }: { template: TemplateFormData }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Шаблоны документов', href: '/document-templates' },
        { title: template.name ?? 'Шаблон', href: `/document-templates/${template.id}` },
        { title: 'Редактирование', href: `/document-templates/${template.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={template.name ?? 'Шаблон'} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TemplateForm template={template} />
            </div>
        </AppLayout>
    );
}
