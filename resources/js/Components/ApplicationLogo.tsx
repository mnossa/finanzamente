import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/finanzamente-logo.webp" alt="Logo FinanzaMente" {...props} />;
}
