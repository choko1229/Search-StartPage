import {t,node} from './i18n.js';
let dialogQueue=Promise.resolve();
export function syncDialog(kind,conflicts=[],scope='') {
    const result=dialogQueue.then(()=>showSyncDialog(kind,conflicts,scope));
    dialogQueue=result.catch(()=>{});return result;
}
function showSyncDialog(kind, conflicts,scope) {
    return new Promise(resolve=>{
        const dialog=node('dialog',undefined,{'aria-labelledby':'sync-dialog-title',class:'sync-dialog'});
        dialog.append(node('h2',t(kind==='initial'?'sync_initial_title':'sync_conflicts')+(scope?' · '+scope:''),{id:'sync-dialog-title'}));
        const finish=value=>{dialog.close();dialog.remove();resolve(value);};
        dialog.addEventListener('cancel',event=>{event.preventDefault();finish(kind==='initial'?'later':null);});
        if(kind==='initial') {
            dialog.append(node('p',t('sync_initial_help')));
            for(const choice of ['local','cloud','later']) {
                const button=node('button',t('sync_'+choice),{type:'button'});
                button.addEventListener('click',()=>finish(choice));dialog.append(button);
            }
        } else {
            const inputs=[];
            for(const conflict of conflicts) {
                const group=node('fieldset');group.append(node('legend',conflict.path.join(' / ')));
                for(const side of ['previous','local','cloud']) {
                    group.append(node('strong',t('sync_'+side)));
                    group.append(node('pre',conflict[side].present ? JSON.stringify(conflict[side].value,null,2) : t('sync_deleted')));
                }
                const label=node('label',t('sync_choose'));const select=node('select',undefined,{'aria-label':t('sync_choose')});
                select.append(node('option',t('sync_choose'),{value:''}),node('option',t('sync_local'),{value:'local'}),node('option',t('sync_cloud'),{value:'cloud'}));
                label.append(select);group.append(label);
                const remember=node('input',undefined,{type:'checkbox'});const rememberLabel=node('label',t('sync_remember'));
                rememberLabel.prepend(remember);group.append(rememberLabel);dialog.append(group);inputs.push({id:conflict.id,select,remember});
            }
            const save=node('button',t('save'),{type:'button'});
            save.addEventListener('click',()=>{
                const missing=inputs.find(item=>!item.select.value);
                if(missing) {missing.select.focus();return;}
                finish({choices:Object.fromEntries(inputs.map(item=>[item.id,item.select.value])),rules:Object.fromEntries(inputs.filter(item=>item.remember.checked).map(item=>[item.id,item.select.value]))});
            });
            const later=node('button',t('sync_later'),{type:'button'});later.addEventListener('click',()=>finish(null));dialog.append(save,later);
        }
        document.body.append(dialog);dialog.showModal();
    });
}
