/** تنسيق موحّد للنوافذ المنبثقة على الجوال — لوحة أصغر مع هامش من الحواف */

export const mobileSheetBackdrop =
    'fixed inset-0 z-50 flex flex-col justify-end bg-black/40 p-3 pt-10 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] sm:items-center sm:justify-center sm:p-4 sm:pt-4';

export const mobileSheetPanelMd =
    'w-full max-h-[min(76dvh,28rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-xl sm:mx-auto sm:max-h-[90vh] sm:max-w-md sm:rounded-2xl';

export const mobileSheetPanelLg =
    'w-full max-h-[min(80dvh,34rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-xl sm:mx-auto sm:max-h-[90vh] sm:max-w-2xl sm:rounded-2xl';

export const mobileSheetPanelXl =
    'w-full max-h-[min(82dvh,36rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white shadow-xl sm:mx-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-2xl';

export const mobileSheetHeader =
    'sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-gray-100 bg-white px-4 py-2.5 sm:px-5 sm:py-3';

export const mobileSheetForm =
    'grid grid-cols-1 gap-2.5 px-4 pb-4 pt-2.5 sm:gap-4 sm:px-5 sm:pb-5 md:grid-cols-2';

export const mobileSheetBody =
    'space-y-2.5 px-4 pb-4 pt-2.5 sm:space-y-3 sm:px-5 sm:pb-5 sm:pt-3';
