import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm, { type ProjectFormData } from './project-form';

export default function ProjectEdit({
    project,
    clients,
    cities,
    statuses,
}: {
    project: ProjectFormData;
    clients: Option<number>[];
    cities: Option<number>[];
    statuses: Option[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Объекты', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Редактирование', href: `/projects/${project.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Редактирование: ${project.name}`} />
            <div className="p-4">
                <h1 className="mb-6 text-xl font-semibold">Редактирование объекта</h1>
                <ProjectForm project={project} clients={clients} cities={cities} statuses={statuses} />
            </div>
        </AppLayout>
    );
}
