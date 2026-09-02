import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import ProjectForm from './project-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Объекты', href: '/projects' },
    { title: 'Новый объект', href: '/projects/create' },
];

export default function ProjectCreate({
    clients,
    cities,
    statuses,
    selectedClientId,
}: {
    clients: Option<number>[];
    cities: Option<number>[];
    statuses: Option[];
    selectedClientId: number | null;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новый объект" />
            <div className="p-4">
                <h1 className="mb-6 text-xl font-semibold">Новый объект</h1>
                <ProjectForm clients={clients} cities={cities} statuses={statuses} defaultClientId={selectedClientId} />
            </div>
        </AppLayout>
    );
}
