/** @typedef {{ id: number, name: string, username?: string }} MentionUser */

const MENTION_TOKEN_REGEX = /@\[(\d+)\]|@([a-zA-Z0-9][a-zA-Z0-9._-]{1,31})/g;

/**
 * @param {string} text
 * @param {number} cursorPos
 * @returns {{ query: string, start: number } | null}
 */
export function findActiveMentionQuery(text, cursorPos) {
    const before = String(text || '').slice(0, cursorPos);
    const match = before.match(/(^|[\s\n])@([^\s@]*)$/u);
    if (!match) {
        return null;
    }

    const query = match[2] ?? '';
    const start = cursorPos - query.length - 1;

    return { query, start };
}

/**
 * @param {MentionUser[]} candidates
 * @param {string} query
 * @param {number|null} excludeUserId
 */
export function filterMentionCandidates(candidates, query, excludeUserId = null) {
    const q = String(query || '').trim().toLowerCase();

    const filtered = (candidates || []).filter((user) => {
        if (!user?.id) {
            return false;
        }
        if (excludeUserId && Number(user.id) === Number(excludeUserId)) {
            return false;
        }
        if (!q) {
            return true;
        }
        const username = String(user.username || '').toLowerCase();
        const name = String(user.name || '').toLowerCase();
        return username.includes(q) || name.includes(q);
    });

    return filtered;
}

/**
 * @param {string} body
 * @param {MentionUser[]} mentions
 */
export function splitMessageBody(body, mentions = []) {
    const text = String(body || '');
    if (!text) {
        return [];
    }

    const byUsername = new Map(
        (mentions || [])
            .filter((m) => m?.username)
            .map((m) => [String(m.username).toLowerCase(), m]),
    );
    const byId = new Map((mentions || []).filter((m) => m?.id).map((m) => [Number(m.id), m]));

    const parts = [];
    let last = 0;
    let match;

    const regex = new RegExp(MENTION_TOKEN_REGEX.source, 'g');

    while ((match = regex.exec(text)) !== null) {
        if (match.index > last) {
            parts.push({ type: 'text', text: text.slice(last, match.index) });
        }

        const userId = match[1] ? Number(match[1]) : null;
        const username = match[2] || null;
        const user = userId ? byId.get(userId) : username ? byUsername.get(username.toLowerCase()) : null;

        parts.push({
            type: 'mention',
            text: mentionDisplayLabel(user, match[0]),
            user,
        });
        last = match.index + match[0].length;
    }

    if (last < text.length) {
        parts.push({ type: 'text', text: text.slice(last) });
    }

    return parts;
}

/**
 * @param {MentionUser|null|undefined} user
 * @param {string} rawToken
 */
export function mentionDisplayLabel(user, rawToken) {
    if (user?.name) {
        return `@${user.name}`;
    }

    return rawToken;
}

/**
 * @param {MentionUser} user
 */
export function mentionTokenForUser(user) {
    const username = String(user?.username || '').trim();
    if (username) {
        return `@${username} `;
    }

    return `@[${user.id}] `;
}

/**
 * @param {MentionUser} user
 */
export function mentionHintForUser(user) {
    const username = String(user?.username || '').trim();
    if (username) {
        return `@${username}`;
    }

    return 'منشن بالاسم';
}

/**
 * @param {{ mentions?: Array<{ id?: number }> }} message
 * @param {number|null|undefined} viewerId
 */
export function messageMentionsViewer(message, viewerId) {
    if (!viewerId || !message?.mentions?.length) {
        return false;
    }

    return message.mentions.some((m) => Number(m?.id) === Number(viewerId));
}
