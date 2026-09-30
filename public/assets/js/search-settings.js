import {t, node} from './i18n.js';
import {setting, setSetting} from './store.js';
import {providers, saveProviders} from './providers.js';
import {validProvider} from './search-core.js';
import {defaultKeys,validShortcut,keySignature,boundedInteger} from './search-preferences.js';
import {history} from './history.js';
import {set} from './store.js';
export function initializeSettings(refresh) {
    const dialog = document.getElementById('search-settings');
    const form = document.getElementById('provider-form');
    const kind = document.getElementById('provider-kind');
    const error = document.getElementById('provider-error');
    function preference(key, title, options, fallback) {
        const label = node('label', title, {class: 'preference'});
        const select = node('select', undefined, {'aria-label': title});
        for (const [value, name] of options) select.append(node('option', name, {value}));
        select.value = setting(key, fallback);
        select.addEventListener('change', () => {setSetting(key, select.value); refresh();});
        label.append(select); return label;
    }
    function render() {
        const preferences = document.getElementById('search-preferences');
        preferences.replaceChildren(preference('initialMode', t('initial_mode'), [['web', t('web_mode')], ['ai', t('ai_mode')], ['last', t('last_mode')]], 'web'));
        for (const mode of ['web', 'ai']) preferences.append(preference(`${mode}Default`, t(`${mode}_default`), [...providers(mode).map(item => [item.id, item.name]), ['last', t('last_mode')]], mode === 'web' ? 'google' : 'chatgpt'));
        preferences.append(preference('aiOrder',t('ai_order'),[['fixed',t('manual')],['usage',t('usage')],['recent',t('recent')]],'fixed'));
        preferences.append(preference('urlPolicy',t('url_policy'),[['suggest',t('url_suggest_only')],['auto',t('url_auto')],['full',t('url_full')]],'suggest'));
        for (const [key,title,fallback,max] of [['historyLimit','history_limit',300,10000],['historyDays','history_days',90,3650]]) {
            const label=node('label',t(title),{class:'preference'});
            const number=node('input',undefined,{type:'number',min:'1',max:String(max),value:String(setting(key,fallback))});
            number.addEventListener('input',()=>{
                const value=Number(number.value);
                if(number.value && Number.isInteger(value) && value>=1 && value<=max) {setSetting(key,value);refresh();}
            });
            number.addEventListener('change',()=>{const value=boundedInteger(number.value,fallback,1,max);number.value=String(value);setSetting(key,value);set('history',history());refresh();});
            label.append(number);preferences.append(label);
        }
        for(const [key,fallback] of Object.entries(defaultKeys)) {
            const label=node('label',t(key),{class:'preference'});
            const input=node('input',undefined,{value:setting(key,fallback),maxlength:'60'});
            input.addEventListener('input',()=>{
                const value=input.value.trim();
                if(validShortcut(value) && !Object.entries(defaultKeys).some(([other,defaultValue])=>other!==key && keySignature(setting(other,defaultValue))===keySignature(value))) {
                    setSetting(key,value);error.textContent='';refresh();
                }
            });
            input.addEventListener('change',()=>{
                const value=input.value.trim();
                if(!validShortcut(value) || Object.entries(defaultKeys).some(([other,defaultValue])=>other!==key && keySignature(setting(other,defaultValue))===keySignature(value))) {
                    error.textContent=t('shortcut_conflict'); input.value=setting(key,fallback);return;
                }
                error.textContent='';setSetting(key,value);refresh();
            });
            label.append(input);preferences.append(label);
        }
        for (const [key, title, fallback] of [['saveHistory', 'save_history',true], ['externalSuggest', 'external_suggest_setting',true],['suggestOnFocus','suggest_on_focus',false],['historyArea','history_area',false]]) {
            const label = node('label', t(title), {class: 'preference'});
            const checkbox = node('input', undefined, {type: 'checkbox'});
            checkbox.checked = setting(key, fallback);
            checkbox.addEventListener('change', () => {setSetting(key, checkbox.checked);refresh();});
            label.append(checkbox); preferences.append(label);
        }
        preferences.append(node('p', t('external_suggest_privacy'), {class: 'muted'}));
        const items = providers(kind.value, false);
        const list = document.getElementById('provider-list'); list.replaceChildren();
        items.forEach((item, index) => {
            const row = node('div', undefined, {class: 'provider-row'});
            const enabled = node('input', undefined, {type: 'checkbox', 'aria-label': `${t('enabled')} ${item.name}`});
            enabled.checked = item.enabled !== false;
            enabled.addEventListener('change', () => {
                if (!enabled.checked && items.filter(value => value.enabled !== false).length === 1) {enabled.checked = true; error.textContent = t('provider_required'); return;}
                item.enabled = enabled.checked; saveProviders(kind.value, items); render(); refresh();
            });
            row.append(enabled, node('span', `${item.icon || '↗'} ${item.name} · !${item.prefix}`));
            const edit = node('button', t('edit'), {type: 'button'});
            edit.addEventListener('click', () => {for (const key of ['id', 'name', 'url', 'prefix', 'icon']) form.elements[key].value = item[key] || ''; form.elements.name.focus();});
            const up = node('button', '↑', {type: 'button', 'aria-label': `${t('move_up')} ${item.name}`}); up.disabled = index === 0;
            up.addEventListener('click', () => { [items[index - 1], items[index]] = [items[index], items[index - 1]]; saveProviders(kind.value, items); render(); refresh(); });
            const remove = node('button', t('delete'), {type: 'button', 'aria-label': `${t('delete')} ${item.name}`});
            remove.addEventListener('click', () => {
                if (items.length === 1 || (item.enabled !== false && items.filter(value => value.enabled !== false).length === 1)) {error.textContent = t('provider_required'); return;}
                if (!confirm(t('confirm_delete'))) return;
                saveProviders(kind.value, items.filter(value => value.id !== item.id)); render(); refresh();
            });
            row.append(edit, up, remove); list.append(row);
        });
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(form));
        const items = providers(kind.value, false);
        const previous = items.find(item => item.id === values.id);
        const candidate = {...previous, ...values, id: values.id || crypto.randomUUID(), enabled: previous?.enabled ?? true};
        if (!validProvider(candidate) || [...providers('web', false), ...providers('ai', false)].some(item => item.id !== candidate.id && item.prefix.toLowerCase() === candidate.prefix.toLowerCase())) {error.textContent = t('invalid_provider'); return;}
        saveProviders(kind.value, previous ? items.map(item => item.id === candidate.id ? candidate : item) : [...items, candidate]);
        form.reset(); error.textContent = ''; render(); refresh();
    });
    form.addEventListener('reset', () => {form.elements.id.value = ''; error.textContent = '';});
    kind.addEventListener('change', () => {form.reset(); render();});
    document.getElementById('search-settings-open').addEventListener('click', () => {render(); dialog.showModal();});
}
