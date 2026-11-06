import { Head, Link, router } from '@inertiajs/react';
import { Ban, Check, Flag, ImageOff, Trash2, Users } from 'lucide-react';
import ReportController from '@/actions/App/Http/Controllers/Admin/ReportController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import { reports as adminReports } from '@/routes/admin';
import type { AdminReportPerson, AdminReportRow, Paginated } from '@/types';

type Status = 'pending' | 'actioned' | 'dismissed' | 'all';

const tabs: { key: Status; label: string }[] = [
    { key: 'pending', label: 'Waiting' },
    { key: 'actioned', label: 'Acted on' },
    { key: 'dismissed', label: 'Dismissed' },
    { key: 'all', label: 'All' },
];

const typeLabels: Record<AdminReportRow['type'], string> = {
    post: 'Post',
    comment: 'Comment',
    story: 'Story',
    user: 'Account',
};

function filedAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const hours = (Date.now() - new Date(iso).getTime()) / 36e5;

    if (hours < 1) {
        return `${Math.max(1, Math.round(hours * 60))}m ago`;
    }

    if (hours < 48) {
        return `${Math.round(hours)}h ago`;
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function Person({
    person,
    label,
}: {
    person: AdminReportPerson;
    label: string;
}) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <UserAvatar name={person.full_name} src={person.avatar_url} />
            <div className="min-w-0">
                <p className="truncate text-caption text-muted-foreground">
                    {label}
                </p>
                <p className="truncate text-sm font-medium">
                    {person.full_name}
                    {person.username && (
                        <span className="ml-1 font-normal text-muted-foreground">
                            @{person.username}
                        </span>
                    )}
                    {person.disabled && (
                        <Badge variant="destructive" className="ml-2">
                            Disabled
                        </Badge>
                    )}
                </p>
            </div>
        </div>
    );
}

/**
 * The moderation queue.
 *
 * Each card shows what was reported, by whom, and why, with the three answers:
 * delete the content, disable the account behind it, or dismiss the report.
 * Acting on one closes every other open report about the same thing.
 */
export default function AdminReports({
    reports,
    status,
    pendingCount,
}: {
    reports: Paginated<AdminReportRow>;
    status: Status;
    pendingCount: number;
}) {
    const act = (url: string) => router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Reports" />

            <Page width="wide">
                <PageHeader
                    title="Reports"
                    description="What members flagged. Both app stores expect each report to be handled within 24 hours."
                />

                <div className="mt-6 flex flex-wrap gap-2" role="tablist">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={
                                adminReports({ query: { status: tab.key } }).url
                            }
                            preserveScroll
                            role="tab"
                            aria-selected={status === tab.key}
                            className={cn(
                                'rounded-full border px-3.5 py-1.5 text-sm transition-colors',
                                status === tab.key
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                            {tab.key === 'pending' && pendingCount > 0 && (
                                <span className="ml-1.5 font-semibold">
                                    {pendingCount}
                                </span>
                            )}
                        </Link>
                    ))}
                </div>

                {reports.data.length === 0 ? (
                    <EmptyState
                        className="mt-6"
                        icon={Flag}
                        title={
                            status === 'pending'
                                ? 'Nothing waiting'
                                : 'No reports here'
                        }
                        description={
                            status === 'pending'
                                ? 'Every report has been handled.'
                                : 'Reports show up here once they have been handled.'
                        }
                    />
                ) : (
                    <div className="mt-6 space-y-4">
                        {reports.data.map((report) => {
                            const pending = report.status === 'pending';
                            const canRemove =
                                pending &&
                                report.target !== null &&
                                report.type !== 'user';
                            const canDisable =
                                pending &&
                                report.author !== null &&
                                !report.author.disabled &&
                                !report.author.is_admin;
                            const who = report.author?.username
                                ? `@${report.author.username}`
                                : 'this person';

                            return (
                                <article
                                    key={report.id}
                                    className="rounded-card border border-border bg-card p-4 shadow-card"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary">
                                            {typeLabels[report.type]}
                                        </Badge>
                                        <Badge
                                            variant={
                                                report.times_reported > 1
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            <Users className="size-3" />
                                            {report.times_reported === 1
                                                ? '1 person reported'
                                                : `${report.times_reported} people reported`}
                                        </Badge>
                                        <span className="text-sm font-medium">
                                            {report.reasons.join(', ')}
                                        </span>
                                        <span className="ml-auto text-caption text-muted-foreground">
                                            {filedAgo(report.first_reported_at)}
                                            {!pending &&
                                                ` · ${report.status === 'actioned' ? 'acted on' : 'dismissed'}`}
                                        </span>
                                    </div>

                                    {report.notes.length > 0 && (
                                        <ul className="mt-2 space-y-1">
                                            {report.notes.map((note, at) => (
                                                <li
                                                    key={at}
                                                    className="text-sm text-muted-foreground"
                                                >
                                                    “{note.text}”{' '}
                                                    <span className="text-caption">
                                                        — {note.by}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}

                                    <div className="mt-3 rounded-lg border border-border bg-muted/30 p-3">
                                        {report.target === null ? (
                                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <ImageOff className="size-4" />
                                                Already deleted.
                                            </p>
                                        ) : (
                                            <>
                                                {report.target.detail && (
                                                    <p className="text-caption text-muted-foreground">
                                                        {report.target.detail}
                                                    </p>
                                                )}
                                                {report.target.text ? (
                                                    <p className="mt-1 text-sm whitespace-pre-line">
                                                        {report.target.text}
                                                    </p>
                                                ) : (
                                                    <p className="mt-1 text-sm text-muted-foreground italic">
                                                        No text.
                                                    </p>
                                                )}
                                                {report.target.images.length >
                                                    0 && (
                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                        {report.target.images.map(
                                                            (src) => (
                                                                <a
                                                                    key={src}
                                                                    href={src}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <img
                                                                        src={
                                                                            src
                                                                        }
                                                                        alt=""
                                                                        className="size-24 rounded-md object-cover"
                                                                    />
                                                                </a>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>

                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        {report.author && (
                                            <Person
                                                person={report.author}
                                                label={
                                                    report.type === 'user'
                                                        ? 'Reported account'
                                                        : 'Posted by'
                                                }
                                            />
                                        )}
                                        {report.reporters[0] && (
                                            <Person
                                                person={report.reporters[0]}
                                                label={
                                                    report.reporters.length > 1
                                                        ? `Reported by ${report.reporters[0].full_name.split(' ')[0]} and ${report.reporters.length - 1} more`
                                                        : 'Reported by'
                                                }
                                            />
                                        )}
                                    </div>

                                    {pending && (
                                        <div className="mt-4 flex flex-wrap gap-2 border-t border-border pt-3">
                                            {canRemove && (
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Delete{' '}
                                                            {typeLabels[
                                                                report.type
                                                            ].toLowerCase()}
                                                        </Button>
                                                    }
                                                    title={`Delete this ${typeLabels[report.type].toLowerCase()}?`}
                                                    description="It is removed for everyone, with any photos. This cannot be undone."
                                                    confirmLabel="Delete"
                                                    destructive
                                                    onConfirm={() =>
                                                        act(
                                                            ReportController.remove.url(
                                                                report.id,
                                                            ),
                                                        )
                                                    }
                                                />
                                            )}
                                            {canDisable && (
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Ban className="size-4" />
                                                            Disable {who}
                                                        </Button>
                                                    }
                                                    title={`Disable ${who}?`}
                                                    description="They are signed out everywhere, cannot sign back in, and everything they posted is hidden. Nothing is deleted: you can enable them again from People."
                                                    confirmLabel="Disable account"
                                                    destructive
                                                    onConfirm={() =>
                                                        act(
                                                            ReportController.disable.url(
                                                                report.id,
                                                            ),
                                                        )
                                                    }
                                                />
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    act(
                                                        ReportController.dismiss.url(
                                                            report.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Check className="size-4" />
                                                Dismiss
                                            </Button>
                                        </div>
                                    )}
                                </article>
                            );
                        })}

                        <Pagination page={reports} label="reports" />
                    </div>
                )}
            </Page>
        </>
    );
}
