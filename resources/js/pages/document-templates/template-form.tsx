import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface TemplateFormData {
    id?: number;
    name: string | null;
    description: string | null;
    original_name?: string | null;
}

const textareaClasses =
    'border-input focus-visible:ring-ring rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none';

export default function TemplateForm({ template }: { template?: TemplateFormData }) {
    const isEdit = Boolean(template?.id);

    const form = useForm({
        name: template?.name ?? '',
        description: template?.description ?? '',
        file: null as File | null,
    });

    const { data, setData, processing, errors, progress } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Пустой file не отправляем: при редактировании он означает
        // «оставить прежний .docx». PHP не разбирает multipart у PUT,
        // поэтому правка уходит как POST с _method.
        form.transform((current) => {
            const payload: Record<string, unknown> = {
                name: current.name,
                description: current.description,
            };

            if (current.file) {
                payload.file = current.file;
            }

            if (isEdit) {
                payload._method = 'put';
            }

            return payload;
        });

        if (isEdit) {
            form.post(route('document-templates.update', template!.id), { forceFormData: true });

            return;
        }

        form.post(route('document-templates.store'), { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Название</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Описание</Label>
                <textarea
                    id="description"
                    rows={3}
                    className={textareaClasses}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="file">Файл шаблона (.docx)</Label>
                <Input
                    id="file"
                    type="file"
                    accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                />
                {isEdit && (
                    <p className="text-muted-foreground text-sm">
                        Сейчас загружен <span className="font-medium">{template?.original_name}</span>. Выберите файл, только если хотите его
                        заменить.
                    </p>
                )}
                <InputError message={errors.file} />

                {progress && (
                    <progress value={progress.percentage} max="100" className="h-1 w-full">
                        {progress.percentage}%
                    </progress>
                )}
            </div>

            <div className="bg-muted/40 border-sidebar-border/70 rounded-xl border p-4 text-sm">
                <p className="mb-2 font-medium">Как размечать шаблон</p>
                <ul className="text-muted-foreground list-disc space-y-1 pl-5">
                    <li>
                        Обычное поле — <code className="font-mono">{'{{contract.number}}'}</code>.
                    </li>
                    <li>
                        Колонка повторяющейся строки таблицы — <code className="font-mono">{'{{works[].name}}'}</code>. Скобки{' '}
                        <code className="font-mono">[]</code> обязательны: без них поле считается обычным, даже если стоит в таблице.
                    </li>
                    <li>Все колонки одной группы должны лежать в одной строке таблицы — повторяться будет именно она.</li>
                    <li>Пробелы внутри скобок и разрывы, которые Word вносит при наборе, исправляются при загрузке автоматически.</li>
                </ul>
            </div>

            <div className="flex gap-3">
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'Сохранить' : 'Загрузить'}
                </Button>
                <Button asChild variant="outline">
                    <Link href={isEdit ? route('document-templates.show', template!.id) : route('document-templates.index')}>Отмена</Link>
                </Button>
            </div>
        </form>
    );
}
