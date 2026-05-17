/**
 * أصناف النوافذ المنبثقة الموحّدة (تُعرَّف في resources/css/modals.css).
 * محاذاة وسط على كل الأجهزة — خلفية بيضاء، أحجام متدرجة.
 */

export const mobileSheetBackdrop = 'app-modal-backdrop';

export const mobileSheetPanelBase = 'app-modal-panel';

export const mobileSheetPanelSm = 'app-modal-panel app-modal-panel--sm';
export const mobileSheetPanelMd = 'app-modal-panel app-modal-panel--md';
export const mobileSheetPanelLg = 'app-modal-panel app-modal-panel--lg';
export const mobileSheetPanelXl = 'app-modal-panel app-modal-panel--xl';
export const mobileSheetPanel2xl = 'app-modal-panel app-modal-panel--2xl';
export const mobileSheetPanelTask = 'app-modal-panel app-modal-panel--task';

/** للمكوّن Modal.vue — يطابق maxWidth */
export const modalMobilePanelByMaxWidth = {
    sm: mobileSheetPanelSm,
    md: mobileSheetPanelMd,
    lg: mobileSheetPanelLg,
    xl: mobileSheetPanelXl,
    '2xl': mobileSheetPanel2xl,
    task: mobileSheetPanelTask,
};

export function modalPanelClassForMaxWidth(maxWidth) {
    return modalMobilePanelByMaxWidth[maxWidth] ?? modalMobilePanelByMaxWidth['2xl'];
}

export const mobileSheetHeader = 'app-modal-header';

export const mobileSheetTitle = 'app-modal-title';

export const mobileSheetCloseBtn = 'app-modal-close';

export const mobileSheetForm =
    'grid grid-cols-1 gap-2 px-4 pb-4 pt-2 sm:gap-3 sm:px-5 sm:pb-5 md:grid-cols-2';

export const mobileSheetBody = 'app-modal-body space-y-2.5 sm:space-y-3';

export const mobileSheetFooter = 'app-modal-footer';
