import { Link, router } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Settings } from 'lucide-react';
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
import { tenantNavGroups } from '@/config/tenant-nav';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { Tenant, User } from '@/types';

/**
 * Chrome for a workspace. Separate from AdminSidebar on purpose: the two authenticate
 * different guards, share no navigation, and a link from one into the other would be a
 * link into a different database.
 *
 * The header names the workspace rather than the product. Someone signed into two
 * workspaces in two tabs needs to know which one they are looking at, and the product
 * name is the same in both.
 */
export function TenantSidebar({
    user,
    tenant,
}: {
    user: User | null;
    tenant: Tenant | null;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useTranslation();
    const { can } = usePermissions();
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    // Filtered here rather than in the render below, so the palette can ask for exactly
    // the same list and the two can never show different navigation.
    const groups = tenantNavGroups(can);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                    <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                                </div>
                                <div className="ml-1 grid flex-1 text-left text-sm">
                                    <span className="truncate font-semibold leading-tight">
                                        {tenant?.name ?? t('tenant.name')}
                                    </span>
                                    <span className="truncate text-sidebar-foreground/70 text-xs">
                                        {tenant ? `/${tenant.slug}` : ''}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group, index) => (
                    <SidebarGroup
                        // Groups are a static list; an unlabelled one has no other
                        // stable identity, so the index is the honest key here.
                        key={group.label ?? `group-${index}`}
                        className="px-2 py-0"
                    >
                        {group.label && (
                            <SidebarGroupLabel>
                                {t(group.label)}
                            </SidebarGroupLabel>
                        )}
                        <SidebarMenu>
                            {group.items.map((item) => (
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
                ))}
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
                                    {/* Account settings belong to the person, not the
                                        workspace, so they live here rather than in the
                                        navigation beside the workspace's own screens. */}
                                    <DropdownMenuItem asChild>
                                        <Link href={editProfile()}>
                                            <Settings className="mr-2 size-4" />
                                            {t('tenant.nav.settings')}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() => {
                                            router.post(
                                                logout().url,
                                                {},
                                                {
                                                    // The next person to sign in must
                                                    // not see this one's cached pages.
                                                    onSuccess: () =>
                                                        router.flushAll(),
                                                },
                                            );
                                        }}
                                    >
                                        <LogOut className="mr-2 size-4" />
                                        {t('tenant.nav.sign_out')}
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
