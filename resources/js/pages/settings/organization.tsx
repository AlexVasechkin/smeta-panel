import { type BreadcrumbItem, type Option } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organization settings',
        href: '/settings/organization',
    },
];

interface Organization {
    id: number;
    name: string | null;
    legal_name: string | null;
    inn: string | null;
    kpp: string | null;
    ogrn: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    director_surname: string | null;
    director_name: string | null;
    director_father_name: string | null;
    bank_name: string | null;
    bank_bik: string | null;
    bank_account: string | null;
    bank_corr_account: string | null;
}

interface DirectorPassport {
    type: string | null;
    series: string | null;
    number: string | null;
    issued_by: string | null;
    issued_at: string | null;
    registration_address: string | null;
}

interface OrganizationForm {
    name: string;
    legal_name: string;
    inn: string;
    kpp: string;
    ogrn: string;
    address: string;
    phone: string;
    email: string;
    director_surname: string;
    director_name: string;
    director_father_name: string;
    bank_name: string;
    bank_bik: string;
    bank_account: string;
    bank_corr_account: string;
    passport: {
        type: string;
        series: string;
        number: string;
        issued_by: string;
        issued_at: string;
        registration_address: string;
    };
}

type TextField = { name: Exclude<keyof OrganizationForm, 'passport'>; label: string; type?: string };

// Реквизиты организации (без блока «Руководитель», он вынесен в отдельную группу).
const identityFields: TextField[] = [
    { name: 'name', label: 'Краткое наименование' },
    { name: 'legal_name', label: 'Полное наименование' },
];

const requisiteFields: TextField[] = [
    { name: 'inn', label: 'ИНН' },
    { name: 'kpp', label: 'КПП' },
    { name: 'ogrn', label: 'ОГРН / ОГРНИП' },
    { name: 'address', label: 'Юридический адрес' },
    { name: 'phone', label: 'Телефон' },
    { name: 'email', label: 'Email', type: 'email' },
    { name: 'bank_name', label: 'Банк' },
    { name: 'bank_bik', label: 'БИК' },
    { name: 'bank_account', label: 'Расчётный счёт' },
    { name: 'bank_corr_account', label: 'Корреспондентский счёт' },
];

export default function OrganizationSettings({
    organization,
    directorPassport,
    passportTypes,
}: {
    organization: Organization;
    directorPassport: DirectorPassport;
    passportTypes: Option[];
}) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: organization.name ?? '',
        legal_name: organization.legal_name ?? '',
        inn: organization.inn ?? '',
        kpp: organization.kpp ?? '',
        ogrn: organization.ogrn ?? '',
        address: organization.address ?? '',
        phone: organization.phone ?? '',
        email: organization.email ?? '',
        director_surname: organization.director_surname ?? '',
        director_name: organization.director_name ?? '',
        director_father_name: organization.director_father_name ?? '',
        bank_name: organization.bank_name ?? '',
        bank_bik: organization.bank_bik ?? '',
        bank_account: organization.bank_account ?? '',
        bank_corr_account: organization.bank_corr_account ?? '',
        passport: {
            type: directorPassport.type ?? '',
            series: directorPassport.series ?? '',
            number: directorPassport.number ?? '',
            issued_by: directorPassport.issued_by ?? '',
            issued_at: directorPassport.issued_at ?? '',
            registration_address: directorPassport.registration_address ?? '',
        },
    });

    const passportErrors = errors as Record<string, string>;
    const setPassport = (field: keyof DirectorPassport, value: string) => setData('passport', { ...data.passport, [field]: value });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('organization.update'), { preserveScroll: true });
    };

    const renderField = (field: TextField) => (
        <div key={field.name} className="grid gap-2">
            <Label htmlFor={field.name}>{field.label}</Label>

            <Input
                id={field.name}
                type={field.type ?? 'text'}
                className="mt-1 block w-full"
                value={data[field.name]}
                onChange={(e) => setData(field.name, e.target.value)}
            />

            <InputError className="mt-2" message={errors[field.name]} />
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Организация" description="Реквизиты вашей организации — используются в сметах и документах" />

                    <form onSubmit={submit} className="space-y-6">
                        {identityFields.map(renderField)}

                        <fieldset className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4">
                            <legend className="text-muted-foreground px-1 text-sm">Руководитель</legend>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="director_surname">Фамилия</Label>
                                    <Input
                                        id="director_surname"
                                        value={data.director_surname}
                                        onChange={(e) => setData('director_surname', e.target.value)}
                                    />
                                    <InputError message={errors.director_surname} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="director_name">Имя</Label>
                                    <Input
                                        id="director_name"
                                        value={data.director_name}
                                        onChange={(e) => setData('director_name', e.target.value)}
                                    />
                                    <InputError message={errors.director_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="director_father_name">Отчество</Label>
                                    <Input
                                        id="director_father_name"
                                        value={data.director_father_name}
                                        onChange={(e) => setData('director_father_name', e.target.value)}
                                    />
                                    <InputError message={errors.director_father_name} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:max-w-md">
                                <Label htmlFor="passport_type">Паспорт</Label>
                                <Select value={data.passport.type} onValueChange={(value) => setPassport('type', value)}>
                                    <SelectTrigger id="passport_type">
                                        <SelectValue placeholder="Выберите тип паспорта" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {passportTypes.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={passportErrors['passport.type']} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="passport_series">Серия</Label>
                                    <Input
                                        id="passport_series"
                                        value={data.passport.series}
                                        onChange={(e) => setPassport('series', e.target.value)}
                                    />
                                    <InputError message={passportErrors['passport.series']} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="passport_number">Номер</Label>
                                    <Input
                                        id="passport_number"
                                        value={data.passport.number}
                                        onChange={(e) => setPassport('number', e.target.value)}
                                    />
                                    <InputError message={passportErrors['passport.number']} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="passport_issued_by">Кем выдан</Label>
                                <Input
                                    id="passport_issued_by"
                                    value={data.passport.issued_by}
                                    onChange={(e) => setPassport('issued_by', e.target.value)}
                                />
                                <InputError message={passportErrors['passport.issued_by']} />
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="passport_issued_at">Дата выдачи</Label>
                                <Input
                                    id="passport_issued_at"
                                    type="date"
                                    value={data.passport.issued_at}
                                    onChange={(e) => setPassport('issued_at', e.target.value)}
                                />
                                <InputError message={passportErrors['passport.issued_at']} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="passport_registration_address">Адрес регистрации</Label>
                                <textarea
                                    id="passport_registration_address"
                                    value={data.passport.registration_address}
                                    onChange={(e) => setPassport('registration_address', e.target.value)}
                                    rows={2}
                                    className="border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                                />
                                <InputError message={passportErrors['passport.registration_address']} />
                            </div>
                        </fieldset>

                        {requisiteFields.map(renderField)}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Сохранить</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Сохранено</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
