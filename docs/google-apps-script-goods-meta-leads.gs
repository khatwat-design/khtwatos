/**
 * ═══════════════════════════════════════════════════════════════════
 *  مزامنة ليدز ميتا → نظام خطوات (قسم البضاعة → تبويب «ليدز ميتا»)
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1) Extensions → Apps Script → الصق هذا الملف بالكامل
 * 2) عدّل CONFIG (السرّ يجب أن يطابق GOODS_META_LEADS_WEBHOOK_SECRET على السيرفر)
 * 3) من القائمة اختر syncAllRows_ → Run (أول مرة يطلب صلاحية UrlFetch)
 * 4) شغّل setup_ مرة واحدة لإنشاء مزامنة تلقائية كل 5 دقائق
 *
 * البيانات تظهر في: البضاعة → تبويب «ليدز ميتا»
 * أو مباشرة: /goods?tab=meta_leads
 */

const CONFIG = {
  API_URL: 'https://os.kharijm.com/goods/meta-leads/sync',
  WEBHOOK_SECRET: 'ضع_نفس_قيمة_GOODS_META_LEADS_WEBHOOK_SECRET_من_env',
  SHEET_NAME: '', // اتركه فارغاً = الشيت النشط، أو اسم الشيت مثل: 'Sheet1'
  HEADER_ROW: 1,
  BATCH_SIZE: 40,
};

/** شغّل مرة واحدة لإنشاء مشغّل زمني */
function setup_() {
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(function (t) {
    if (t.getHandlerFunction() === 'syncAllRows_') {
      ScriptApp.deleteTrigger(t);
    }
  });
  ScriptApp.newTrigger('syncAllRows_')
    .timeBased()
    .everyMinutes(5)
    .create();
}

/** عند تعديل صف في الشيت (اختياري — قد يحتاج تفعيل trigger من نوع onEdit) */
function onEdit(e) {
  if (!e || !e.range) return;
  const sheet = e.range.getSheet();
  const row = e.range.getRow();
  if (row <= CONFIG.HEADER_ROW) return;
  try {
    syncRowByNumber_(sheet, row);
  } catch (err) {
    console.error('onEdit sync failed:', err);
  }
}

/** مزامنة كل الصفوف — استخدمها للاختبار أو من المشغّل */
function syncAllRows_() {
  const sheet = getSheet_();
  const lastRow = sheet.getLastRow();
  if (lastRow <= CONFIG.HEADER_ROW) return;

  const lastCol = sheet.getLastColumn();
  const headers = sheet.getRange(CONFIG.HEADER_ROW, 1, 1, lastCol).getValues()[0];
  const allRows = [];

  for (let r = CONFIG.HEADER_ROW + 1; r <= lastRow; r++) {
    const values = sheet.getRange(r, 1, 1, lastCol).getValues()[0];
    const rowObj = rowObject_(headers, values, r);
    if (rowObj.id) {
      allRows.push(rowObj);
    }
  }

  if (!allRows.length) return;

  for (let i = 0; i < allRows.length; i += CONFIG.BATCH_SIZE) {
    const chunk = allRows.slice(i, i + CONFIG.BATCH_SIZE);
    postToApi_({ rows: chunk });
  }
}

function syncRowByNumber_(sheet, rowNumber) {
  const lastCol = sheet.getLastColumn();
  const headers = sheet.getRange(CONFIG.HEADER_ROW, 1, 1, lastCol).getValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastCol).getValues()[0];
  const rowObj = rowObject_(headers, values, rowNumber);
  if (!rowObj.id) return;
  postToApi_({ rows: [rowObj] });
}

function getSheet_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (CONFIG.SHEET_NAME) {
    const named = ss.getSheetByName(CONFIG.SHEET_NAME);
    if (!named) {
      throw new Error('الشيت غير موجود: ' + CONFIG.SHEET_NAME);
    }
    return named;
  }
  return ss.getActiveSheet();
}

function rowObject_(headers, values, rowNumber) {
  const obj = { _row_number: rowNumber };
  headers.forEach(function (h, i) {
    if (!h) return;
    const key = String(h).trim();
    obj[key] = cellValue_(values[i]);
  });
  return obj;
}

function cellValue_(value) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, Session.getScriptTimeZone(), "yyyy-MM-dd'T'HH:mm:ssXXX");
  }
  return value;
}

function postToApi_(payload) {
  const res = UrlFetchApp.fetch(CONFIG.API_URL, {
    method: 'post',
    contentType: 'application/json',
    muteHttpExceptions: true,
    headers: {
      'X-Goods-Meta-Leads-Secret': CONFIG.WEBHOOK_SECRET,
    },
    payload: JSON.stringify(payload),
  });
  const code = res.getResponseCode();
  const body = res.getContentText();
  if (code < 200 || code >= 300) {
    throw new Error('API ' + code + ': ' + body);
  }
  Logger.log('OK ' + code + ' ' + body);
}
