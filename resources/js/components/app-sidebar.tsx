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
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    CreditCard,
    Download,
    FileArchive,
    Gauge,
    LayoutGrid,
    Package,
    Settings,
    ShieldCheck,
    Tag,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const userNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Downloads', url: '/downloads', icon: Download },
    { title: 'Biblioteca', url: '/library', icon: FileArchive },
    { title: 'Créditos', url: '/billing', icon: CreditCard },
];

const adminNavItems: NavItem[] = [
    { title: 'Visão geral', url: '/admin', icon: Gauge },
    { title: 'Usuários', url: '/admin/users', icon: Users },
    { title: 'Providers', url: '/admin/providers', icon: Tag },
    { title: 'Regras de preço', url: '/admin/pricing', icon: ShieldCheck },
    { title: 'Pacotes', url: '/admin/packages', icon: Package },
    { title: 'Configurações', url: '/admin/settings', icon: Settings },
    { title: 'Auditoria', url: '/admin/audit', icon: ShieldCheck },
];

export function AppSidebar() {
    const auth = usePage<{ auth?: { user?: { is_admin?: boolean } | null } }>().props.auth;
    const isAdmin = !!auth?.user?.is_admin;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={userNavItems} label="Plataforma" />
                {isAdmin && <NavMain items={adminNavItems} label="Admin" />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
