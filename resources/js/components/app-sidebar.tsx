import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    UserCog,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import {
    circles as adminCircles,
    dashboard as adminDashboard,
    users as adminUsers,
} from '@/routes/admin';
import { index as circlesIndex } from '@/routes/circles';
import { edit as editProfile } from '@/routes/profile';
import type { NavGroup } from '@/types';

/**
 * The staff screens, shown only to staff.
 *
 * Kept out of `navGroups` rather than filtered inside it, so that for everybody
 * else these entries are never built at all — a nav that quietly carried the
 * admin routes and only hid them would be telling every member they exist.
 */
const adminGroup: NavGroup = {
    label: 'Admin',
    items: [
        {
            title: 'Platform health',
            href: adminDashboard(),
            icon: Activity,
        },
        {
            title: 'People',
            href: adminUsers(),
            icon: UserCog,
        },
        {
            title: 'All circles',
            href: adminCircles(),
            icon: ShieldCheck,
        },
    ],
};

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Community',
        items: [
            {
                title: 'Circles',
                href: circlesIndex(),
                icon: Users,
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                title: 'Settings',
                href: editProfile(),
                icon: Settings2,
            },
        ],
    },
];

/**
 * The navigation rail: folds to icons on desktop, slides away on a phone.
 *
 * `collapsible="icon"` rather than `"none"` — the latter looks like the way to
 * say "does not fold" and is a trap: it returns before the mobile branch,
 * leaving a 16rem rail pinned across a phone with no way to dismiss it, and
 * drops the fixed positioning that keeps the rail still while a page scrolls.
 */
export function AppSidebar() {
    const { auth } = usePage().props;

    /*
     * Staff get the admin entries instead of the member ones, not as well as
     * them. "Platform health" is an admin's dashboard and "All circles" is
     * their circle list — each a superset of the member entry it replaces, so
     * keeping both would offer a choice between two doors to the same room.
     *
     * System sits last for staff. It is the one group that is about your own
     * account rather than the platform, so it belongs under the work rather
     * than above it.
     */
    const groups = auth.user?.is_admin
        ? [adminGroup, ...navGroups.filter((group) => group.label === 'System')]
        : navGroups;

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader className="px-4 py-5">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent active:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-6 px-2 py-2">
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
