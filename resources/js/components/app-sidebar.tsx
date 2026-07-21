import { Link } from '@inertiajs/react';
import {
    AudioLines,
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Settings2,
    Sparkles,
} from 'lucide-react';
import { index as meditationCategories } from '@/actions/App/Http/Controllers/MeditationCategoryController';
import { index as meditations } from '@/actions/App/Http/Controllers/MeditationController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { edit as editProfile } from '@/routes/profile';
import type { NavGroup, NavItem } from '@/types';

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
        label: 'Content library',
        items: [
            {
                title: 'Meditations',
                href: meditations(),
                icon: AudioLines,
            },
            {
                title: 'Categories',
                href: meditationCategories(),
                icon: Sparkles,
            },
        ],
    },
    {
        label: 'Workspace',
        items: [
            {
                title: 'Settings',
                href: editProfile(),
                icon: Settings2,
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-b border-sidebar-border/60 pb-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4 pt-2">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-sidebar-border/60">
                <NavFooter items={footerNavItems} />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
