import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import PartnerForm from './partner-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Организации', href: '/partners' },
    { title: 'Новая организация', href: '/partners/create' },
];

export default function PartnerCreate({ organizationTypes, passportTypes }: { organizationTypes: Option[]; passportTypes: Option[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Новая организация" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PartnerForm organizationTypes={organizationTypes} passportTypes={passportTypes} />
            </div>
        </AppLayout>
    );
}
