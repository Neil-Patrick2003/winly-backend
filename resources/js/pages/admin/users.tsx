import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    Copy,
    KeyRound,
    Search,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Admin/PasswordResetLinkController';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserAvatar } from '@/components/user-avatar';
import { useClipboard } from '@/hooks/use-clipboard';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { cn } from '@/lib/utils';
import { users as adminUsers } from '@/routes/admin';
import type { AdminUserRow, Paginated } from '@/types';

type ResetLink = { user_id: string; full_name: string; url: string };

/** The columns that can be ordered. Mirrors `UserController::SORTABLE`. */
const columns: { key: string; label: string; className?: string }[] = [
    { key: 'full_name', label: 'Name' },
    { key: 'username', label: 'Username', className: 'hidden md:table-cell' },
    { key: 'email', label: 'Email', className: 'hidden sm:table-cell' },
    { key: 'created_at', label: 'Joined', className: 'hidden lg:table-cell' },
];

function joinedOn(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * Everybody with an account, and a way back in for the ones locked out.
 *
 * The reset link is the same one the "forgot password" email carries, minted on
 * demand rather than listed: making one retires whatever link that account had,
 * so a page that minted twenty on load would leave nineteen dead.
 */
export default function AdminUsers({
    users,
    search,
    sort,
    resetExpiresInMinutes,
}: {
    users: Paginated<AdminUserRow>;
    search: string | null;
    sort: string;
    resetExpiresInMinutes: number;
}) {
    const [term, setTerm] = useState(search ?? '');
    const settled = useDebouncedValue(term);
    const [link, setLink] = useState<ResetLink | null>(null);
    const [copied, copy] = useClipboard();
    const mounted = useRef(false);

    const descending = sort.startsWith('-');
    const sortedBy = descending ? sort.slice(1) : sort;

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        router.get(
            adminUsers().url,
            { sort, ...(settled ? { filter: { search: settled } } : {}) },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users', 'search'],
            },
        );
    }, [settled, sort]);

    /** Clicking the column you are already on flips the direction. */
    const orderBy = (key: string) =>
        router.get(
            adminUsers().url,
            {
                sort: sortedBy === key && !descending ? `-${key}` : key,
                ...(term ? { filter: { search: term } } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const makeResetLink = (user: string) =>
        router.post(
            PasswordResetLinkController.url(user),
            {},
            {
                preserveScroll: true,
                // Flashed rather than sent as a prop, so a working credential
                // never lands in the browser's history state.
                onFlash: (flash) => {
                    const fresh = flash.resetLink as ResetLink | undefined;

                    if (fresh) {
                        setLink(fresh);
                        void copy(fresh.url);
                    }
                },
            },
        );

    return (
        <>
            <Head title="People" />

            <Page width="wide">
                <PageHeader
                    title="People"
                    description="Everybody with an account. Reset links are for the ones whose email never arrives."
                />

                <div className="relative mt-6 sm:max-w-sm">
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Search by name, username or email"
                        aria-label="Search by name, username or email"
                        className="pl-8"
                    />
                </div>

                {link && (
                    <div className="mt-4 rounded-card border border-border bg-card p-4 shadow-card">
                        <p className="text-card-title font-medium">
                            Reset link for {link.full_name}
                        </p>
                        <p className="mt-1 text-caption text-muted-foreground">
                            {copied === link.url
                                ? 'Copied to your clipboard.'
                                : 'Copy this and send it to them yourself.'}{' '}
                            It stops working in {resetExpiresInMinutes} minutes,
                            and making another for the same person replaces it.
                        </p>

                        <div className="mt-3 flex gap-2">
                            <Input
                                readOnly
                                value={link.url}
                                aria-label={`Reset link for ${link.full_name}`}
                                onFocus={(event) => event.target.select()}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void copy(link.url)}
                            >
                                {copied === link.url ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                                <span className="sr-only">Copy reset link</span>
                            </Button>
                        </div>
                    </div>
                )}

                {users.data.length === 0 ? (
                    <EmptyState
                        className="mt-6"
                        icon={Users}
                        title="Nobody found"
                        description={
                            search
                                ? `Nothing matches “${search}”.`
                                : 'There are no accounts yet.'
                        }
                    />
                ) : (
                    <div className="mt-6 space-y-4">
                        <div className="overflow-x-auto rounded-card border border-border shadow-card">
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-muted/40">
                                    <tr>
                                        {columns.map(
                                            ({ key, label, className }) => {
                                                const active = sortedBy === key;
                                                const Icon = !active
                                                    ? ArrowUpDown
                                                    : descending
                                                      ? ArrowDown
                                                      : ArrowUp;

                                                return (
                                                    <th
                                                        key={key}
                                                        scope="col"
                                                        className={cn(
                                                            'px-4 py-2.5 text-left font-medium',
                                                            className,
                                                        )}
                                                        aria-sort={
                                                            active
                                                                ? descending
                                                                    ? 'descending'
                                                                    : 'ascending'
                                                                : 'none'
                                                        }
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                orderBy(key)
                                                            }
                                                            className={cn(
                                                                'flex items-center gap-1.5 transition-colors hover:text-foreground',
                                                                active
                                                                    ? 'text-foreground'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {label}
                                                            <Icon
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                        </button>
                                                    </th>
                                                );
                                            },
                                        )}
                                        <th
                                            scope="col"
                                            className="px-4 py-2.5 text-right font-medium text-muted-foreground"
                                        >
                                            Reset
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-border">
                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="bg-card transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center gap-2.5">
                                                    <UserAvatar
                                                        name={user.full_name}
                                                        src={user.avatar_url}
                                                    />
                                                    <span className="font-medium">
                                                        {user.full_name}
                                                    </span>
                                                    {user.is_admin && (
                                                        <Badge variant="secondary">
                                                            Admin
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="hidden px-4 py-2.5 text-muted-foreground md:table-cell">
                                                {user.username
                                                    ? `@${user.username}`
                                                    : '—'}
                                            </td>

                                            <td className="hidden px-4 py-2.5 text-muted-foreground sm:table-cell">
                                                {user.email}
                                                {!user.email_verified && (
                                                    <span className="ml-2 text-caption">
                                                        Unverified
                                                    </span>
                                                )}
                                            </td>

                                            <td className="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                                {joinedOn(user.joined_at)}
                                            </td>

                                            <td className="px-4 py-2.5 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-muted-foreground"
                                                    onClick={() =>
                                                        makeResetLink(user.id)
                                                    }
                                                >
                                                    <KeyRound className="size-4" />
                                                    <span className="sr-only">
                                                        Make a reset link for{' '}
                                                        {user.full_name}
                                                    </span>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination page={users} label="people" />
                    </div>
                )}
            </Page>
        </>
    );
}
