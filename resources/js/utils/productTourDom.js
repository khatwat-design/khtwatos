/**
 * أدوات DOM للجولات — عناصر ظاهرة فقط (تجنب تأشير مخفي على الجوال).
 */

export function isTourElementVisible(el) {
    if (!el || !(el instanceof Element)) {
        return false;
    }

    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
        return false;
    }

    const rect = el.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2) {
        return false;
    }

    return true;
}

/**
 * @param {string|string[]|(() => Element|null|undefined)} target
 */
export function pickTourElement(target) {
    if (typeof target === 'function') {
        const el = target();
        return isTourElementVisible(el) ? el : null;
    }

    const selectors = Array.isArray(target) ? target : [target];

    for (const selector of selectors) {
        if (!selector) {
            continue;
        }
        const nodes = document.querySelectorAll(selector);
        for (const el of nodes) {
            if (isTourElementVisible(el)) {
                return el;
            }
        }
    }

    return null;
}

/** رابط قائمة ظاهر (سطح المكتب أو الشريط السفلي) */
export function pickTourNavLink(routeName) {
    return pickTourElement([
        `[data-tour="nav-${routeName}"]`,
        `[data-tour-mobile-nav="${routeName}"]`,
    ]);
}

/** أول بطاقة/محتوى واضح داخل الصفحة الحالية */
export function pickTourPageAnchor() {
    return () =>
        pickTourElement([
            '[data-tour-page-anchor]',
            '[data-tour-page-root] .ops-surface',
            '[data-tour-page-root] .ui-card',
            '[data-tour-page-root] > div > section',
        ]);
}

export function tourStep(element, title, description, side = 'bottom') {
    const resolved =
        typeof element === 'function'
            ? element
            : element
              ? () => pickTourElement(element)
              : undefined;

    return {
        element: resolved,
        popover: {
            title,
            description,
            side,
            align: 'center',
        },
    };
}
