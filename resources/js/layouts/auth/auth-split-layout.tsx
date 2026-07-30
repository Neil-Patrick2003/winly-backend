import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AuthBrandPanel } from '@/components/auth-brand-panel';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, register and the rest of the auth screens.
 *
 * Two columns on a wide screen — the artwork on the left, the form on the
 * right — collapsing to the form alone below `lg`. The panel is the first
 * thing dropped rather than something to scroll past: on a phone the only
 * thing anyone came here to do is sign in.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <AuthBrandPanel name={name} />

            <div className="flex flex-col justify-center px-6 py-10 sm:px-10">
                <div className="mx-auto w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mb-8 flex items-center justify-center gap-2.5 lg:hidden"
                    >
                        <AppLogoIcon className="size-8 shrink-0" />
                        <span className="font-display text-page-title font-semibold">
                            {name}
                        </span>
                    </Link>

                    <div className="mb-6 flex flex-col gap-1.5">
                        <h1 className="font-display text-heading font-semibold text-balance">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-section text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
