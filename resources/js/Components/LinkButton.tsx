import clsx from 'clsx';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { AnchorHTMLAttributes, ReactNode } from 'react';

type LinkButtonVariant = 'primary' | 'secondary' | 'danger';
type LinkButtonSize = 'sm' | 'md' | 'lg';

type CommonLinkButtonProps = {
    variant?: LinkButtonVariant;
    size?: LinkButtonSize;
    icon?: ReactNode;
    children: ReactNode;
    className?: string;
};

type InertiaLinkButtonProps = CommonLinkButtonProps &
    Omit<InertiaLinkProps, 'size' | 'native'> & {
        native?: false;
    };

type NativeLinkButtonProps = CommonLinkButtonProps &
    Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'size' | 'href' | 'children' | 'className'> & {
        native: true;
        href: string;
    };

type LinkButtonProps = InertiaLinkButtonProps | NativeLinkButtonProps;

const variantClasses: Record<LinkButtonVariant, string> = {
    primary: clsx(
        'bg-emerald-500 hover:bg-emerald-600 text-white',
        'shadow-[0_4px_14px_-3px_rgba(16,185,129,0.35)]',
        'hover:shadow-[0_8px_20px_-4px_rgba(16,185,129,0.4)]',
    ),
    secondary: clsx(
        'bg-slate-50 hover:bg-white text-slate-600',
        'border border-slate-200 hover:border-slate-300',
        'shadow-sm',
    ),
    danger: clsx(
        'bg-rose-500 hover:bg-rose-600 text-white',
        'shadow-sm hover:shadow-md',
    ),
};

const sizeClasses: Record<LinkButtonSize, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-6 py-3 text-sm',
};

function linkButtonClasses(
    variant: LinkButtonVariant,
    size: LinkButtonSize,
    className: string,
): string {
    return clsx(
        'inline-flex items-center justify-center gap-2',
        'rounded-xl font-semibold',
        'transition-all duration-200 active:scale-95',
        'focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
        variantClasses[variant],
        sizeClasses[size],
        className,
    );
}

export default function LinkButton(props: LinkButtonProps) {
    const {
        variant = 'primary',
        size = 'md',
        icon,
        className = '',
        children,
    } = props;
    const classes = linkButtonClasses(variant, size, className);

    if (props.native) {
        const { native: _native, variant: _v, size: _s, icon: _i, className: _c, children: _ch, href, ...anchorProps } =
            props;

        return (
            <a href={href} className={classes} {...anchorProps}>
                {icon && <span className="shrink-0">{icon}</span>}
                {children}
            </a>
        );
    }

    const { native: _native, variant: _v, size: _s, icon: _i, className: _c, children: _ch, ...linkProps } = props;

    return (
        <Link {...linkProps} className={classes}>
            {icon && <span className="shrink-0">{icon}</span>}
            {children}
        </Link>
    );
}
