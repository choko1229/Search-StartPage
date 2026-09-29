import {t, node} from './i18n.js';
import {setting, setSetting} from './store.js';
import {providers, saveProviders} from './providers.js';
import {validProvider} from './search-core.js';
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
        for (const [key, title] of [['saveHistory', 'save_history'], ['externalSuggest', 'external_suggest_setting']]) {
            const label = node('label', t(title), {class: 'preference'});
            const checkbox = node('input', undefined, {type: 'checkbox'});
            checkbox.checked = setting(key, true);
            checkbox.addEventListener('change', () => setSetting(key, checkbox.checked));
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
