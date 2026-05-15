export function isAppleLikeBrowser() {
    if (typeof navigator === 'undefined') {
        return false;
    }
    const ua = navigator.userAgent || '';
    const iOS =
        /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const safari = /Safari/i.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS|OPR\//i.test(ua);
    return iOS || safari;
}

export function pickRecorderMimeType() {
    const candidates = isAppleLikeBrowser()
        ? ['audio/mp4', 'audio/aac', 'audio/webm;codecs=opus', 'audio/webm', 'video/mp4']
        : ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', 'audio/mp4'];

    for (const type of candidates) {
        if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) {
            return type;
        }
    }
    return '';
}

export function voiceFileFromBlob(blob, mimeType) {
    const type = String(mimeType || blob.type || 'audio/webm').split(';')[0];
    const ext = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
    const normalizedType = type.includes('mp4')
        ? 'audio/mp4'
        : type.includes('ogg')
          ? 'audio/ogg'
          : type.startsWith('audio/')
            ? type
            : 'audio/webm';

    return new File([blob], `voice-${Date.now()}.${ext}`, { type: normalizedType });
}
