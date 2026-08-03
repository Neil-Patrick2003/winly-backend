import type { ImgHTMLAttributes } from 'react';

/**
 * The brand mark, served from `public/welle_logo.png`.
 *
 * Decorative by default: every caller sits next to the app name or a labelled
 * link, so an empty alt keeps screen readers from reading the brand twice.
 * Pass `alt` to override where the mark stands alone.
 */
export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img src="/welle_logo.png" alt="" {...props} />;
}
