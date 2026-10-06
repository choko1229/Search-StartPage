import {get,setMany} from './store.js';
import {request} from './sync-api.js';
import {StatisticsQueue} from './statistics-core.js';
import {isOnline} from './api-transport.js';
let timer;
let initialized=false;
async function persist(update) {
    for(let attempt=0;;attempt++){
        try{return await setMany(state=>({statistics:update(state.statistics)}),[],state=>({statistics:state.statistics??null}));}
        catch(error){if(error.message!=='storage_conflict'||attempt>=3)throw error;}
    }
}
const queue=new StatisticsQueue({read:()=>get('statistics',null),write:persist,send:async events=>{
    const csrf=await request('/api/csrf');if(csrf.status!==200||typeof csrf.data?.csrf_token!=='string')throw new Error('STATISTICS_CSRF_FAILED');
    return request('/api/statistics/event',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','X-CSRF-Token':csrf.data.csrf_token},body:JSON.stringify({events})});
}});
async function deliver(){
    clearTimeout(timer);
    if(!isOnline())return;
    try{await queue.flush();}catch{timer=setTimeout(deliver,60000);}
}
export async function recordStatistic(type,data={}) {
    // Statistics never block a user's search or local edits during storage failure.
    try{await queue.add(type,data);if(initialized){clearTimeout(timer);timer=setTimeout(deliver,0);}}catch{}
}
export function initializeStatistics(){
    if(initialized)return;initialized=true;
    window.addEventListener('online',()=>void deliver());
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)void deliver();});
    void recordStatistic('visit');
}
