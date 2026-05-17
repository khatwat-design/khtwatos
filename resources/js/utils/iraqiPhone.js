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

/** جوال عراقي 07… — غالباً يدعم واتساب */
export function isLikelyIraqiMobile(phone) {
    const local = toLocalIraqiPhone(phone);
    return /^07[0-9]{9}$/.test(local);
}

/** wa.me — واتساب عادي (احتياطي) */
export function whatsAppUrl(phone, message = null) {
    const digits = toWhatsAppDigits(phone);
    if (!digits) {
        return '';
    }
    const base = `https://wa.me/${digits}`;
    if (message != null && String(message).trim() !== '') {
        return `${base}?text=${encodeURIComponent(String(message).trim())}`;
    }
    return base;
}

function whatsAppTextQuery(message) {
    if (message == null || String(message).trim() === '') {
        return { text: '', param: '' };
    }
    const text = encodeURIComponent(String(message).trim());
    return { text, param: `&text=${text}` };
}

/**
 * يفتح واتساب بزنس مباشرة (وليس واتساب الشخصي إن أمكن).
 * أندرويد: Intent بحزمة com.whatsapp.w4b
 * iOS / سطح المكتب: whatsapp://send
 */
export function whatsAppBusinessUrl(phone, message = null) {
    const digits = toWhatsAppDigits(phone);
    if (!digits) {
        return '';
    }

    const { text, param } = whatsAppTextQuery(message);
    const schemeUrl = `whatsapp://send?phone=${digits}${param}`;

    if (typeof navigator === 'undefined') {
        return schemeUrl;
    }

    const ua = navigator.userAgent || '';
    if (/Android/i.test(ua)) {
        const query = `phone=${digits}${text ? `&text=${text}` : ''}`;
        return `intent://send/?${query}#Intent;scheme=whatsapp;package=com.whatsapp.w4b;end`;
    }

    return schemeUrl;
}

/** فتح واتساب بزنس مع احتياطي wa.me إذا لم يُثبَّت التطبيق */
export function openWhatsAppBusiness(phone, message = null) {
    const primary = whatsAppBusinessUrl(phone, message);
    if (!primary) {
        return false;
    }

    const fallback = whatsAppUrl(phone, message);
    const isAndroidIntent = primary.startsWith('intent://');

    if (isAndroidIntent) {
        window.location.href = primary;
    } else {
        window.open(primary, '_blank', 'noopener,noreferrer');
    }

    if (fallback) {
        window.setTimeout(() => {
            if (document.visibilityState === 'visible') {
                window.open(fallback, '_blank', 'noopener,noreferrer');
            }
        }, 1200);
    }

    return true;
}

/** رسالة افتتاحية لموظف مبيعات ليدز ميتا */
export function buildGoodsMetaWhatsAppMessage(customerName, employeeName) {
    const name = String(customerName || '').trim();
    const emp = String(employeeName || '').trim() || 'فريق خطوات';
    const honorific = name !== '' ? `استاذ ${name}` : 'استاذ';
    return `السلام عليكم شلون صحتك ${honorific} ان شاء الله تكون بخير وياك ${emp} علي موظف مبيعات في شركة خطوات سبق وان تواصل ويانا`;
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
