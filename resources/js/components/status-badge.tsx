import { cn } from '@/lib/utils';

// Соответствие цвета из ProjectStatus::color() классам Tailwind (light/dark).
const colorClasses: Record<string, string> = {
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    zinc: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
};

export default function StatusBadge({ label, color }: { label: string; color: string }) {
    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', colorClasses[color] ?? colorClasses.zinc)}>
            {label}
        </span>
    );
}
