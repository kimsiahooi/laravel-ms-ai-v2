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
import { dashboard, home, logout } from '@/routes/admin';
import { index as tenantsIndex, trashed } from '@/routes/admin/tenants';
import type { NavItem, User } from '@/types';

const NAV: NavItem[] = [
    { title: 'Overview', href: dashboard(), icon: LayoutGrid },
    { title: 'Workspaces', href: tenantsIndex(), icon: Building2 },
    { title: 'Archive', href: trashed(), icon: Archive },
];

/**
 * Chrome for the central super-admin area. Deliberately separate from the tenant
 * AppSidebar: the two shells authenticate different guards and share no navigation,
 * and the admin's menu must never link into a workspace.
 */
export function AdminSidebar({ user }: { user: User | null }) {
    const { isCurrentUrl } = useCurrentUrl();
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
                                        Console
                                    </span>
                                    <span className="truncate text-sidebar-foreground/70 text-xs">
                                        Workspace administration
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Manage</SidebarGroupLabel>
                    <SidebarMenu>
                        {NAV.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
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
                                        Sign out
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
