import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Option } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface PassportData {
    type: string | null;
    series: string | null;
    number: string | null;
    issued_by: string | null;
    issued_at: string | null;
    registration_address: string | null;
}

export interface ClientFormData {
    id?: number;
    type: string;
    name: string;
    surname: string | null;
    father_name: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    passport?: PassportData | null;
}

export default function ClientForm({
    client,
    clientTypes,
    passportTypes,
}: {
    client?: ClientFormData;
    clientTypes: Option[];
    passportTypes: Option[];
}) {
    const isEdit = Boolean(client?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        type: client?.type ?? 'individual',
        name: client?.name ?? '',
        surname: client?.surname ?? '',
        father_name: client?.father_name ?? '',
        phone: client?.phone ?? '',
        email: client?.email ?? '',
        notes: client?.notes ?? '',
        passport: {
            type: client?.passport?.type ?? '',
            series: client?.passport?.series ?? '',
            number: client?.passport?.number ?? '',
            issued_by: client?.passport?.issued_by ?? '',
            issued_at: client?.passport?.issued_at ?? '',
            registration_address: client?.passport?.registration_address ?? '',
        },
    });

    const isIndividual = data.type === 'individual';
    const passportErrors = errors as Record<string, string>;

    const setPassport = (field: keyof PassportData, value: string) => setData('passport', { ...data.passport, [field]: value });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit && client?.id) {
            put(route('clients.update', client.id));
        } else {
            post(route('clients.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="type">Тип клиента</Label>
                <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                    <SelectTrigger id="type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {clientTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            {isIndividual ? (
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="surname">Фамилия</Label>
                        <Input id="surname" value={data.surname} onChange={(e) => setData('surname', e.target.value)} autoFocus />
                        <InputError message={errors.surname} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Имя</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="father_name">Отчество</Label>
                        <Input id="father_name" value={data.father_name} onChange={(e) => setData('father_name', e.target.value)} />
                        <InputError message={errors.father_name} />
                    </div>
                </div>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor="name">Название</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    <InputError message={errors.name} />
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="phone">Телефон</Label>
                    <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    <InputError message={errors.phone} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    <InputError message={errors.email} />
                </div>
            </div>

            {isIndividual && (
                <fieldset className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4">
                    <legend className="text-muted-foreground px-1 text-sm">Паспортные данные</legend>

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
            )}

            <div className="grid gap-2">
                <Label htmlFor="notes">Заметки</Label>
                <textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    rows={4}
                    className="border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                />
                <InputError message={errors.notes} />
            </div>

            <div className="flex gap-3">
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'Сохранить' : 'Создать'}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('clients.index')}>Отмена</Link>
                </Button>
            </div>
        </form>
    );
}
