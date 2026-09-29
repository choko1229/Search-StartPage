import {safeUrl} from './search-core.js';
export function normalizeFavorite(value, previous = {}) {
    const name = String(value.name || '').trim();
    const url = safeUrl(String(value.url || '').trim());
    if (!name || name.length > 100 || !url) throw new Error('invalid_favorite');
    const tags = [...new Set(String(value.tags || '').split(',').map(tag => tag.trim()).filter(Boolean))];
    if (tags.length > 20 || tags.some(tag => tag.length > 40)) throw new Error('invalid_tags');
    const shortcut = String(value.shortcut || '').trim();
    if (shortcut && !/^(?:(?:Alt|Control|Shift)\+){1,3}[a-z0-9]$/i.test(shortcut)) throw new Error('invalid_shortcut');
    return {...previous, id: previous.id || crypto.randomUUID(), name, url,
        folderId: value.folderId || null, tags, icon: String(value.icon || '').slice(0, 2048),
        color: /^#[0-9a-f]{6}$/i.test(value.color) ? value.color : '#304fc3',
        description: String(value.description || '').slice(0, 2000), shortcut,
        pinned: Boolean(value.pinned), visible: Boolean(value.visible),
        sortOrder: previous.sortOrder ?? Date.now(), usageCount: previous.usageCount || 0,
        lastAccess: previous.lastAccess || null, createdAt: previous.createdAt || Date.now(), updatedAt: Date.now()};
}
function matchScore(text, query) {
    text = text.toLocaleLowerCase();
    if (text === query) return 120;
    if (text.startsWith(query)) return 90;
    if (text.includes(query)) return 65;
    let at = 0;
    for (const char of text) if (char === query[at]) at++;
    return at === query.length ? Math.max(5, 30 - (text.length - query.length) / 4) : 0;
}
export function rankFavorites(items, query, folders = [], now = Date.now()) {
    const terms = query.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    return items.map(item => {
        const fields = [item.name, item.url, item.description || '', (item.tags || []).join(' '), folders.find(folder => folder.id === item.folderId)?.name || ''];
        const scores = terms.map(term => Math.max(...fields.map(field => matchScore(field, term))));
        const match = scores.every(score => score > 0);
        const relevance = scores.reduce((sum, score) => sum + score, 0);
        const recency = item.lastAccess ? Math.max(0, 5 - (now - item.lastAccess) / 86400000) : 0;
        return {item, score: match ? relevance * 10 + Math.log2(1 + (item.usageCount || 0)) + recency : -1};
    }).filter(result => result.score >= 0).sort((a, b) => b.score - a.score).map(result => result.item);
}
export function shortcutKey(value) {
    const parts = value.toLowerCase().split('+');
    const key = parts.pop();
    return [...new Set(parts)].sort().join('+') + '+' + key;
}
export function favoriteShortcuts(items) {
    const rows = sortFavorites(items.filter(item => item.visible !== false), 'manual');
    const bindings = new Map();
    for (const item of rows) if (item.shortcut) bindings.set(shortcutKey(item.shortcut), item);
    rows.slice(0, 9).forEach((item, index) => {
        const key = shortcutKey(`Alt+${index + 1}`);
        if (!item.shortcut && !bindings.has(key)) bindings.set(key, item);
    });
    return bindings;
}
export function sortFavorites(items, order) {
    return [...items].sort((a, b) => Number(b.pinned) - Number(a.pinned) || (
        order === 'usage' ? (b.usageCount || 0) - (a.usageCount || 0) :
        order === 'recent' ? (b.lastAccess || 0) - (a.lastAccess || 0) :
        order === 'name' ? a.name.localeCompare(b.name) : (a.sortOrder || 0) - (b.sortOrder || 0)
    ));
}
