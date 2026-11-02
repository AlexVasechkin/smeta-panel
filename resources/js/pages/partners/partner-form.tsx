import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Option } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface DirectorPassport {
    type: string | null;
    series: string | null;
    number: string | null;
    issued_by: string | null;
    issued_at: string | null;
    registration_address: string | null;
}

export interface PartnerFormData {
    id?: number;
    type: string | null;
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
    passport?: DirectorPassport | null;
}

const textareaClasses =
    'border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none';

export default function PartnerForm({
    partner,
    organizationTypes,
    passportTypes,
}: {
    partner?: PartnerFormData;
    organizationTypes: Option[];
    passportTypes: Option[];
}) {
    const isEdit = Boolean(partner?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        type: partner?.type ?? 'ooo',
        name: partner?.name ?? '',
        legal_name: partner?.legal_name ?? '',
        inn: partner?.inn ?? '',
        kpp: partner?.kpp ?? '',
        ogrn: partner?.ogrn ?? '',
        address: partner?.address ?? '',
        phone: partner?.phone ?? '',
        email: partner?.email ?? '',
        director_surname: partner?.director_surname ?? '',
        director_name: partner?.director_name ?? '',
        director_father_name: partner?.director_father_name ?? '',
        bank_name: partner?.bank_name ?? '',
        bank_bik: partner?.bank_bik ?? '',
        bank_account: partner?.bank_account ?? '',
        bank_corr_account: partner?.bank_corr_account ?? '',
        passport: {
            type: partner?.passport?.type ?? '',
            series: partner?.passport?.series ?? '',
            number: partner?.passport?.number ?? '',
            issued_by: partner?.passport?.issued_by ?? '',
            issued_at: partner?.passport?.issued_at ?? '',
            registration_address: partner?.passport?.registration_address ?? '',
        },
    });

    const isIp = data.type === 'ip';
    const passportErrors = errors as Record<string, string>;

    const setPassport = (field: keyof DirectorPassport, value: string) => setData('passport', { ...data.passport, [field]: value });

    // У ИП нет КПП — очищаем поле при смене формы, чтобы не отправлять лишнее.
    const changeType = (value: string) => setData({ ...data, type: value, kpp: value === 'ip' ? '' : data.kpp });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit && partner?.id) {
            put(route('partners.update', partner.id));
        } else {
            post(route('partners.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2 sm:max-w-xs">
                <Label htmlFor="type">Форма</Label>
                <Select value={data.type} onValueChange={changeType}>
                    <SelectTrigger id="type">
                        <SelectValue placeholder="Выберите форму" />
                    </SelectTrigger>
                    <SelectContent>
                        {organizationTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Краткое наименование</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="legal_name">Полное наименование</Label>
                <Input id="legal_name" value={data.legal_name} onChange={(e) => setData('legal_name', e.target.value)} />
                <InputError message={errors.legal_name} />
            </div>

            <fieldset className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4">
                <legend className="text-muted-foreground px-1 text-sm">Реквизиты</legend>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="inn">ИНН</Label>
                        <Input id="inn" inputMode="numeric" value={data.inn} onChange={(e) => setData('inn', e.target.value)} />
                        <InputError message={errors.inn} />
                    </div>

                    {!isIp && (
                        <div className="grid gap-2">
                            <Label htmlFor="kpp">КПП</Label>
                            <Input id="kpp" inputMode="numeric" value={data.kpp} onChange={(e) => setData('kpp', e.target.value)} />
                            <InputError message={errors.kpp} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="ogrn">{isIp ? 'ОГРНИП' : 'ОГРН'}</Label>
                        <Input id="ogrn" inputMode="numeric" value={data.ogrn} onChange={(e) => setData('ogrn', e.target.value)} />
                        <InputError message={errors.ogrn} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="address">{isIp ? 'Адрес' : 'Юридический адрес'}</Label>
                    <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    <InputError message={errors.address} />
                </div>

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
            </fieldset>

            <fieldset className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4">
                <legend className="text-muted-foreground px-1 text-sm">{isIp ? 'Предприниматель' : 'Руководитель'}</legend>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="director_surname">Фамилия</Label>
                        <Input id="director_surname" value={data.director_surname} onChange={(e) => setData('director_surname', e.target.value)} />
                        <InputError message={errors.director_surname} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="director_name">Имя</Label>
                        <Input id="director_name" value={data.director_name} onChange={(e) => setData('director_name', e.target.value)} />
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
                        <Input id="passport_series" value={data.passport.series} onChange={(e) => setPassport('series', e.target.value)} />
                        <InputError message={passportErrors['passport.series']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="passport_number">Номер</Label>
                        <Input id="passport_number" value={data.passport.number} onChange={(e) => setPassport('number', e.target.value)} />
                        <InputError message={passportErrors['passport.number']} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="passport_issued_by">Кем выдан</Label>
                    <Input id="passport_issued_by" value={data.passport.issued_by} onChange={(e) => setPassport('issued_by', e.target.value)} />
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
                        className={textareaClasses}
                    />
                    <InputError message={passportErrors['passport.registration_address']} />
                </div>
            </fieldset>

            <fieldset className="border-sidebar-border/70 grid gap-4 rounded-xl border p-4">
                <legend className="text-muted-foreground px-1 text-sm">Банковские реквизиты</legend>

                <div className="grid gap-2">
                    <Label htmlFor="bank_name">Банк</Label>
                    <Input id="bank_name" value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)} />
                    <InputError message={errors.bank_name} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="bank_bik">БИК</Label>
                        <Input id="bank_bik" inputMode="numeric" value={data.bank_bik} onChange={(e) => setData('bank_bik', e.target.value)} />
                        <InputError message={errors.bank_bik} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="bank_account">Расчётный счёт</Label>
                        <Input
                            id="bank_account"
                            inputMode="numeric"
                            value={data.bank_account}
                            onChange={(e) => setData('bank_account', e.target.value)}
                        />
                        <InputError message={errors.bank_account} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="bank_corr_account">Корреспондентский счёт</Label>
                    <Input
                        id="bank_corr_account"
                        inputMode="numeric"
                        value={data.bank_corr_account}
                        onChange={(e) => setData('bank_corr_account', e.target.value)}
                    />
                    <InputError message={errors.bank_corr_account} />
                </div>
            </fieldset>

            <div className="flex gap-3">
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'Сохранить' : 'Добавить'}
                </Button>
                <Button asChild variant="outline">
                    <Link href={route('partners.index')}>Отмена</Link>
                </Button>
            </div>
        </form>
    );
}
