/**
 * تحويل أرقام العراق (964 / p:+964 / 07…) لنسخ محلي أو فتح واتساب.
 */

export function digitsOnly(phone) {
    return String(phone || '').replace(/\D/g, '');
}

/** للواتساب: 9647xxxxxxxxxx */
export function toWhatsAppDigits(phone) {
    let d = digitsOnly(phone);
    if (!d) {
        return '';
    }
    if (d.startsWith('964') && d.length >= 12) {
        return d.slice(0, 13);
    }
    if (d.startsWith('0')) {
        d = '964'.concat(d.slice(1));
    } else if (d.startsWith('7')) {
        d = '964'.concat(d);
    } else {
        d = '964'.concat(d);
    }

    return d.length >= 12 ? d : '';
}

/** للاتصال والنسخ: 07xxxxxxxxxx */
export function toLocalIraqiPhone(phone) {
    let d = digitsOnly(phone);
    if (!d) {
        return '';
    }
    if (d.startsWith('964')) {
        d = d.slice(3);
    }
    if (d.startsWith('0')) {
        return d.slice(0, 11);
    }
    if (d.startsWith('7')) {
        return `0${d.slice(0, 10)}`;
    }

    return `0${d.slice(0, 10)}`;
}

export function whatsAppUrl(phone) {
    const digits = toWhatsAppDigits(phone);
    return digits ? `https://wa.me/${digits}` : '';
}

export async function copyText(text) {
    if (!text) {
        return false;
    }
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'absolute';
        el.style.left = '-9999px';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    }
}
