import {t, node} from './i18n.js';
import {favorites, folders, saveFavorite} from './favorites-store.js';
import {rejectDisabled} from './site-policy.js';
const dialog = document.getElementById('favorite-editor');
const form = document.getElementById('favorite-form');
let request;
export function editFavorite(id, focus) {
    request?.abort(); form.reset();
    const item = favorites().find(value => value.id === id);
    form.elements.folderId.replaceChildren(node('option', t('no_folder'), {value:''}), ...folders().map(folder => node('option', folder.name, {value:folder.id})));
    for (const key of ['id','url','name','icon','folderId','description','shortcut']) form.elements[key].value = item?.[key] || '';
    form.elements.tags.value = item?.tags?.join(', ') || '';
    form.elements.color.value = item?.color || '#304fc3';
    form.elements.pinned.checked = item?.pinned || false;
    form.elements.visible.checked = item?.visible !== false;
    document.getElementById('favorite-error').textContent = '';
    document.getElementById('metadata-status').textContent = '';
    dialog.showModal(); form.elements[focus || 'url'].focus();
}
form.addEventListener('submit', event => {
    event.preventDefault();
    try {
        const data = Object.fromEntries(new FormData(form));
        saveFavorite({...data,pinned:form.elements.pinned.checked,visible:form.elements.visible.checked}, data.id || null);
        dialog.close();
    } catch(error) {document.getElementById('favorite-error').textContent = t(error.message);}
});
async function metadata() {
    const url = form.elements.url.value;
    if (!url || !form.elements.url.checkValidity()) return;
    request?.abort(); request = new AbortController();
    const active=request;
    const status = document.getElementById('metadata-status'); status.textContent = t('loading_metadata');
    try {
        const response = await fetch(`/api/favorites/metadata?url=${encodeURIComponent(url)}`,{signal:request.signal});
        const result = await response.json();
        if (active!==request || form.elements.url.value !== url) return;
        rejectDisabled(result);
        if (!response.ok || !result.success) throw new Error();
        for (const [field,key] of [['name','title'],['description','description'],['icon','favicon']]) if (!form.elements[field].value && result.data[key]) form.elements[field].value = result.data[key];
        status.textContent = t('metadata_loaded');
    } catch(error) {if(active===request && form.elements.url.value===url && error.name !== 'AbortError') status.textContent = t(error.message==='FEATURE_DISABLED'?'metadata_disabled':'metadata_unavailable');}
}
form.elements.url.addEventListener('blur', metadata);
document.getElementById('favorite-metadata').addEventListener('click',metadata);
document.getElementById('favorite-add').addEventListener('click',()=>editFavorite());
