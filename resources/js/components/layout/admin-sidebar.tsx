import { Link, router } from '@inertiajs/react';
import {
    Archive,
    Building2,
    ChevronsUpDown,
    LayoutGrid,
    LogOut,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, home, logout } from '@/routes/admin';
import { index as tenantsIndex, trashed } from '@/routes/admin/tenants';
import type { User } from '@/types';
import type { TranslationKey } from '@/types/lang';

// Titles are keys, resolved at render — a NavItem carries plain text, and this list
// lives at module scope where there is no locale.
const NAV: {
    title: TranslationKey;
    href: ReturnType<typeof dashboard>;
    icon: typeof LayoutGrid;
}[] = [
    { title: 'console.nav.overview', href: dashboard(), icon: LayoutGrid },
    { title: 'console.nav.workspaces', href: tenantsIndex(), icon: Building2 },
    { title: 'console.nav.archive', href: trashed(), icon: Archive },
];

/**
 * Chrome for the central super-admin area. Deliberately separate from the tenant
 * AppSidebar: the two shells authenticate different guards and share no navigation,
 * and the admin's menu must never link into a workspace.
 */
export function AdminSidebar({ user }: { user: User | null }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useTranslation();
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home()} prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                                </div>
                                <div className="ml-1 grid flex-1 text-left text-sm">
                                    <span className="truncate font-semibold leading-tight">
                                        {t('console.name')}
                                    </span>
                                    <span className="truncate text-sidebar-foreground/70 text-xs">
                                        {t('console.tagline')}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>
                        {t('console.nav.group')}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {NAV.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: t(item.title) }}
                                >
                                    <Link href={item.href} prefetch>
                                        <item.icon />
                                        <span>{t(item.title)}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            {user && (
                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        size="lg"
                                        className="text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                                    >
                                        <UserInfo user={user} />
                                        <ChevronsUpDown className="ml-auto size-4" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                    align="end"
                                    side={
                                        isMobile || state === 'expanded'
                                            ? 'bottom'
                                            : 'right'
                                    }
                                >
                                    <DropdownMenuLabel className="p-0 font-normal">
                                        <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                            <UserInfo user={user} showEmail />
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            router.post(
                                                logout().url,
                                                {},
                                                {
                                                    onSuccess: () =>
                                                        router.flushAll(),
                                                },
                                            );
                                        }}
                                    >
                                        <LogOut className="mr-2 size-4" />
                                        {t('console.nav.sign_out')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            )}
        </Sidebar>
    );
}
