const VOICE_NAME_PREFIX = /^voice-/i;
const VOICE_EXT = /\.(webm|ogg|oga|m4a|mp3|wav|opus)$/i;

export function isVoiceMime(mime) {
    const m = String(mime || '').toLowerCase();
    if (m.startsWith('audio/')) {
        return true;
    }
    return m === 'video/webm' || m === 'application/ogg';
}

export function isVoiceFileName(name) {
    const n = String(name || '').toLowerCase();
    return VOICE_NAME_PREFIX.test(n) || VOICE_EXT.test(n);
}

export function isVoiceFile(file) {
    if (!file) {
        return false;
    }
    return isVoiceMime(file.type) || isVoiceFileName(file.name);
}

/** @param {{ mime?: string, name?: string, is_audio?: boolean } | null | undefined} att */
export function attachmentIsVoice(att) {
    if (!att) {
        return false;
    }
    if (att.is_audio) {
        return true;
    }
    return isVoiceMime(att.mime) || isVoiceFileName(att.name);
}

export function buildOptimisticAttachment(file) {
    const voice = isVoiceFile(file);
    const previewable = voice || String(file.type || '').startsWith('image/');

    return {
        name: voice ? 'رسالة صوتية' : file.name,
        mime: voice ? normalizeVoiceMimeForDisplay(file.type, file.name) : file.type || 'ملف',
        size: file.size || 0,
        is_image: String(file.type || '').startsWith('image/'),
        is_audio: voice,
        url: previewable ? URL.createObjectURL(file) : null,
    };
}

export function normalizeVoiceMimeForDisplay(mime, name) {
    if (isVoiceMime(mime)) {
        return mime.startsWith('audio/') ? mime : 'audio/webm';
    }
    const n = String(name || '').toLowerCase();
    if (n.endsWith('.ogg')) {
        return 'audio/ogg';
    }
    if (n.endsWith('.m4a') || n.endsWith('.mp4')) {
        return 'audio/mp4';
    }
    if (n.endsWith('.mp3')) {
        return 'audio/mpeg';
    }
    return 'audio/webm';
}
