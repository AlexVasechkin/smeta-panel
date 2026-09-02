import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type Option, type SharedData } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export interface ProjectFormData {
    id?: number;
    client_id: number | null;
    city_id: number | null;
    name: string;
    order_number: string | null;
    address: string;
    area: string | null;
    rooms: number | null;
    status: string;
    start_date: string | null;
    notes: string | null;
}

function AddCityDialog({ onCreated }: { onCreated: (id: number) => void }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ name: '' });

    const close = () => {
        reset();
        clearErrors();
        setOpen(false);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('cities.store'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const created = (page.props as unknown as SharedData).flash.created_city;
                if (created) {
                    onCreated(created.id);
                }
                close();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={(next) => (next ? setOpen(true) : close())}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="icon" aria-label="Добавить город">
                    <Plus className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Новый город</DialogTitle>
                    <DialogDescription>Город будет доступен только в вашей организации.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="city-name">Название</Label>
                        <Input id="city-name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus required />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Отмена
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Добавить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProjectForm({
    project,
    clients,
    cities,
    statuses,
    defaultClientId,
}: {
    project?: ProjectFormData;
    clients: Option<number>[];
    cities: Option<number>[];
    statuses: Option[];
    defaultClientId?: number | null;
}) {
    const isEdit = Boolean(project?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        client_id: project?.client_id ?? defaultClientId ?? ('' as number | ''),
        city_id: project?.city_id ?? ('' as number | ''),
        name: project?.name ?? '',
        order_number: project?.order_number ?? '',
        address: project?.address ?? '',
        area: project?.area ?? '',
        rooms: project?.rooms ?? ('' as number | ''),
        status: project?.status ?? 'new',
        start_date: project?.start_date ?? '',
        notes: project?.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit && project?.id) {
            put(route('projects.update', project.id));
        } else {
            post(route('projects.store'));
        }
    };

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="client_id">Клиент</Label>
                <Select value={data.client_id ? String(data.client_id) : ''} onValueChange={(value) => setData('client_id', Number(value))}>
                    <SelectTrigger id="client_id">
                        <SelectValue placeholder="Выберите клиента" />
                    </SelectTrigger>
                    <SelectContent>
                        {clients.map((option) => (
                            <SelectItem key={option.value} value={String(option.value)}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.client_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="city_id">Город</Label>
                <div className="flex gap-2">
                    <SearchSelect
                        id="city_id"
                        className="flex-1"
                        options={cities}
                        value={data.city_id === '' ? null : data.city_id}
                        onChange={(value) => setData('city_id', value ?? '')}
                        placeholder="Выберите город"
                    />
                    <AddCityDialog onCreated={(id) => setData('city_id', id)} />
                </div>
                <InputError message={errors.city_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Название объекта</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="order_number">Номер договора</Label>
                <Input
                    id="order_number"
                    value={data.order_number}
                    onChange={(e) => setData('order_number', e.target.value)}
                    placeholder="Оставьте пустым — присвоится автоматически"
                />
                <InputError message={errors.order_number} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="address">Адрес</Label>
                <Input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} required />
                <InputError message={errors.address} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="area">Площадь, м²</Label>
                    <Input id="area" type="number" step="0.01" value={data.area} onChange={(e) => setData('area', e.target.value)} />
                    <InputError message={errors.area} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="rooms">Комнат</Label>
                    <Input
                        id="rooms"
                        type="number"
                        value={data.rooms}
                        onChange={(e) => setData('rooms', e.target.value === '' ? '' : Number(e.target.value))}
                    />
                    <InputError message={errors.rooms} />
                </div>
            </div>

            <div className="grid gap-2 sm:max-w-xs">
                <Label htmlFor="status">Статус</Label>
                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                    <SelectTrigger id="status">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {statuses.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.status} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="start_date">Дата начала</Label>
                <Input id="start_date" type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                <InputError message={errors.start_date} />
            </div>

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
                    <Link href={route('projects.index')}>Отмена</Link>
                </Button>
            </div>
        </form>
    );
}
