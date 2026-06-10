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

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    const today = new Date();
    const sameDay = d.toDateString() === today.toDateString();
    if (sameDay) {
        return `hoje ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
    }
    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

/**
 * Brazilian phone mask. Accepts whatever the user types and returns a
 * formatted view limited to 11 digits.
 *
 *   "" → ""
 *   "3" → "(3"
 *   "35" → "(35) "
 *   "35991" → "(35) 991"
 *   "3599180" → "(35) 9180-"  (10 digit fallback while typing)
 *   "35991803209" → "(35) 99180-3209"
 */
export function formatPhoneMask(raw: string): string {
    const digits = (raw || '').replace(/\D+/g, '').slice(0, 11);
    if (digits.length === 0) return '';
    if (digits.length <= 2) return `(${digits}`;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export function phoneDigits(raw: string | null | undefined): string {
    return (raw || '').replace(/\D+/g, '');
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

export const IN_PROGRESS_STATUSES = new Set(['queued', 'resolving', 'requesting', 'downloading']);

export function isInProgress(status: string): boolean {
    return IN_PROGRESS_STATUSES.has(status);
}
