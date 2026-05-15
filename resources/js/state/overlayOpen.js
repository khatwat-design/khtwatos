import { ref } from 'vue';

/** عدد النوافذ/الأوراق المفتوحة — لإخفاء الشريط السفلي على الجوال */
export const overlayOpenCount = ref(0);

export function registerOverlayOpen() {
    overlayOpenCount.value += 1;
    if (typeof document !== 'undefined') {
        document.body.classList.add('app-overlay-open');
        document.body.style.overflow = 'hidden';
    }
}

export function registerOverlayClose() {
    overlayOpenCount.value = Math.max(0, overlayOpenCount.value - 1);
    if (typeof document !== 'undefined' && overlayOpenCount.value === 0) {
        document.body.classList.remove('app-overlay-open');
        document.body.style.overflow = '';
    }
}
