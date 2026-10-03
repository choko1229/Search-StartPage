export function weatherRegion(value) {
    if(!value || typeof value!=='object' || !Number.isFinite(value.latitude) || !Number.isFinite(value.longitude) || Math.abs(value.latitude)>90 || Math.abs(value.longitude)>180)return null;
    return {latitude:Math.round(value.latitude*100)/100,longitude:Math.round(value.longitude*100)/100};
}
export function needsWeather(rows) {
    let count=0;
    const visit=(rule,depth=0)=>{
        if(!rule || typeof rule!=='object' || depth>12 || ++count>100)return false;
        if(['weather','temperature'].includes(rule.type))return true;
        return Array.isArray(rule.conditions) && rule.conditions.some(child=>visit(child,depth+1));
    };
    return rows.some(row=>{count=0;return row?.deleted!==true && visit(row?.rule);});
}
export class WeatherContext {
    constructor({fetcher=globalThis.fetch,clock=()=>Date.now(),changed=()=>{}}={}) {
        Object.assign(this,{fetcher,clock,changed,key:null,cached:null,pending:null,retryAt:0,controller:null,generation:0});
    }
    read(value,needed=true) {
        const region=weatherRegion(value),key=region?JSON.stringify(region):null;
        if(key!==this.key){this.controller?.abort();this.generation++;this.key=key;this.cached=null;this.pending=null;this.retryAt=0;}
        if(!region)return {};
        const context={latitude:region.latitude,longitude:region.longitude};
        if(!needed)return context;
        const now=this.clock();
        if(this.cached?.expiresAt*1000>now)return {...context,weather:this.cached.weather,temperature:this.cached.temperature};
        this.cached=null;
        if(!this.pending && now>=this.retryAt){
            const generation=this.generation;
            this.controller=new AbortController();const controller=this.controller;
            const timer=setTimeout(()=>controller.abort(),10000);
            this.pending=this.load(region,controller.signal).then(data=>{
                if(generation!==this.generation)return;
                this.cached=data;this.retryAt=0;this.changed();
            }).catch(()=>{if(generation===this.generation){this.cached=null;this.retryAt=this.clock()+60000;}}).finally(()=>{
                clearTimeout(timer);if(generation===this.generation){this.pending=null;this.controller=null;}
            });
        }
        return context;
    }
    async load(region,signal) {
        const options={credentials:'same-origin',cache:'no-store',redirect:'error',signal};
        const csrf=await this.fetcher('/api/csrf',options),token=await csrf.json();
        if(csrf.status!==200 || typeof token.data?.csrf_token!=='string')throw new Error('weather_unavailable');
        const response=await this.fetcher('/api/weather',{...options,method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token.data.csrf_token},body:JSON.stringify(region)});
        const payload=await response.json(),data=payload?.data,now=this.clock()/1000;
        if(response.status!==200 || payload.success!==true || !data || !['clear','cloudy','fog','rain','snow','storm'].includes(data.weather)
            || !Number.isFinite(data.temperature) || data.temperature < -100 || data.temperature > 70
            || !Number.isInteger(data.observedAt) || data.observedAt<now-7200 || data.observedAt>now+900
            || !Number.isInteger(data.expiresAt) || data.expiresAt<=now || data.expiresAt>now+901)throw new Error('weather_unavailable');
        return {weather:data.weather,temperature:data.temperature,observedAt:data.observedAt,expiresAt:data.expiresAt};
    }
}
