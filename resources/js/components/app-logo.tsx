import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

type SharedProps = { brand?: { name?: string } };

export default function AppLogo() {
    const brand = usePage<SharedProps>().props.brand;
    const name = brand?.name ?? 'UseStock';

    return (
        <>
            <div className="bg-primary text-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{name}</span>
            </div>
        </>
    );
}
