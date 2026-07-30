import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-7 items-center justify-center rounded-md bg-brand-gradient text-white">
                <AppLogoIcon className="size-4 fill-current text-white" />
            </div>
            <div className="grid flex-1 text-left">
                <span className="truncate font-display text-[13px] leading-tight font-semibold">
                    {name}
                </span>
                <span className="truncate text-[11px] leading-tight text-muted-foreground">
                    Admin console
                </span>
            </div>
        </>
    );
}
