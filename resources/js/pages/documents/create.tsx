import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Option } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Документы', href: '/documents' },
    { title: 'Создание', href: '/documents/create' },
];

interface DocumentTypeOption extends Option {
    implemented: boolean;
}

interface ProjectOption extends Option {
    has_preliminary: boolean;
    has_estimate: boolean;
}

// Некоторые типы создаются только для объектов с документом-основанием.
const REQUIREMENTS: Record<string, { flag: keyof Pick<ProjectOption, 'has_preliminary' | 'has_estimate'>; hint: string }> = {
    estimate: { flag: 'has_preliminary', hint: 'Нет объектов с предварительной сметой. Сначала создайте предварительную смету.' },
    contract: { flag: 'has_estimate', hint: 'Нет объектов со сметой. Сначала создайте смету.' },
    work_completion_act: { flag: 'has_estimate', hint: 'Нет объектов со сметой. Сначала создайте смету.' },
};

export default function DocumentsCreate({ documentTypes, projects }: { documentTypes: DocumentTypeOption[]; projects: ProjectOption[] }) {
    const [type, setType] = useState('');
    const [project, setProject] = useState('');

    const requirement = REQUIREMENTS[type];
    const availableProjects = requirement ? projects.filter((option) => option[requirement.flag]) : projects;

    const onTypeChange = (value: string) => {
        setType(value);
        // Сбросить выбор, если объект больше не подходит для выбранного типа.
        const req = REQUIREMENTS[value];
        if (req && project && !projects.find((option) => option.value === project)?.[req.flag]) {
            setProject('');
        }
    };

    const proceed = () => {
        if (type && project) {
            router.get(route('documents.preview', { type, project }));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Создание документа" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">Создание документа</h1>

                <div className="border-sidebar-border/70 grid max-w-xl gap-5 rounded-xl border p-5">
                    <div className="grid gap-2">
                        <Label htmlFor="type">Тип документа</Label>
                        <Select value={type} onValueChange={onTypeChange}>
                            <SelectTrigger id="type">
                                <SelectValue placeholder="Выберите тип документа" />
                            </SelectTrigger>
                            <SelectContent>
                                {documentTypes.map((option) => (
                                    <SelectItem key={option.value} value={option.value} disabled={!option.implemented}>
                                        {option.label}
                                        {!option.implemented ? ' (скоро)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="project">Объект</Label>
                        <Select value={project} onValueChange={setProject}>
                            <SelectTrigger id="project">
                                <SelectValue placeholder="Выберите объект" />
                            </SelectTrigger>
                            <SelectContent>
                                {availableProjects.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {projects.length === 0 && <p className="text-muted-foreground text-sm">Сначала создайте объект в разделе «Объекты».</p>}
                        {projects.length > 0 && requirement && availableProjects.length === 0 && (
                            <p className="text-muted-foreground text-sm">{requirement.hint}</p>
                        )}
                    </div>

                    <div>
                        <Button onClick={proceed} disabled={!type || !project}>
                            Далее — предпросмотр
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
