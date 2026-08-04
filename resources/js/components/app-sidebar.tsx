import { Link, usePage } from '@inertiajs/react';
import {
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
import { circles as adminCircles, users as adminUsers } from '@/routes/admin';
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
     * Staff get "All circles" instead of "Circles", not as well as it.
     *
     * The member entry lists the circles you happen to belong to, which is not
     * the question an admin has — and the staff list already contains them.
     * Two entries a word apart, one a subset of the other, is a choice nobody
     * should have to make on the way to the same place.
     */
    const groups = auth.user?.is_admin
        ? [
              ...navGroups.filter((group) => group.label !== 'Community'),
              adminGroup,
          ]
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
