export const commandCategories=['commands','favorites','search','ai','settings','tags','folders','history'];
const weights={commands:300,favorites:250,search:225,ai:225,settings:180,tags:120,folders:120,history:80};
const normalize=value=>String(value).normalize('NFKC').toLocaleLowerCase().trim().replace(/\s+/gu,' ');
const forbidden=new Set(['__proto__','constructor','prototype']);
const validId=value=>typeof value==='string'&&/^[a-zA-Z0-9:_-]{1,160}$/.test(value)&&!forbidden.has(value);
const validText=value=>typeof value==='string'&&value.trim()!==''&&value.length<=512;
const subsequence=(needle,haystack)=>{let at=0;for(const character of haystack){if(character===needle[at])at++;if(at===needle.length)return true;}return false;};
function statistics(usage,id,now) {
    const value=usage&&Object.hasOwn(usage,id)?usage[id]:null;
    return {count:Number.isSafeInteger(value?.count)&&value.count>=0?Math.min(value.count,1000000):0,
        lastUsed:Number.isFinite(value?.lastUsed)&&value.lastUsed>=0&&value.lastUsed<=now?value.lastUsed:0};
}
export function commandScore(command,query,usage={},now=Date.now()) {
    const needle=normalize(query).slice(0,256),title=normalize(command.title),keywords=(command.keywords||[]).map(normalize);
    const stats=statistics(usage,command.id,now);
    let match=0;
    if(needle) {
        if(title===needle)match=10000;
        else if(title.startsWith(needle))match=6000;
        else if(title.includes(needle))match=4000;
        else {
            const terms=needle.split(' '),texts=[title,...keywords];
            if(!terms.every(term=>texts.some(text=>text.includes(term)||subsequence(term,text))))return null;
            match=terms.reduce((score,term)=>score+(title.includes(term)?1200:keywords.some(text=>text.includes(term))?800:400),0);
            match=Math.min(match,3000);
        }
    }
    const usageScore=Math.min(200,Math.log2(stats.count+1)*20);
    const recencyScore=stats.lastUsed>0?200*Math.exp(-(now-stats.lastUsed)/(7*86400000)):0;
    return match+weights[command.category]+usageScore+recencyScore;
}

/** Registrations contain executable callbacks; search never executes a callback. */
export class CommandRegistry {
    #commands=new Map();
    register(command) {
        if(!command||!validId(command.id)||!validText(command.title)||!commandCategories.includes(command.category)
            ||!['navigate','search','state'].includes(command.effect)||typeof command.run!=='function'
            ||(command.keywords!==undefined&&(!Array.isArray(command.keywords)||command.keywords.length>20||!command.keywords.every(validText)))
            ||(command.confirmationKey!==undefined&&!validId(command.confirmationKey)))throw new Error('INVALID_COMMAND');
        if(this.#commands.has(command.id))throw new Error('DUPLICATE_COMMAND');
        const entry=Object.freeze({...command,keywords:Object.freeze([...(command.keywords||[])]),confirmationKey:command.confirmationKey||command.id});
        this.#commands.set(entry.id,entry);
        return ()=>{if(this.#commands.get(entry.id)===entry)this.#commands.delete(entry.id);};
    }
    get(id){return this.#commands.get(id)||null;}
    search(query='',{usage={},now=Date.now(),limit=50}={}) {
        const maximum=Number.isInteger(limit)?Math.max(0,Math.min(limit,200)):50;
        return [...this.#commands.values()].map(command=>({command,score:commandScore(command,query,usage,now)}))
            .filter(result=>result.score!==null).sort((a,b)=>b.score-a.score||a.command.title.localeCompare(b.command.title)||a.command.id.localeCompare(b.command.id))
            .slice(0,maximum).map(result=>result.command);
    }
    initial({usage={},now=Date.now(),limitPerGroup=5}={}) {
        const maximum=Number.isInteger(limitPerGroup)?Math.max(0,Math.min(limitPerGroup,20)):5;
        const commands=[...this.#commands.values()].filter(row=>row.category==='commands');
        const used=commands.filter(row=>statistics(usage,row.id,now).count>0);
        return [
            {id:'recent',items:[...used].sort((a,b)=>statistics(usage,b.id,now).lastUsed-statistics(usage,a.id,now).lastUsed||a.id.localeCompare(b.id)).slice(0,maximum)},
            {id:'frequent',items:[...used].sort((a,b)=>statistics(usage,b.id,now).count-statistics(usage,a.id,now).count||a.id.localeCompare(b.id)).slice(0,maximum)},
            ...['favorites','search','ai'].map(id=>({id,items:this.search('',{usage,now,limit:200}).filter(row=>row.category===id).slice(0,maximum)})),
        ];
    }
}

export function requiresCommandConfirmation(command,preferences={}) {
    return command.effect==='state'&&!(preferences&&Object.hasOwn(preferences,command.confirmationKey)&&preferences[command.confirmationKey]===false);
}
export function commandUsageAfter(usage,id,now=Date.now()) {
    if(!validId(id)||!Number.isFinite(now)||now<0)throw new Error('INVALID_COMMAND');
    const stats=statistics(usage,id,now);
    const entries=Object.entries(usage&&typeof usage==='object'?usage:{}).filter(([key])=>validId(key));
    const result=Object.fromEntries(entries);result[id]={count:stats.count+1,lastUsed:now};
    return Object.fromEntries(Object.entries(result).sort((a,b)=>(Number(b[1]?.lastUsed)||0)-(Number(a[1]?.lastUsed)||0)||a[0].localeCompare(b[0])).slice(0,500));
}
