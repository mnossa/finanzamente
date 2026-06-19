import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/finanzamente-logo.webp" alt="Logo Finanzamente" width={32} height={32} {...props} />;
}
