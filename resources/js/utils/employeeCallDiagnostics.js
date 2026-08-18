import { ref } from 'vue';

const activeTrace = ref(null);
const steps = ref([]);
const lastServerStatus = ref(null);

function nowIso() {
    return new Date().toISOString();
}

export function startTrace(operation, meta = {}) {
    activeTrace.value = `ec-${Math.random().toString(36).slice(2, 14)}`;
    steps.value = [
        {
            at: nowIso(),
            step: 'trace.start',
            operation,
            ...meta,
        },
    ];
    console.info('[employee-call]', operation, meta);
    return activeTrace.value;
}

export function logStep(step, data = {}) {
    const row = { at: nowIso(), step, ...data };
    steps.value.push(row);
    if (steps.value.length > 80) {
        steps.value.shift();
    }
    console.info('[employee-call]', step, data);
}

export function getActiveTrace() {
    return activeTrace.value;
}

export function getSteps() {
    return [...steps.value];
}

export function formatTraceForDisplay() {
    const lines = steps.value.map((row) => {
        const extra = { ...row };
        delete extra.at;
        delete extra.step;
        const detail = Object.keys(extra).length ? ` ${JSON.stringify(extra)}` : '';
        return `${row.at} | ${row.step}${detail}`;
    });
    if (activeTrace.value) {
        lines.unshift(`trace: ${activeTrace.value}`);
    }
    return lines.join('\n');
}

export async function fetchServerStatus() {
    try {
        const res = await window.axios.get(route('chat.calls.diagnostics.status'), {
            headers: { Accept: 'application/json' },
        });
        lastServerStatus.value = res?.data?.status ?? null;
        logStep('diag.server_status', { status: lastServerStatus.value });
        return lastServerStatus.value;
    } catch (err) {
        logStep('diag.server_status_failed', {
            status: err?.response?.status,
            message: err?.response?.data?.message || err?.message,
        });
        return null;
    }
}

export async function flushToServer(error = null) {
    if (!activeTrace.value && !steps.value.length) {
        return null;
    }

    const payload = {
        trace: activeTrace.value,
        steps: steps.value,
        error: error
            ? {
                  message: error?.message || String(error),
                  name: error?.name,
                  status: error?.response?.status,
                  response_message: error?.response?.data?.message,
                  diag_trace: error?.response?.data?.diag_trace,
                  diag_debug: error?.response?.data?.diag_debug,
              }
            : null,
        meta: {
            user_agent: typeof navigator !== 'undefined' ? navigator.userAgent : '',
            echo: Boolean(window.Echo),
            reverb_key: Boolean(import.meta.env.VITE_REVERB_APP_KEY),
            online: typeof navigator !== 'undefined' ? navigator.onLine : true,
            visibility: typeof document !== 'undefined' ? document.visibilityState : '',
        },
    };

    try {
        const res = await window.axios.post(route('chat.calls.diagnostics.client'), payload, {
            headers: { Accept: 'application/json' },
        });
        logStep('diag.flushed', { server_trace: res?.data?.diag_trace });
        return res?.data?.diag_trace || activeTrace.value;
    } catch (flushErr) {
        logStep('diag.flush_failed', { message: flushErr?.message });
        return activeTrace.value;
    }
}

export async function copyTraceToClipboard() {
    const text = formatTraceForDisplay();
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return true;
    }
    return false;
}

export function buildErrorMessage(err, fallback = 'حدث خطأ.') {
    const data = err?.response?.data;
    const parts = [
        data?.message || err?.message || fallback,
        data?.diag_trace ? `رقم التشخيص: ${data.diag_trace}` : null,
        data?.diag_debug?.detail ? `تفاصيل: ${data.diag_debug.detail}` : null,
        data?.diag_debug?.file ? `ملف: ${data.diag_debug.file}` : null,
    ].filter(Boolean);

    return parts.join('\n');
}

export function useEmployeeCallDiagnostics() {
    return {
        activeTrace,
        steps,
        lastServerStatus,
        startTrace,
        logStep,
        formatTraceForDisplay,
        fetchServerStatus,
        flushToServer,
        copyTraceToClipboard,
        buildErrorMessage,
    };
}
