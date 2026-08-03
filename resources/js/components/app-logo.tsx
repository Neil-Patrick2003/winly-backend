import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-7 shrink-0" />
            <div className="grid flex-1 text-left">
                <span className="truncate font-display text-[13px] leading-tight font-semibold">
                    {name}
                </span>
                <span className="truncate text-[11px] leading-tight text-muted-foreground">
                    Owner console
                </span>
            </div>
        </>
    );
}
