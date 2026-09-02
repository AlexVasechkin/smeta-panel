import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function FlashMessages() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(false);

    const message = flash?.success ?? flash?.error ?? null;
    const isError = Boolean(flash?.error);

    useEffect(() => {
        if (message) {
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 4000);
            return () => clearTimeout(timer);
        }
    }, [message]);

    if (!message || !visible) {
        return null;
    }

    return (
        <div className="fixed top-4 right-4 z-50 max-w-sm">
            <div
                className={
                    'flex items-center gap-2 rounded-lg border px-4 py-3 text-sm shadow-lg ' +
                    (isError
                        ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                        : 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200')
                }
            >
                {isError ? <XCircle className="size-5 shrink-0" /> : <CheckCircle2 className="size-5 shrink-0" />}
                <span>{message}</span>
            </div>
        </div>
    );
}
