import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head } from '@inertiajs/react';
import PartnerForm, { type PartnerFormData } from './partner-form';

export default function PartnerEdit({
    partner,
    organizationTypes,
    passportTypes,
}: {
    partner: PartnerFormData;
    organizationTypes: Option[];
    passportTypes: Option[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Организации', href: '/partners' },
        { title: partner.name ?? 'Организация', href: `/partners/${partner.id}` },
        { title: 'Редактирование', href: `/partners/${partner.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={partner.name ?? 'Организация'} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <PartnerForm partner={partner} organizationTypes={organizationTypes} passportTypes={passportTypes} />
            </div>
        </AppLayout>
    );
}
