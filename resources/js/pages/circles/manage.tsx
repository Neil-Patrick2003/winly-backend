import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import { Ban, Crown, Search, Trash2, UserMinus, UserPlus } from 'lucide-react';
import { useState } from 'react';
import CircleManagementController from '@/actions/App/Http/Controllers/CircleManagementController';
import { CircleBadge } from '@/components/circle-badge';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { PersonRow } from '@/components/person-row';
import { SettingsSection } from '@/components/settings-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { owner as circleOwner } from '@/routes/admin/circles';
import { manage, members as membersTab } from '@/routes/circles';
import type {
    CircleHeader,
    CircleMember,
    CirclePerson,
    InviteCandidate,
    Paginated,
    PendingInvitation,
} from '@/types';

const listStyles =
    'divide-y divide-border overflow-hidden rounded-card border border-border shadow-card';

/** The groups the settings are split across. */
type TabKey = 'details' | 'inner' | 'people' | 'danger';

export default function Manage({
    circle,
    subCircles,
    canAddSubCircle,
    members,
    invitations,
    blocked,
    candidates,
    search,
}: {
    circle: CircleHeader;
    /** The circles sitting inside this one. */
    subCircles: {
        id: string;
        name: string;
        members_count: number;
        owner: { id: string; full_name: string } | null;
    }[];
    /** False for a circle already inside another — they do not nest. */
    canAddSubCircle: boolean;
    members: Paginated<CircleMember>;
    invitations: PendingInvitation[];
    blocked: CirclePerson[];
    candidates: InviteCandidate[];
    search: string | null;
}) {
    /**
     * Which group is showing.
     *
     * Local rather than in the URL: every tab's data is already on the page, so
     * switching is instant and a round trip would be a step backwards. A reload
     * lands on Details, which is where somebody opening this page usually wants
     * to be anyway.
     */
    const [tab, setTab] = useState<TabKey>('details');
    const [addingSub, setAddingSub] = useState(false);

    const tabs: { key: TabKey; label: string; count?: number }[] = [
        { key: 'details', label: 'Details' },
        // Hidden entirely for a circle that already sits inside another: there
        // is nothing it could hold, so offering the tab would only disappoint.
        ...(canAddSubCircle
            ? [
                  {
                      key: 'inner' as const,
                      label: 'Circles inside',
                      count: subCircles.length,
                  },
              ]
            : []),
        { key: 'people', label: 'People', count: members.total },
        { key: 'danger', label: 'Danger' },
    ];

    const searchForm = useForm({ search: search ?? '' });

    const scoped = (user: string) => ({ circle: circle.id, user });

    const invite = (person: InviteCandidate) =>
        router.post(
            CircleManagementController.invite.url(circle.id),
            { user_id: person.id },
            { preserveScroll: true },
        );

    const revoke = (invitation: PendingInvitation) =>
        router.delete(
            CircleManagementController.revokeInvitation.url({
                circle: circle.id,
                invitation: invitation.id,
            }),
            { preserveScroll: true },
        );

    const runSearch = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            manage(circle.id).url,
            { search: searchForm.data.search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`Manage ${circle.name}`} />

            {/*
             * The frame matches the circle's other pages so the header and the
             * way back line up as you move between them; the content keeps a
             * readable measure inside it, since a form field the width of the
             * page is harder to fill in, not easier.
             */}
            <Page width="wide">
                <PageHeader
                    title={`Manage ${circle.name}`}
                    description={
                        circle.can_transfer_ownership
                            ? 'You are here as staff. Everything the owner can do, you can do.'
                            : 'Only you can see this page — you own this circle.'
                    }
                    back={{
                        href: membersTab(circle.id),
                        label: 'Back to the circle',
                    }}
                    leading={
                        <CircleBadge
                            initial={circle.icon_initial}
                            color={circle.color_hex}
                            size="lg"
                        />
                    }
                />

                <div className="mt-8 max-w-2xl">
                    {/* One group at a time.
                        This all sat in a single column: seven sections with a
                        few hundred rows of people in the middle and the way to
                        delete the circle underneath them. Nothing was hard to
                        find so much as far away. */}
                    <div
                        role="tablist"
                        aria-label="Circle settings"
                        className="flex flex-wrap gap-1 rounded-lg border bg-muted/40 p-1"
                    >
                        {tabs.map((entry) => (
                            <button
                                key={entry.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === entry.key}
                                onClick={() => setTab(entry.key)}
                                className={`flex-1 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap transition ${
                                    tab === entry.key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {entry.label}
                                {entry.count === undefined ? null : (
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        {entry.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Hidden rather than unmounted: a half-typed invitation search
                    survives a look at Details and back, and switching costs
                    nothing because every tab's data is already here. */}
                <div className="mt-8 max-w-2xl">
                    <div
                        className={
                            tab === 'details'
                                ? 'flex flex-col gap-10'
                                : 'hidden'
                        }
                    >
                        <SettingsSection
                            title="Details"
                            description="How the circle appears everywhere it is listed."
                        >
                            <Form
                                {...CircleManagementController.update.form(
                                    circle.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                defaultValue={circle.name}
                                                required
                                                maxLength={60}
                                                aria-invalid={!!errors.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="description">
                                                Description
                                            </Label>
                                            <Input
                                                id="description"
                                                name="description"
                                                defaultValue={
                                                    circle.description ?? ''
                                                }
                                                maxLength={500}
                                                placeholder="What this circle is for"
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="grid gap-2 sm:col-span-1">
                                                <Label htmlFor="tag">Tag</Label>
                                                <Input
                                                    id="tag"
                                                    name="tag"
                                                    defaultValue={
                                                        circle.tag ?? ''
                                                    }
                                                    maxLength={40}
                                                />
                                                <InputError
                                                    message={errors.tag}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="icon_initial">
                                                    Initial
                                                </Label>
                                                <Input
                                                    id="icon_initial"
                                                    name="icon_initial"
                                                    defaultValue={
                                                        circle.icon_initial
                                                    }
                                                    required
                                                    maxLength={2}
                                                />
                                                <InputError
                                                    message={
                                                        errors.icon_initial
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="color_hex">
                                                    Colour
                                                </Label>
                                                <Input
                                                    id="color_hex"
                                                    name="color_hex"
                                                    type="color"
                                                    defaultValue={
                                                        circle.color_hex
                                                    }
                                                    required
                                                    className="h-9 w-full p-1"
                                                />
                                                <InputError
                                                    message={errors.color_hex}
                                                />
                                            </div>
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </SettingsSection>
                    </div>

                    <div
                        className={
                            tab === 'inner' ? 'flex flex-col gap-10' : 'hidden'
                        }
                    >
                        <SettingsSection
                            title="Circles inside this one"
                            description="A smaller circle within this one. Anybody can join it, and wins shared there also reach this circle."
                        >
                            {subCircles.length > 0 ? (
                                <ul className="mb-6 divide-y divide-border rounded-lg border">
                                    {subCircles.map((sub) => (
                                        <li
                                            key={sub.id}
                                            className="flex items-center justify-between gap-3 px-4 py-3"
                                        >
                                            <div className="min-w-0">
                                                {/* Named as it is named everywhere else, so the same
                                                    circle reads the same wherever it appears. */}
                                                <p className="truncate text-sm font-medium">
                                                    {sub.name}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        ({circle.name})
                                                    </span>
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {sub.members_count === 1
                                                        ? '1 member'
                                                        : `${sub.members_count} members`}
                                                    {sub.owner
                                                        ? ` · kept by ${sub.owner.full_name}`
                                                        : ''}
                                                </p>
                                            </div>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={manage(sub.id)}>
                                                    Manage
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}

                            {/* In a dialog rather than under the list.
                                Opening a circle inside another is occasional —
                                the list is what this section is usually for —
                                and a permanently open form put three empty
                                boxes between it and everything below it. */}
                            <Dialog
                                open={addingSub}
                                onOpenChange={setAddingSub}
                            >
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Add a circle inside
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            New circle inside {circle.name}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Anybody can join it, and wins shared
                                            there also reach {circle.name}.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <Form
                                        {...CircleManagementController.createSubCircle.form(
                                            circle.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        resetOnSuccess
                                        onSuccess={() => setAddingSub(false)}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="sub_name">
                                                        Name
                                                    </Label>
                                                    <Input
                                                        id="sub_name"
                                                        name="name"
                                                        required
                                                        maxLength={60}
                                                        placeholder="Beginners"
                                                    />
                                                    <InputError
                                                        message={errors.name}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sub_description">
                                                        What it is for
                                                    </Label>
                                                    <Input
                                                        id="sub_description"
                                                        name="description"
                                                        maxLength={500}
                                                        placeholder="For anyone just starting out."
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.description
                                                        }
                                                    />
                                                </div>

                                                <DialogFooter>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Create circle inside
                                                    </Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </SettingsSection>
                    </div>

                    <div
                        className={
                            tab === 'people' ? 'flex flex-col gap-10' : 'hidden'
                        }
                    >
                        <SettingsSection
                            title="Invite people"
                            description="People you and they both follow. Anyone not on welle yet needs a share link."
                        >
                            <form onSubmit={runSearch} className="flex gap-2">
                                <Input
                                    value={searchForm.data.search}
                                    onChange={(event) =>
                                        searchForm.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Search by name or username"
                                    aria-label="Search people to invite"
                                />
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button type="submit" variant="outline">
                                            <Search className="size-4" />
                                            <span className="sr-only">
                                                Search
                                            </span>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Search people to invite
                                    </TooltipContent>
                                </Tooltip>
                            </form>

                            {candidates.length === 0 ? (
                                <EmptyState
                                    icon={UserPlus}
                                    title={
                                        search
                                            ? 'Nobody matches that'
                                            : 'Nobody to invite yet'
                                    }
                                    description={
                                        search
                                            ? 'Only people who follow you back can be invited from here.'
                                            : 'People who follow you back will be listed here, ready to invite.'
                                    }
                                />
                            ) : (
                                <ul className={listStyles}>
                                    {candidates.map((person) => (
                                        <li
                                            key={person.id}
                                            className="px-4 py-3"
                                        >
                                            <PersonRow
                                                person={person}
                                                action={
                                                    person.is_member ? (
                                                        <Badge variant="secondary">
                                                            Member
                                                        </Badge>
                                                    ) : person.is_blocked ? (
                                                        <Badge variant="destructive">
                                                            Blocked
                                                        </Badge>
                                                    ) : person.invite_status ===
                                                      'pending' ? (
                                                        <Badge variant="outline">
                                                            Invited
                                                        </Badge>
                                                    ) : (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                invite(person)
                                                            }
                                                        >
                                                            <UserPlus className="size-4" />
                                                            {person.invite_status ===
                                                            'declined'
                                                                ? 'Ask again'
                                                                : 'Invite'}
                                                        </Button>
                                                    )
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SettingsSection>

                        {invitations.length > 0 && (
                            <SettingsSection
                                title="Waiting on an answer"
                                description="Sent, but not yet accepted or declined."
                            >
                                <ul className={listStyles}>
                                    {invitations.map((invitation) => (
                                        <li
                                            key={invitation.id}
                                            className="px-4 py-3"
                                        >
                                            <PersonRow
                                                person={invitation.user}
                                                action={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            revoke(invitation)
                                                        }
                                                    >
                                                        Take back
                                                    </Button>
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </SettingsSection>
                        )}

                        <SettingsSection
                            title="Members"
                            description="Removing takes back this membership. Blocking also stops the next one."
                        >
                            <ul className={listStyles}>
                                {members.data.map((member) => (
                                    <li key={member.id} className="px-4 py-3">
                                        <PersonRow
                                            person={member}
                                            action={
                                                member.is_owner ? (
                                                    <Badge variant="secondary">
                                                        Owner
                                                    </Badge>
                                                ) : (
                                                    <>
                                                        {circle.can_transfer_ownership && (
                                                            <ConfirmDialog
                                                                tooltip="Make this member the owner"
                                                                title={`Hand ${circle.name} to ${member.full_name}?`}
                                                                description={`They will be able to rename the circle, invite, remove and block members, and delete it. ${
                                                                    circle.owner
                                                                        ? `${circle.owner.full_name} stays a member, but loses all of that.`
                                                                        : 'Nobody can do any of that today.'
                                                                }`}
                                                                confirmLabel="Hand it over"
                                                                onConfirm={() =>
                                                                    router.patch(
                                                                        circleOwner.url(
                                                                            circle.id,
                                                                        ),
                                                                        {
                                                                            owner_id:
                                                                                member.id,
                                                                        },
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                                trigger={
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-muted-foreground"
                                                                    >
                                                                        <Crown className="size-4" />
                                                                        <span className="sr-only">
                                                                            Make{' '}
                                                                            {
                                                                                member.full_name
                                                                            }{' '}
                                                                            the
                                                                            owner
                                                                        </span>
                                                                    </Button>
                                                                }
                                                            />
                                                        )}

                                                        <ConfirmDialog
                                                            tooltip="Remove from circle"
                                                            title={`Remove ${member.full_name}?`}
                                                            description={`They will lose access to ${circle.name} but can be invited back.`}
                                                            confirmLabel="Remove"
                                                            onConfirm={() =>
                                                                router.delete(
                                                                    CircleManagementController.removeMember.url(
                                                                        scoped(
                                                                            member.id,
                                                                        ),
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            trigger={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-muted-foreground"
                                                                >
                                                                    <UserMinus className="size-4" />
                                                                    <span className="sr-only">
                                                                        Remove{' '}
                                                                        {
                                                                            member.full_name
                                                                        }
                                                                    </span>
                                                                </Button>
                                                            }
                                                        />

                                                        <ConfirmDialog
                                                            tooltip="Block from circle"
                                                            title={`Block ${member.full_name}?`}
                                                            description="They will be removed from the circle, cannot rejoin, and any invitation still standing is cancelled."
                                                            confirmLabel="Block"
                                                            destructive
                                                            onConfirm={() =>
                                                                router.post(
                                                                    CircleManagementController.block.url(
                                                                        scoped(
                                                                            member.id,
                                                                        ),
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            trigger={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-muted-foreground"
                                                                >
                                                                    <Ban className="size-4" />
                                                                    <span className="sr-only">
                                                                        Block{' '}
                                                                        {
                                                                            member.full_name
                                                                        }
                                                                    </span>
                                                                </Button>
                                                            }
                                                        />
                                                    </>
                                                )
                                            }
                                        />
                                    </li>
                                ))}
                            </ul>

                            <Pagination page={members} label="members" />
                        </SettingsSection>

                        {blocked.length > 0 && (
                            <SettingsSection
                                title="Blocked"
                                description="They cannot join or be invited. Unblocking clears the bar; it does not put them back in."
                            >
                                <ul className={listStyles}>
                                    {blocked.map((person) => (
                                        <li
                                            key={person.id}
                                            className="px-4 py-3"
                                        >
                                            <PersonRow
                                                person={person}
                                                action={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.delete(
                                                                CircleManagementController.unblock.url(
                                                                    scoped(
                                                                        person.id,
                                                                    ),
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Unblock
                                                    </Button>
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </SettingsSection>
                        )}
                    </div>

                    <div
                        className={
                            tab === 'danger' ? 'flex flex-col gap-10' : 'hidden'
                        }
                    >
                        <SettingsSection
                            title="Delete this circle"
                            description="The circle and every membership in it go. Posts shared into it stay with their authors."
                            danger
                        >
                            <ConfirmDialog
                                title={`Delete ${circle.name}?`}
                                description="This cannot be undone. Every membership, invitation and block goes with it."
                                confirmLabel="Delete circle"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        CircleManagementController.destroy.url(
                                            circle.id,
                                        ),
                                    )
                                }
                                trigger={
                                    <Button variant="destructive">
                                        <Trash2 className="size-4" />
                                        Delete circle
                                    </Button>
                                }
                            />
                        </SettingsSection>
                    </div>
                </div>
            </Page>
        </>
    );
}
