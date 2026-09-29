import {history} from './history.js';
import {get, setting} from './store.js';
import {providers} from './providers.js';
import {detectUrl, recommendAi, safeUrl} from './search-core.js';
import {t} from './i18n.js';
import {favorites, folders} from './favorites-store.js';
import {rankFavorites} from './favorites-core.js';
let controller;
let sequence = 0;
export function cancelSuggestions() { sequence++; controller?.abort(); }
export async function suggestions(query, mode, current, render) {
    const generation = ++sequence;
    controller?.abort();
    const text = query.trim();
    if (!text) { render([]); return; }
    const results = [];
    if (recommendAi(text)) results.push({label: t('ai_recommend'), category: t('ai_mode'), query: text, mode: 'ai'});
    const url = detectUrl(text);
    if (url) results.push({label: `${t('open_url')} · ${url}`, category: t('url_category'), url});
    const lower = text.toLocaleLowerCase();
    for (const item of (setting('favoriteSearch','both') === 'dedicated' ? [] : rankFavorites(favorites().filter(item=>item.visible!==false),text,folders()).slice(0,4))) {
        if (safeUrl(item.url)) results.push({label: item.name, category: t('favorites'), url: item.url, favoriteId:item.id});
    }
    for (const item of history().filter(row => row.query.toLocaleLowerCase().includes(lower)).slice(0, 5)) {
        results.push({label: item.query, category: t('history'), query: item.query, mode: item.mode, providerId: item.provider});
    }
    for (const target of ['web', 'ai']) {
        for (const provider of providers(target).filter(item => item.name.toLocaleLowerCase().includes(lower) || item.prefix === lower).slice(0, 3)) {
            results.push({label: provider.name, category: t(target === 'web' ? 'web_mode' : 'ai_mode'), query: text, mode: target, providerId: provider.id});
        }
    }
    results.push({label: `${current?.name ?? t(mode + '_mode')} · ${text}`, category: t('search'), query: text, mode, providerId: current?.id});
    render(results);
    if (!setting('externalSuggest', true) || text.length > 200) return;
    controller = new AbortController();
    try {
        const response = await fetch(`/api/search/suggest?q=${encodeURIComponent(text)}`, {signal: controller.signal});
        if (!response.ok) return;
        const payload = await response.json();
        if (generation !== sequence || !Array.isArray(payload.data?.suggestions)) return;
        const existing = new Set(results.map(item => item.query));
        render([...results, ...payload.data.suggestions.filter(item => typeof item === 'string' && !existing.has(item)).map(item => ({label: item, category: t('external_suggest'), query: item, mode}))]);
    } catch { /* Local suggestions remain usable offline or during provider failures. */ }
}
