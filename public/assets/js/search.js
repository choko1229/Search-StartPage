import {t, node} from './i18n.js';
import {setting, setSetting,flush} from './store.js';
import {providers, selected, remember, recordProvider} from './providers.js';
import {defaultKeys,matchesShortcut,directUrl} from './search-preferences.js';
import {prefixQuery, queryUrl, recommendAi, safeUrl} from './search-core.js';
import {history, record, removeHistory, clearHistory} from './history.js';
import {suggestions, cancelSuggestions} from './suggest.js';
import {initializeSettings} from './search-settings.js';
import {initializeSettingsModal} from './settings-modal.js';
import {initializeAppearance} from './appearance.js';
import {initializeOnboarding} from './onboarding.js';
import {initializeBackground} from './background.js';
import {initializeCommandPalette} from './command-palette.js';
import './favorites.js';
import './sync.js';
import {favorites, openFavorite} from './favorites-store.js';
import {initializeStatistics,recordStatistic} from './statistics.js';
import {initializeOfflineMode} from './offline-mode.js';
initializeOfflineMode();
initializeStatistics();
const input = document.getElementById('query');
const select = document.getElementById('provider');
const list = document.getElementById('suggestions');
const status = document.getElementById('search-status');
const suggestStatus=node('p','',{role:'status','aria-live':'polite'});status.before(suggestStatus);
const initialMode = setting('initialMode', 'web');
let mode = initialMode === 'last' ? setting('lastMode', 'web') : initialMode;
if (!['web', 'ai'].includes(mode)) mode = 'web';
let rows = [], index = -1, timer;
const active = {web: selected('web')?.id, ai: selected('ai')?.id};
function refresh() {
    renderHistoryArea();
    document.getElementById('search-help').textContent=`${setting('webKey',defaultKeys.webKey)}: Web · ${setting('aiKey',defaultKeys.aiKey)}: AI · ${setting('historyKey',defaultKeys.historyKey)}: ${t('history')}`;
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
    suggestions(input.value, mode, current(mode), renderSuggestions,key=>{suggestStatus.textContent=key?t(key):'';});
}
input.addEventListener('input', () => {clearTimeout(timer); cancelSuggestions(); suggestStatus.textContent='';renderSuggestions([]); timer = setTimeout(updateSuggestions, 180);});
input.addEventListener('focus',()=>{if(setting('suggestOnFocus',false))updateSuggestions();});
async function navigate(url) {
    if(!safeUrl(url))return;
    try {await flush();}catch {status.textContent=t('storage_unavailable');}
    window.location.assign(url);
}
let pendingAi;
function execute(query, target, id, usePrefix = true) {
    query = query.trim(); if (!query) return;
    const prefixed = usePrefix ? prefixQuery(query, {web: providers('web'), ai: providers('ai')}) : null;
    if (prefixed) {target = prefixed.mode; id = prefixed.provider.id; query = prefixed.query;}
    if(!prefixed && target==='web') {const url=directUrl(query,setting('urlPolicy','suggest'));if(url){navigate(url);return;}}
    const provider = providers(target).find(item => item.id === id) ?? current(target);
    if (!provider) return;
    const url = queryUrl(provider, query); if (!url) return;
    const go = async () => {await record(query, provider.id, target); recordProvider(provider.id); remember(target, provider.id); setSetting('lastMode', target);await recordStatistic(target==='ai'?'ai_search':'search',{provider:provider.id});navigate(url);};
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
    if(matchesShortcut(event,setting('webKey',defaultKeys.webKey))) {
        event.preventDefault();execute(input.value,'web',active.web);
    } else if(matchesShortcut(event,setting('aiKey',defaultKeys.aiKey))) {
        event.preventDefault();execute(input.value,'ai',selected('ai')?.id);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (!event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey && index >= 0) executeItem(rows[index]);
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
        remove.addEventListener('click', async () => {try{await removeHistory(item.id);renderHistory();}catch{status.textContent=t('storage_unavailable');}});
        row.append(open, node('small', new Date(item.at).toLocaleDateString()), remove); container.append(row);
    }
}
function openHistory() {renderHistory(); document.getElementById('history-dialog').showModal();}
document.getElementById('history-open').addEventListener('click', openHistory);
document.querySelector('a[href="/#history"]')?.addEventListener('click', event => {
    event.preventDefault(); openHistory();
});
// Other pages use the same header link to reach the history on the home page.
function openLinkedHistory() {if (location.hash === '#history') openHistory();}
window.addEventListener('hashchange', openLinkedHistory);
document.addEventListener('keydown',event=>{
    if(matchesShortcut(event,setting('historyKey',defaultKeys.historyKey)) && !document.querySelector('dialog[open]')) {
        event.preventDefault();openHistory();
    }
});
function renderHistoryArea() {
    const area=document.getElementById('history-area');
    if(!area)return;
    area.hidden=!setting('historyArea',false);
    area.replaceChildren(node('h2',t('history')));
    for(const item of history().slice(0,10)) {
        const button=node('button',item.query,{type:'button',class:'secondary'});
        button.addEventListener('click',()=>execute(item.query,item.mode,item.provider,false));area.append(button);
    }
}
window.addEventListener('data-change',event=>{if(event.detail==='history')renderHistoryArea();});
document.getElementById('history-clear').addEventListener('click', async () => {if (confirm(t('confirm_clear_history'))) {try{await clearHistory();renderHistory();}catch{status.textContent=t('storage_unavailable');}}});
window.addEventListener('storage-unavailable', () => {status.textContent = t('storage_unavailable');});
initializeSettings(refresh);
const settingsModal=initializeSettingsModal();
initializeAppearance();
initializeBackground();
initializeCommandPalette({openSettings:category=>settingsModal.open(category),openHistory,search:(query,target,id)=>execute(query,target,id,false),chooseProvider:(target,id)=>{active[target]=id;changeMode(target);}});
window.addEventListener('data-change',event=>{if(event.detail==='settings')refresh();});
refresh();
openLinkedHistory();
void initializeOnboarding();
