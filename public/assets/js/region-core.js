import {weatherRegion} from './weather-context.js';

export function requestRegion(geolocation=globalThis.navigator?.geolocation,timeout=20000) {
    return new Promise((resolve,reject)=>{
        if(typeof geolocation?.getCurrentPosition!=='function'){reject(new Error('region_unsupported'));return;}
        let settled=false;
        const finish=(error,value)=>{if(settled)return;settled=true;clearTimeout(timer);error?reject(new Error(error)):resolve(value);};
        const timer=setTimeout(()=>finish('region_timeout'),timeout);
        try{geolocation.getCurrentPosition(position=>{
            const region=weatherRegion(position?.coords);finish(region?null:'region_unavailable',region);
        },error=>finish(error?.code===1?'region_denied':error?.code===3?'region_timeout':'region_unavailable'),{enableHighAccuracy:false,timeout:10000,maximumAge:300000});}
        catch{finish('region_unavailable');}
    });
}

