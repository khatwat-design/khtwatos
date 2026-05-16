/** @typedef {{ id: number, name: string, username: string }} MentionUser */

/**
 * @param {string} text
 * @param {number} cursorPos
 * @returns {{ query: string, start: number } | null}
 */
export function findActiveMentionQuery(text, cursorPos) {
    const before = String(text || '').slice(0, cursorPos);
    const match = before.match(/(^|[\s\n])@([a-zA-Z0-9._-]*)$/);
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

    return (candidates || [])
        .filter((user) => {
            if (excludeUserId && Number(user.id) === Number(excludeUserId)) {
                return false;
            }
            if (!user?.username) {
                return false;
            }
            if (!q) {
                return true;
            }
            const username = String(user.username).toLowerCase();
            const name = String(user.name || '').toLowerCase();
            return username.includes(q) || name.includes(q);
        })
        .slice(0, 8);
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

    const parts = [];
    const regex = /@([a-zA-Z0-9][a-zA-Z0-9._-]{1,31})/g;
    let last = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
        if (match.index > last) {
            parts.push({ type: 'text', text: text.slice(last, match.index) });
        }

        const username = match[1];
        parts.push({
            type: 'mention',
            text: `@${username}`,
            user: byUsername.get(username.toLowerCase()) || null,
        });
        last = match.index + match[0].length;
    }

    if (last < text.length) {
        parts.push({ type: 'text', text: text.slice(last) });
    }

    return parts;
}

/**
 * @param {MentionUser} user
 */
export function mentionTokenForUser(user) {
    return `@${user.username} `;
}
