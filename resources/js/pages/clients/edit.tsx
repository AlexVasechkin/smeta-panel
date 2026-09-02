import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import ClientForm, { type ClientFormData } from './client-form';

export default function ClientEdit({
    client,
    clientTypes,
    passportTypes,
}: {
    client: ClientFormData;
    clientTypes: Option[];
    passportTypes: Option[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Клиенты', href: '/clients' },
        { title: client.name, href: `/clients/${client.id}` },
        { title: 'Редактирование', href: `/clients/${client.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Редактирование: ${client.name}`} />
            <div className="p-4">
                <h1 className="mb-6 text-xl font-semibold">Редактирование клиента</h1>
                <ClientForm client={client} clientTypes={clientTypes} passportTypes={passportTypes} />
            </div>
        </AppLayout>
    );
}
