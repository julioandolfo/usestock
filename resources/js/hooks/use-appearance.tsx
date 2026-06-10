import { useCallback, useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'appearance';

function prefersDark(): boolean {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyTheme(appearance: Appearance): void {
    if (typeof document === 'undefined') return;
    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', isDark);
    // Native form controls follow the theme.
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
}

function readSaved(): Appearance {
    if (typeof window === 'undefined') return 'system';
    const v = window.localStorage.getItem(STORAGE_KEY);
    return v === 'light' || v === 'dark' || v === 'system' ? v : 'system';
}

let systemListenerAttached = false;

function ensureSystemListener(): void {
    if (typeof window === 'undefined' || systemListenerAttached) return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    mq.addEventListener('change', () => {
        if (readSaved() === 'system') {
            applyTheme('system');
        }
    });
    systemListenerAttached = true;
}

export function initializeTheme(): void {
    applyTheme(readSaved());
    ensureSystemListener();
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>(() => readSaved());

    const updateAppearance = useCallback((mode: Appearance) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, mode);
        } catch {
            /* localStorage may be blocked */
        }
        applyTheme(mode);
        setAppearance(mode);
    }, []);

    useEffect(() => {
        ensureSystemListener();
        applyTheme(readSaved());

        const onStorage = (e: StorageEvent) => {
            if (e.key !== STORAGE_KEY) return;
            const v = readSaved();
            applyTheme(v);
            setAppearance(v);
        };
        window.addEventListener('storage', onStorage);
        return () => window.removeEventListener('storage', onStorage);
    }, []);

    return { appearance, updateAppearance };
}
