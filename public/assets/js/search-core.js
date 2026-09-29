export function safeUrl(value) {
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null;
    } catch { return null; }
}
export function detectUrl(value) {
    const text = value.trim();
    if (/\s/.test(text)) return null;
    if (/^https?:\/\//i.test(text)) return safeUrl(text);
    if (/^[\w\p{L}-]+(?:\.[\w\p{L}-]+)+(?:[/:?#]|$)/u.test(text)) return safeUrl(`https://${text}`);
    return null;
}
export function recommendAi(query) {
    return [...query.trim()].length >= 25 || /[\n?？]|比較|原因|方法|教えて|なぜ|どう|\b(why|how|compare|explain)\b/i.test(query);
}
export function prefixQuery(input, providers) {
    const match = input.match(/^!?([^\s]+)\s+([\s\S]+)$/);
    if (!match) return null;
    for (const mode of ['web', 'ai']) {
        const provider = providers[mode].find(item => item.enabled !== false && item.prefix.toLowerCase() === match[1].toLowerCase());
        if (provider) return {mode, provider, query: match[2].trim()};
    }
    return null;
}
export function queryUrl(provider, query) {
    return safeUrl(provider.url.replaceAll('{query}', encodeURIComponent(query)));
}
export function validProvider(provider) {
    return provider && typeof provider === 'object' && typeof provider.name === 'string' && provider.name.trim().length > 0 && provider.name.length <= 100
        && typeof provider.prefix === 'string' && /^[\w-]{1,32}$/.test(provider.prefix)
        && typeof provider.url === 'string' && provider.url.length <= 2048
        && Boolean(safeUrl(provider.url.replaceAll('{query}', 'test')))
        && (provider.url.includes('{query}') || provider.copy === true);
}
