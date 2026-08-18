/** يطابق `lg:hidden` / فتح لوحة المحادثة على الجوال والتابلت العمودي */
export const CHAT_MOBILE_MEDIA = '(max-width: 1023px)';

export function isChatMobileViewport() {
    return typeof window !== 'undefined' && window.matchMedia(CHAT_MOBILE_MEDIA).matches;
}
