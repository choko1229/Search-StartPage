import {t, node} from './i18n.js';
import {setting, setSetting} from './store.js';
import {providers, selected, remember} from './providers.js';
import {prefixQuery, queryUrl, recommendAi, safeUrl} from './search-core.js';
import {history, record, removeHistory, clearHistory} from './history.js';
import {suggestions, cancelSuggestions} from './suggest.js';
import {initializeSettings} from './search-settings.js';
import './favorites.js';
import {favorites, openFavorite} from './favorites-store.js';
const input = document.getElementById('query');
const select = document.getElementById('provider');
const list = document.getElementById('suggestions');
const status = document.getElementById('search-status');
const initialMode = setting('initialMode', 'web');
let mode = initialMode === 'last' ? setting('lastMode', 'web') : initialMode;
if (!['web', 'ai'].includes(mode)) mode = 'web';
let rows = [], index = -1, timer;
const active = {web: selected('web')?.id, ai: selected('ai')?.id};
function refresh() {
    for (const target of ['web', 'ai']) document.getElementById(`mode-${target}`).setAttribute('aria-pressed', String(mode === target));
    select.replaceChildren(...providers(mode).map(item => node('option', item.name, {value: item.id})));
    select.value = providers(mode).some(item => item.id === active[mode]) ? active[mode] : selected(mode)?.id;
    active[mode] = select.value;
    const shortcuts = document.getElementById('provider-shortcuts'); shortcuts.replaceChildren();
    for (const provider of providers(mode).slice(0, 6)) {
        const button = node('button', `${provider.icon || '↗'} ${provider.name}`, {type: 'button', class: 'secondary'});
        button.addEventListener('click', () => {active[mode] = provider.id; remember(mode, provider.id); refresh(); input.focus(); updateSuggestions();});
        shortcuts.append(button);
    }
}
function changeMode(next) {mode = next; setSetting('lastMode', mode); refresh(); updateSuggestions(); input.focus();}
for (const target of ['web', 'ai']) document.getElementById(`mode-${target}`).addEventListener('click', () => changeMode(target));
select.addEventListener('change', () => {active[mode] = select.value; remember(mode, select.value); updateSuggestions();});
function current(target) { return providers(target).find(item => item.id === active[target]) ?? selected(target); }
function highlight(next) {
    index = next;
    [...list.children].forEach((item, position) => item.setAttribute('aria-selected', String(position === index)));
    if (index >= 0) {input.setAttribute('aria-activedescendant', `suggestion-${index}`); list.children[index]?.scrollIntoView({block: 'nearest'});}
    else input.removeAttribute('aria-activedescendant');
}
function renderSuggestions(next) {
    const previous = index >= 0 ? JSON.stringify(rows[index]) : null;
    rows = next; index = -1; input.removeAttribute('aria-activedescendant');
    list.replaceChildren(...rows.map((item, position) => {
        const li = node('li', item.label, {role: 'option', id: `suggestion-${position}`, 'aria-selected': 'false'});
        li.append(node('small', item.category));
        li.addEventListener('mousedown', event => event.preventDefault());
        li.addEventListener('click', () => executeItem(item)); return li;
    }));
    list.hidden = rows.length === 0; input.setAttribute('aria-expanded', String(!list.hidden));
    if (previous) highlight(rows.findIndex(item => JSON.stringify(item) === previous));
}
function updateSuggestions() {
    clearTimeout(timer);
    const recommended = input.value.trim() !== '' && recommendAi(input.value);
    document.getElementById('ai-hint').hidden = !recommended;
    document.querySelector('.search-box').classList.toggle('ai-recommended', recommended);
    suggestions(input.value, mode, current(mode), renderSuggestions);
}
input.addEventListener('input', () => {clearTimeout(timer); cancelSuggestions(); renderSuggestions([]); timer = setTimeout(updateSuggestions, 180);});
function navigate(url) {if (safeUrl(url)) window.location.assign(url);}
let pendingAi;
function execute(query, target, id, usePrefix = true) {
    query = query.trim(); if (!query) return;
    const prefixed = usePrefix ? prefixQuery(query, {web: providers('web'), ai: providers('ai')}) : null;
    if (prefixed) {target = prefixed.mode; id = prefixed.provider.id; query = prefixed.query;}
    const provider = providers(target).find(item => item.id === id) ?? current(target);
    if (!provider) return;
    const url = queryUrl(provider, query); if (!url) return;
    const go = () => {record(query, provider.id, target); remember(target, provider.id); setSetting('lastMode', target); navigate(url);};
    if (provider.copy) {
        document.getElementById('copy-query').value = query;
        pendingAi = go; document.getElementById('ai-copy-dialog').showModal();
    } else go();
}
function executeItem(item) {
    if(item.favoriteId){const favorite=favorites().find(value=>value.id===item.favoriteId);if(favorite)openFavorite(favorite);}
    else if (item.url) navigate(item.url);
    else execute(item.query, item.mode, item.providerId, false);
}
document.getElementById('search-execute').addEventListener('click', () => execute(input.value, mode, active[mode]));
document.getElementById('open-ai-button').addEventListener('click', () => pendingAi?.());
document.getElementById('copy-query-button').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(document.getElementById('copy-query').value); document.getElementById('copy-query-button').textContent = t('copied'); }
    catch {document.getElementById('copy-query').select();}
});
input.addEventListener('keydown', event => {
    if (event.isComposing) return;
    if (event.key === 'Enter') {
        event.preventDefault();
        if (event.shiftKey) execute(input.value, 'web', active.web);
        else if (event.altKey) execute(input.value, 'ai', active.ai);
        else if (index >= 0) executeItem(rows[index]);
    } else if (['ArrowDown', 'ArrowUp'].includes(event.key) && rows.length) {
        event.preventDefault(); highlight((index + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length);
    } else if (event.key === 'Escape') {clearTimeout(timer); cancelSuggestions(); renderSuggestions([]);}
});
document.addEventListener('click', event => {if (!event.target.closest('.search-home')) {clearTimeout(timer); cancelSuggestions(); renderSuggestions([]);}});
for (const dialog of document.querySelectorAll('dialog')) {
    dialog.querySelector('[data-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {if (event.target === dialog) {const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();}});
}
function renderHistory() {
    const container = document.getElementById('history-list'); container.replaceChildren();
    if (!history().length) container.append(node('p', t('history_empty')));
    for (const item of history()) {
        const row = node('div', undefined, {class: 'history-row'});
        const open = node('button', item.query, {type: 'button', class: 'history-query'});
        open.addEventListener('click', () => execute(item.query, item.mode, item.provider, false));
        const remove = node('button', t('delete'), {type: 'button', 'aria-label': `${t('delete')} ${item.query}`});
        remove.addEventListener('click', () => {removeHistory(item.id); renderHistory();});
        row.append(open, node('small', new Date(item.at).toLocaleDateString()), remove); container.append(row);
    }
}
document.getElementById('history-open').addEventListener('click', () => {renderHistory(); document.getElementById('history-dialog').showModal();});
document.getElementById('history-clear').addEventListener('click', () => {if (confirm(t('confirm_clear_history'))) {clearHistory(); renderHistory();}});
window.addEventListener('storage-unavailable', () => {status.textContent = t('storage_unavailable');});
initializeSettings(refresh);
refresh();
