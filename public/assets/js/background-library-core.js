import {normalizeBackground} from './background-core.js';

export function libraryBackgrounds(items,sort='saved') {
    const rows=(Array.isArray(items)?items:[]).map(normalizeBackground).filter(Boolean);
    if(sort==='name')return rows.sort((a,b)=>a.name.localeCompare(b.name)||a.id.localeCompare(b.id));
    if(sort==='favorite')return rows.sort((a,b)=>Number(b.favorite)-Number(a.favorite)||a.name.localeCompare(b.name)||a.id.localeCompare(b.id));
    return rows;
}
