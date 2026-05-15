import { employeeCallRealtimeEnabled, userPrivateChannel } from '@/echo.js';
import { computed, ref } from 'vue';

const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
];

const phase = ref('idle');
const call = ref(null);
const peer = ref(null);
const error = ref('');
const isMuted = ref(false);
const isVideoOff = ref(false);
const localStream = ref(null);
const remoteStream = ref(null);
const callDuration = ref(0);

let pc = null;
let echoChannel = null;
let durationTimer = null;
let pendingOffer = null;
let pendingOfferType = null;
let currentUserId = null;
let subscribed = false;

const isActive = computed(() => ['outgoing', 'incoming', 'connecting', 'active'].includes(phase.value));
const isIncoming = computed(() => phase.value === 'incoming');
const isOutgoing = computed(() => phase.value === 'outgoing' || (phase.value === 'connecting' && Boolean(call.value?.is_caller)));
const isVideoCall = computed(() => call.value?.type === 'video');

/** الشخص المعروض في الواجهة: المتصل عند الواردة، الطرف الآخر عند الصادرة/النشطة */
const contactPerson = computed(() => {
    if (!call.value) {
        return peer.value;
    }
    if (phase.value === 'incoming') {
        return call.value.caller || peer.value;
    }
    if (call.value.is_caller) {
        return call.value.callee || peer.value;
    }
    return call.value.caller || peer.value;
});

function api() {
    return window.axios;
}

function resetStreams() {
    if (localStream.value) {
        localStream.value.getTracks().forEach((t) => t.stop());
    }
    localStream.value = null;
    remoteStream.value = null;
}

function closePeerConnection() {
    if (pc) {
        pc.ontrack = null;
        pc.onicecandidate = null;
        pc.close();
        pc = null;
    }
}

function stopDurationTimer() {
    if (durationTimer) {
        clearInterval(durationTimer);
        durationTimer = null;
    }
    callDuration.value = 0;
}

function startDurationTimer() {
    stopDurationTimer();
    const started = Date.now();
    durationTimer = setInterval(() => {
        callDuration.value = Math.floor((Date.now() - started) / 1000);
    }, 1000);
}

function formatDuration(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

async function postSignal(callId, signalType, extra = {}) {
    await api().post(
        route('chat.calls.signal', callId),
        { signal_type: signalType, ...extra },
        { headers: { Accept: 'application/json' } },
    );
}

async function ensureLocalStream(video) {
    const wantVideo = Boolean(video);
    if (localStream.value) {
        const hasVideo = localStream.value.getVideoTracks().length > 0;
        if (wantVideo && !hasVideo) {
            const extra = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            extra.getVideoTracks().forEach((t) => localStream.value.addTrack(t));
        }
        if (!wantVideo) {
            localStream.value.getVideoTracks().forEach((t) => {
                t.stop();
                localStream.value.removeTrack(t);
            });
        }
        isVideoOff.value = !wantVideo;
        return localStream.value;
    }
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: wantVideo,
    });
    localStream.value = stream;
    isVideoOff.value = !wantVideo;
    return stream;
}

function syncLocalTracksToPeerConnection() {
    if (!pc || !localStream.value) {
        return;
    }
    const senders = pc.getSenders();
    localStream.value.getTracks().forEach((track) => {
        const existing = senders.find((s) => s.track?.kind === track.kind);
        if (existing) {
            existing.replaceTrack(track);
        } else {
            pc.addTrack(track, localStream.value);
        }
    });
    senders.forEach((sender) => {
        if (!sender.track) {
            return;
        }
        const stillThere = localStream.value.getTracks().some((t) => t.id === sender.track.id);
        if (!stillThere) {
            pc.removeTrack(sender);
        }
    });
}

function createPeerConnection(callId) {
    closePeerConnection();
    pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });

    pc.ontrack = (event) => {
        const [stream] = event.streams;
        if (stream) {
            remoteStream.value = stream;
        }
    };

    pc.onicecandidate = (event) => {
        if (!event.candidate || !callId) {
            return;
        }
        postSignal(callId, 'ice', {
            candidate: event.candidate.toJSON(),
        }).catch(() => {});
    };

    syncLocalTracksToPeerConnection();

    return pc;
}

async function handleRemoteOffer(callId, sdp, offerType = null) {
    const wasActive = phase.value === 'active';
    const useVideo = offerType === 'video' || (offerType === null && isVideoCall.value);

    if (!pc) {
        await ensureLocalStream(useVideo);
        createPeerConnection(callId);
    } else if (offerType) {
        await ensureLocalStream(offerType === 'video');
        syncLocalTracksToPeerConnection();
        if (call.value) {
            call.value = { ...call.value, type: offerType };
        }
    }

    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    await postSignal(callId, 'answer', { sdp: answer.toJSON() });

    if (!wasActive) {
        phase.value = 'active';
        startDurationTimer();
    }
}

async function handleRemoteAnswer(sdp) {
    if (!pc) {
        return;
    }
    await pc.setRemoteDescription(new RTCSessionDescription(sdp));
    phase.value = 'active';
    startDurationTimer();
}

async function handleRemoteIce(candidate) {
    if (!pc || !candidate) {
        return;
    }
    try {
        await pc.addIceCandidate(new RTCIceCandidate(candidate));
    } catch {
        /* ignore */
    }
}

function settleIdle(message = '') {
    phase.value = 'idle';
    call.value = null;
    peer.value = null;
    pendingOffer = null;
    pendingOfferType = null;
    error.value = message;
    stopDurationTimer();
    closePeerConnection();
    resetStreams();
    isMuted.value = false;

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('employee-call-settled'));
    }
}

async function endCallRemote() {
    const callId = call.value?.id;
    if (callId) {
        try {
            await api().post(route('chat.calls.end', callId), {}, { headers: { Accept: 'application/json' } });
        } catch {
            /* ignore */
        }
    }
    settleIdle();
}

function cancelOutgoing() {
    endCallRemote();
}

async function renegotiateWithMode(targetType) {
    const callId = call.value?.id;
    if (!callId || phase.value !== 'active' || !pc) {
        return;
    }

    await ensureLocalStream(targetType === 'video');
    syncLocalTracksToPeerConnection();
    call.value = { ...call.value, type: targetType };

    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await postSignal(callId, 'offer', { sdp: offer.toJSON() });

    try {
        await api().patch(
            route('chat.calls.mode', callId),
            { type: targetType },
            { headers: { Accept: 'application/json' } },
        );
    } catch {
        /* الوضع محلياً محدّث */
    }
}

async function switchToVideo() {
    if (!isVideoCall.value) {
        await renegotiateWithMode('video');
    }
}

async function switchToVoice() {
    if (isVideoCall.value) {
        await renegotiateWithMode('voice');
    }
}

async function startCall(calleeId, type = 'voice') {
    if (!employeeCallRealtimeEnabled()) {
        error.value = 'المكالمات تتطلب تفعيل البث المباشر (Reverb).';
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'المتصفح لا يدعم المكالمات.';
        return;
    }
    if (isActive.value) {
        return;
    }

    error.value = '';
    phase.value = 'outgoing';

    try {
        const res = await api().post(
            route('chat.calls.store'),
            { callee_id: calleeId, type },
            { headers: { Accept: 'application/json' } },
        );
        call.value = res.data.call;
        peer.value = res.data.call?.callee || res.data.call?.peer;
        await ensureLocalStream(type === 'video');
        createPeerConnection(call.value.id);
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await postSignal(call.value.id, 'offer', { sdp: offer.toJSON() });
        phase.value = 'connecting';
    } catch (err) {
        const msg = err?.response?.data?.message || err?.response?.data?.errors?.callee_id?.[0];
        settleIdle(msg || 'تعذّر بدء المكالمة.');
    }
}

async function acceptCall() {
    if (!call.value?.id || phase.value !== 'incoming') {
        return;
    }
    error.value = '';
    phase.value = 'connecting';
    try {
        const res = await api().post(route('chat.calls.accept', call.value.id), {}, { headers: { Accept: 'application/json' } });
        call.value = res.data.call || call.value;
        peer.value = call.value.callee || call.value.peer;
        const type = call.value?.type || 'voice';
        await ensureLocalStream(type === 'video');
        if (pendingOffer) {
            await handleRemoteOffer(call.value.id, pendingOffer, pendingOfferType || type);
            pendingOffer = null;
            pendingOfferType = null;
        }
    } catch {
        settleIdle('تعذّر قبول المكالمة.');
    }
}

async function rejectCall() {
    const callId = call.value?.id;
    if (callId) {
        try {
            await api().post(route('chat.calls.reject', callId), {}, { headers: { Accept: 'application/json' } });
        } catch {
            /* ignore */
        }
    }
    settleIdle();
}

function toggleMute() {
    if (!localStream.value) {
        return;
    }
    isMuted.value = !isMuted.value;
    localStream.value.getAudioTracks().forEach((t) => {
        t.enabled = !isMuted.value;
    });
}

function toggleVideo() {
    if (!localStream.value || !isVideoCall.value) {
        return;
    }
    isVideoOff.value = !isVideoOff.value;
    localStream.value.getVideoTracks().forEach((t) => {
        t.enabled = !isVideoOff.value;
    });
}

function onSignalingEvent(eventName, payload) {
    const callId = Number(payload?.call_id);
    if (!callId) {
        return;
    }

    if (eventName === 'employee-call.incoming') {
        if (isActive.value) {
            return;
        }
        call.value = payload.call;
        peer.value = payload.call?.caller;
        pendingOffer = null;
        pendingOfferType = null;
        phase.value = 'incoming';
        return;
    }

    if (!call.value || Number(call.value.id) !== callId) {
        return;
    }

    if (eventName === 'employee-call.mode-changed' && payload.type) {
        call.value = { ...call.value, type: payload.type };
        return;
    }

    if (eventName === 'employee-call.offer' && payload.sdp) {
        const offerType = payload.type || call.value?.type;
        if (phase.value === 'incoming') {
            pendingOffer = payload.sdp;
            pendingOfferType = offerType;
            return;
        }
        if (phase.value === 'connecting' && call.value && !call.value.is_caller) {
            handleRemoteOffer(callId, payload.sdp, offerType).catch(() => settleIdle('فشل الاتصال.'));
            return;
        }
        if (phase.value === 'active') {
            handleRemoteOffer(callId, payload.sdp, offerType).catch(() => settleIdle('فشل الاتصال.'));
            return;
        }
        if (phase.value === 'outgoing') {
            return;
        }
        handleRemoteOffer(callId, payload.sdp, offerType).catch(() => settleIdle('فشل الاتصال.'));
        return;
    }

    if (eventName === 'employee-call.answer' && payload.sdp) {
        handleRemoteAnswer(payload.sdp).catch(() => settleIdle('فشل الاتصال.'));
        return;
    }

    if (eventName === 'employee-call.ice' && payload.candidate) {
        handleRemoteIce(payload.candidate);
        return;
    }

    if (['employee-call.accepted', 'employee-call.rejected', 'employee-call.ended'].includes(eventName)) {
        if (eventName === 'employee-call.accepted' && (phase.value === 'outgoing' || phase.value === 'connecting')) {
            phase.value = 'connecting';
            return;
        }
        const messages = {
            'employee-call.rejected': 'تم رفض المكالمة.',
            'employee-call.ended': 'انتهت المكالمة.',
        };
        settleIdle(messages[eventName] || '');
    }
}

function subscribeToUserChannel(userId) {
    if (!employeeCallRealtimeEnabled() || !userId || subscribed) {
        return;
    }
    currentUserId = userId;
    subscribed = true;
    echoChannel = window.Echo.private(userPrivateChannel(userId));

    const events = ['incoming', 'accepted', 'offer', 'answer', 'ice', 'rejected', 'ended', 'mode-changed'];
    events.forEach((action) => {
        echoChannel.listen(`.employee-call.${action}`, (payload) => {
            onSignalingEvent(`employee-call.${action}`, payload);
        });
    });
}

function unsubscribeFromUserChannel() {
    if (echoChannel && currentUserId) {
        window.Echo.leave(userPrivateChannel(currentUserId));
        echoChannel = null;
    }
    subscribed = false;
    currentUserId = null;
}

function initEmployeeCalls(userId) {
    subscribeToUserChannel(userId);
}

function teardownEmployeeCalls() {
    unsubscribeFromUserChannel();
    if (isActive.value) {
        endCallRemote();
    } else {
        settleIdle();
    }
}

export function useEmployeeCall() {
    return {
        phase,
        call,
        peer,
        contactPerson,
        error,
        isMuted,
        isVideoOff,
        localStream,
        remoteStream,
        callDuration,
        isActive,
        isIncoming,
        isOutgoing,
        isVideoCall,
        formatDuration,
        initEmployeeCalls,
        teardownEmployeeCalls,
        startCall,
        acceptCall,
        rejectCall,
        endCall: endCallRemote,
        cancelOutgoing,
        toggleMute,
        toggleVideo,
        switchToVideo,
        switchToVoice,
        settleIdle,
    };
}
