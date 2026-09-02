import { cn } from '@/lib/utils';
import { type PaginationLink } from '@/types';
import { Link } from '@inertiajs/react';

export default function Pagination({ links, className }: { links: PaginationLink[]; className?: string }) {
    // Скрываем пагинацию, если есть только одна страница (3 «служебные» ссылки: назад/1/вперёд).
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className={cn('flex flex-wrap items-center gap-1', className)}>
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={cn(
                            'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm transition-colors',
                            link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-sidebar-border/70 hover:bg-muted',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="border-sidebar-border/70 text-muted-foreground inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm opacity-50"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
