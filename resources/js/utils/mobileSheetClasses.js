/**
 * تنسيق موحّد للنوافذ المنبثقة على الجوال — لوحات مضغوطة (bottom sheet) مع هامش من الحواف.
 * على sm+ تظهر كنافذة مركزية بحجم مناسب.
 */

export const mobileSheetBackdrop =
    'fixed inset-0 z-[100] flex flex-col justify-end bg-black/50 p-4 pt-6 pb-[max(0.5rem,env(safe-area-inset-bottom,0px))] sm:z-50 sm:items-center sm:justify-center sm:bg-black/40 sm:p-4 sm:pt-4 sm:pb-4';

export const mobileSheetPanelBase =
    'w-full overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-xl [-webkit-overflow-scrolling:touch] sm:mx-auto sm:rounded-2xl';

/** أقصى ارتفاع على الجوال — sm ≈ تأكيد / حذف */
export const mobileSheetMaxSm = 'max-h-[min(52dvh,20rem)] sm:max-h-[90vh] sm:max-w-sm';

/** فلاتر، إعادة تعيين، نماذج قصيرة */
export const mobileSheetMaxMd = 'max-h-[min(58dvh,22rem)] sm:max-h-[90vh] sm:max-w-md';

/** إضافة عميل، تذكرة، غرفة */
export const mobileSheetMaxLg = 'max-h-[min(55dvh,21rem)] sm:max-h-[90vh] sm:max-w-lg';

/** نماذج متوسطة */
export const mobileSheetMaxXl = 'max-h-[min(68dvh,28rem)] sm:max-h-[90vh] sm:max-w-xl';

/** مهام، موظفين، اجتماعات */
export const mobileSheetMax2xl = 'max-h-[min(72dvh,30rem)] sm:max-h-[90vh] sm:max-w-2xl';

/** نماذج المهام (إنشاء/تعديل) — عرض أصغر وحد أقصى للارتفاع مع تمرير داخلي */
export const mobileSheetMaxTask = 'max-h-[min(86dvh,34rem)] sm:max-h-[min(88vh,40rem)] sm:max-w-lg lg:max-w-xl';

export const mobileSheetPanelTask = `${mobileSheetPanelBase} ${mobileSheetMaxTask}`;

export const mobileSheetPanelSm = `${mobileSheetPanelBase} ${mobileSheetMaxSm}`;
export const mobileSheetPanelMd = `${mobileSheetPanelBase} ${mobileSheetMaxMd}`;
export const mobileSheetPanelLg = `${mobileSheetPanelBase} ${mobileSheetMaxLg}`;
export const mobileSheetPanelXl = `${mobileSheetPanelBase} ${mobileSheetMaxXl}`;
export const mobileSheetPanel2xl = `${mobileSheetPanelBase} ${mobileSheetMax2xl}`;

/** للمكوّن Modal.vue — يطابق maxWidth */
export const modalMobilePanelByMaxWidth = {
    sm: mobileSheetPanelSm,
    md: mobileSheetPanelMd,
    lg: mobileSheetPanelLg,
    xl: mobileSheetPanelXl,
    '2xl': mobileSheetPanel2xl,
    task: mobileSheetPanelTask,
};

export const mobileSheetHeader =
    'sticky top-0 z-10 flex shrink-0 items-center justify-between gap-2 border-b border-gray-100 bg-white px-3.5 py-2 sm:px-4 sm:py-2.5';

export const mobileSheetTitle = 'text-sm font-bold text-gray-900 sm:text-base';

export const mobileSheetCloseBtn =
    'inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg text-sm font-medium text-gray-600 transition hover:bg-gray-100';

export const mobileSheetForm =
    'grid grid-cols-1 gap-2 px-3.5 pb-3.5 pt-2 sm:gap-3 sm:px-4 sm:pb-4 md:grid-cols-2';

export const mobileSheetBody =
    'space-y-2 px-3.5 pb-3.5 pt-2 sm:space-y-2.5 sm:px-4 sm:pb-4 sm:pt-2.5';

export const mobileSheetFooter =
    'sticky bottom-0 z-10 flex shrink-0 flex-col-reverse gap-2 border-t border-gray-100 bg-white px-3.5 py-2.5 sm:flex-row sm:justify-end sm:px-4 sm:py-3';
