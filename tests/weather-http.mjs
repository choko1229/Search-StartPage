import assert from 'node:assert/strict';
import {WeatherContext} from '../public/assets/js/weather-context.js';
import {selectBackground} from '../public/assets/js/background-core.js';
const base=process.argv[2];
if(!['http://127.0.0.1:8083','http://127.0.0.1:8084','http://127.0.0.1:8085','http://127.0.0.1:8086'].includes(base))throw new Error('Dedicated test ports required');
let passed=0;
const check=value=>{assert.ok(value);passed++;};
const csrf=await fetch(base+'/api/csrf');
const token=(await csrf.json()).data.csrf_token;
const cookies={};for(const header of csrf.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(header);if(match)cookies[match[1]]=match[2];}
const cookie=Object.entries(cookies).map(([name,value])=>name+'='+value).join('; ');
const post=(body,authorized=true)=>fetch(base+'/api/weather',{method:'POST',headers:{'Content-Type':'application/json',Cookie:cookie,...authorized?{'X-CSRF-Token':token}:{}},body:JSON.stringify(body),signal:AbortSignal.timeout(15000)});
let response=await post({latitude:0,longitude:0},false);
check(response.status===403 && (await response.json()).error.code==='CSRF_INVALID');
for(const bad of [{},{latitude:'0',longitude:0},{latitude:91,longitude:0},{latitude:0,longitude:-181},{latitude:0,longitude:0,url:'http://127.0.0.1'},{latitude:null,longitude:0}]){
    response=await post(bad);const data=await response.json();check(response.status===422 && data.error.code==='INVALID_INPUT');
}
response=await fetch(base+'/api/weather');check(response.status===405);
response=await post({latitude:35.68,longitude:139.76});
const data=await response.json();
if(response.status===200){
    check(data.success===true && ['clear','cloudy','fog','rain','snow','storm'].includes(data.data.weather));
    check(Number.isFinite(data.data.temperature) && data.data.observedAt>Date.now()/1000-7200);
    check(!JSON.stringify(data).includes('latitude') && !JSON.stringify(data).includes('apikey'));
    const weather=new WeatherContext({fetcher:(url,options)=>fetch(base+url,{...options,headers:{...options.headers,Cookie:cookie}})});
    weather.read({latitude:35.68,longitude:139.76});await weather.pending;
    const context=weather.read({latitude:35.68,longitude:139.76});
    check(context.weather===data.data.weather && context.temperature===data.data.temperature);
    const background={id:'live-weather',name:'Weather proof',type:'solid',color:'#000000',rule:{operator:'and',conditions:[{type:'weather',values:[context.weather]},{type:'temperature',min:context.temperature-.1,max:context.temperature+.1}]}};
    check(selectBackground([background,{id:'default',name:'Default',type:'solid',color:'#ffffff'}],{backgroundSwitch:'rules'},context).id==='live-weather');
    console.log('Live provider success verified using public city coordinates.');
}else{
    check(response.status===503 && data.error.code==='WEATHER_UNAVAILABLE');
    console.log('Live provider unavailable; safe failure verified. Live success remains unconfirmed.');
}
response=await fetch(base+'/');check(response.status===200 && !(await response.text()).includes('Stack trace:'));
console.log(`${passed} weather HTTP assertions passed.`);
