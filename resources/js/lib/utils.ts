import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Размер файла в человекочитаемом виде: 1536 → «1,5 КБ».
 */
export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} Б`;
    }

    const units = ['КБ', 'МБ', 'ГБ'];
    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(1).replace('.', ',')} ${units[unit]}`;
}
