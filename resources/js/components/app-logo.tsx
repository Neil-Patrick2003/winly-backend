import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, auth } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-7 shrink-0" />
            <div className="grid flex-1 text-left">
                <span className="truncate font-display text-[13px] leading-tight font-semibold">
                    {name}
                </span>
                {/*
                 * Staff are not looking at their own circles here, so calling
                 * it the owner console would be describing somebody else's job.
                 */}
                <span className="truncate text-[11px] leading-tight text-muted-foreground">
                    {auth.user?.is_admin ? 'Admin console' : 'Owner console'}
                </span>
            </div>
        </>
    );
}
