export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes || bytes <= 0) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let v = bytes;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(v < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
}

export function formatBRL(cents: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}

export function formatNumber(n: number): string {
    return new Intl.NumberFormat('pt-BR').format(n);
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('pt-BR');
}

export const STATUS_LABELS: Record<string, string> = {
    queued: 'Na fila',
    resolving: 'Identificando',
    requesting: 'Solicitando',
    downloading: 'Baixando',
    ready: 'Pronto',
    expired: 'Expirado',
    failed: 'Falhou',
    refunded: 'Estornado',
};

export const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    queued: 'secondary',
    resolving: 'secondary',
    requesting: 'secondary',
    downloading: 'secondary',
    ready: 'default',
    expired: 'outline',
    failed: 'destructive',
    refunded: 'destructive',
};
