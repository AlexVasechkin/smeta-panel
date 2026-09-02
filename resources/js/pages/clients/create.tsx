import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import ClientForm from './client-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Клиенты', href: '/clients' },
    { title: 'Новый клиент', href: '/clients/create' },
];

export default function ClientCreate({ clientTypes, passportTypes }: { clientTypes: Option[]; passportTypes: Option[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новый клиент" />
            <div className="p-4">
                <h1 className="mb-6 text-xl font-semibold">Новый клиент</h1>
                <ClientForm clientTypes={clientTypes} passportTypes={passportTypes} />
            </div>
        </AppLayout>
    );
}
