/**
 * مزامنة ليدز ميتا من كل أوراق الشيت → نظام خطوات (البضاعة → ليدز ميتا)
 *
 * 1) Extensions → Apps Script → الصق/حدّث هذا الملف
 * 2) WEBHOOK_SECRET = نفس GOODS_META_LEADS_WEBHOOK_SECRET
 * 3) Run → syncMetaLeadsNow
 * 4) Run → setupMetaLeadsAutoSync
 */

const CONFIG = {
  API_URL: 'https://os.kharijm.com/goods/meta-leads/sync',
  WEBHOOK_SECRET: 'ضع_نفس_قيمة_GOODS_META_LEADS_WEBHOOK_SECRET_من_env',
  /** true = كل الأوراق (Sheet1، نبراس، زينب، …) */
  SYNC_ALL_SHEETS: true,
  /** إن وُجدت: مزامنة هذه الأوراق فقط (تجاهل SYNC_ALL_SHEETS) */
  SHEET_NAMES: [],
  /** أوراق تُتخطى (اختياري) */
  SKIP_SHEET_NAMES: [],
  HEADER_ROW: 1,
  BATCH_SIZE: 40,
};

function syncMetaLeadsNow() {
  syncAllRows_();
}

function setupMetaLeadsAutoSync() {
  setup_();
}

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

function onEdit(e) {
  if (!e || !e.range) return;
  const sheet = e.range.getSheet();
  const row = e.range.getRow();
  if (row <= CONFIG.HEADER_ROW) return;
  if (shouldSkipSheet_(sheet.getName())) return;
  try {
    syncRowByNumber_(sheet, row);
  } catch (err) {
    console.error('onEdit sync failed:', err);
  }
}

function syncAllRows_() {
  const sheets = getSheetsToSync_();
  const allRows = [];

  sheets.forEach(function (sheet) {
    const rows = collectRowsFromSheet_(sheet);
    if (rows.length) {
      allRows.push.apply(allRows, rows);
    }
  });

  if (!allRows.length) {
    Logger.log('No rows with id found in any sheet.');
    return;
  }

  Logger.log('Syncing ' + allRows.length + ' rows from ' + sheets.length + ' sheet(s).');

  for (let i = 0; i < allRows.length; i += CONFIG.BATCH_SIZE) {
    postToApi_({ rows: allRows.slice(i, i + CONFIG.BATCH_SIZE) });
  }
}

function collectRowsFromSheet_(sheet) {
  const sheetName = sheet.getName();
  const lastRow = sheet.getLastRow();
  if (lastRow <= CONFIG.HEADER_ROW) {
    return [];
  }

  const lastCol = sheet.getLastColumn();
  if (lastCol < 1) {
    return [];
  }

  const headers = sheet.getRange(CONFIG.HEADER_ROW, 1, 1, lastCol).getValues()[0];
  const rows = [];

  for (let r = CONFIG.HEADER_ROW + 1; r <= lastRow; r++) {
    const values = sheet.getRange(r, 1, 1, lastCol).getValues()[0];
    const rowObj = rowObject_(headers, values, r, sheetName);
    if (rowObj.id) {
      rows.push(rowObj);
    }
  }

  return rows;
}

function syncRowByNumber_(sheet, rowNumber) {
  const lastCol = sheet.getLastColumn();
  const headers = sheet.getRange(CONFIG.HEADER_ROW, 1, 1, lastCol).getValues()[0];
  const values = sheet.getRange(rowNumber, 1, 1, lastCol).getValues()[0];
  const rowObj = rowObject_(headers, values, rowNumber, sheet.getName());
  if (!rowObj.id) return;
  postToApi_({ rows: [rowObj] });
}

function getSheetsToSync_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  if (CONFIG.SHEET_NAMES && CONFIG.SHEET_NAMES.length > 0) {
    return CONFIG.SHEET_NAMES.map(function (name) {
      return ss.getSheetByName(name);
    }).filter(function (s) {
      return s !== null;
    });
  }

  if (CONFIG.SYNC_ALL_SHEETS !== false) {
    return ss.getSheets().filter(function (sheet) {
      return !shouldSkipSheet_(sheet.getName());
    });
  }

  return [ss.getActiveSheet()];
}

function shouldSkipSheet_(name) {
  if (!CONFIG.SKIP_SHEET_NAMES || !CONFIG.SKIP_SHEET_NAMES.length) {
    return false;
  }
  return CONFIG.SKIP_SHEET_NAMES.indexOf(name) !== -1;
}

function rowObject_(headers, values, rowNumber, sheetName) {
  const obj = {
    _row_number: rowNumber,
    _sheet_name: sheetName,
  };
  headers.forEach(function (h, i) {
    if (!h) return;
    obj[String(h).trim()] = cellValue_(values[i]);
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
