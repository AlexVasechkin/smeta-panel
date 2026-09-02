import { cn } from '@/lib/utils';
import { type Option } from '@/types';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';

interface SearchSelectProps<V extends string | number> {
    options: Option<V>[];
    value: V | null;
    onChange: (value: V | null) => void;
    placeholder?: string;
    id?: string;
    className?: string;
    disabled?: boolean;
}

/**
 * Выпадающий список с поиском по подстроке (combobox).
 * Значение — value выбранного Option, либо null.
 */
export function SearchSelect<V extends string | number>({
    options,
    value,
    onChange,
    placeholder = 'Начните вводить…',
    id,
    className,
    disabled,
}: SearchSelectProps<V>) {
    const [query, setQuery] = useState('');
    const selected = options.find((option) => option.value === value) ?? null;

    const filtered =
        query.trim() === ''
            ? options
            : options.filter((option) => option.label.toLowerCase().includes(query.trim().toLowerCase()));

    return (
        <Combobox
            value={selected}
            onChange={(option: Option<V> | null) => onChange(option ? option.value : null)}
            onClose={() => setQuery('')}
            disabled={disabled}
        >
            <div className={cn('relative', className)}>
                <ComboboxInput
                    id={id}
                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-10 w-full items-center rounded-md border px-3 py-2 pr-10 text-sm focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                    displayValue={(option: Option<V> | null) => option?.label ?? ''}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={placeholder}
                    autoComplete="off"
                />
                <ComboboxButton className="absolute inset-y-0 right-0 flex items-center pr-3">
                    <ChevronsUpDown className="h-4 w-4 opacity-50" />
                </ComboboxButton>

                <ComboboxOptions className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border p-1 shadow-md empty:hidden">
                    {filtered.length === 0 ? (
                        <div className="text-muted-foreground px-2 py-1.5 text-sm">Ничего не найдено</div>
                    ) : (
                        filtered.map((option) => (
                            <ComboboxOption
                                key={String(option.value)}
                                value={option}
                                className="data-[focus]:bg-accent data-[focus]:text-accent-foreground relative flex cursor-default items-center rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none"
                            >
                                {({ selected: isSelected }) => (
                                    <>
                                        {isSelected && (
                                            <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                                                <Check className="h-4 w-4" />
                                            </span>
                                        )}
                                        {option.label}
                                    </>
                                )}
                            </ComboboxOption>
                        ))
                    )}
                </ComboboxOptions>
            </div>
        </Combobox>
    );
}
