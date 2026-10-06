import {t,node} from './i18n.js';
import {isOnline} from './api-transport.js';
export function initializeOfflineMode() {
    const notice=node('p',t('offline_local_mode'),{class:'offline-notice',role:'status','aria-live':'polite'});
    document.querySelector('.search-home').prepend(notice);
    function update(){const offline=!isOnline();notice.hidden=!offline;document.body.classList.toggle('offline-mode',offline);}
    window.addEventListener('online',update);window.addEventListener('offline',update);update();
}
