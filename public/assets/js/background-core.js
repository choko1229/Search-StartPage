import {color,bounded} from './appearance-core.js';
export function mediaUrl(value) {
    if(typeof value!=='string'||value.length>2048)return null;
    if(/^\/(?!\/)/.test(value) && !/[\\\r\n]/.test(value))return value;
    try{const url=new URL(value);return url.protocol==='https:' && !url.username && !url.password?url.href:null;}catch{return null;}
}
export function normalizeBackground(value) {
    if(!value || typeof value!=='object' || !['solid','gradient','image','video'].includes(value.type) || typeof value.id!=='string' || !/^[a-zA-Z0-9_-]{1,80}$/.test(value.id))return null;
    if(typeof value.name!=='string'||!value.name.trim()||value.name.length>100)return null;
    const localFile=value.sourceType==='upload'&&value.fileId===value.id;
    if(['image','video'].includes(value.type)&&!localFile&&!mediaUrl(value.url))return null;
    return {...value,favorite:value.favorite===true,name:value.name.trim(),url:mediaUrl(value.url),color:color(value.color,'#101723'),colorEnd:color(value.colorEnd,'#304fc3'),angle:bounded(value.angle,135,0,360),blur:bounded(value.blur,0,0,40),brightness:bounded(value.brightness,1,0,2),overlay:bounded(value.overlay,0,0,1),overlayColor:color(value.overlayColor,'#000000'),position:['center','top','bottom','left','right'].includes(value.position)?value.position:'center',scale:bounded(value.scale,1,1,2),fit:['cover','contain','fill'].includes(value.fit)?value.fit:'cover',fixed:value.fixed!==false,autoplay:value.autoplay!==false,loop:value.loop!==false,mute:value.mute!==false,speed:bounded(value.speed,1,.25,4),paused:value.paused===true,fallback:mediaUrl(value.fallback),localOnly:value.localOnly!==false};
}
const dateKey=date=>`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
const timeNumber=value=>/^\d{2}:\d{2}$/.test(value) && Number(value.slice(0,2))<24 && Number(value.slice(3))<60?Number(value.slice(0,2))*60+Number(value.slice(3)):null;
export function conditionMatches(condition,context) {
    if(!condition||typeof condition!=='object')return false;
    const now=context.now || new Date(),minutes=now.getHours()*60+now.getMinutes(),today=dateKey(now);
    switch(condition.type){
    case 'time': {const start=timeNumber(condition.start),end=timeNumber(condition.end);if(start===null||end===null)return false;return start===end?true:start<end?minutes>=start&&minutes<end:minutes>=start||minutes<end;}
    case 'day':return Array.isArray(condition.days)&&condition.days.includes(now.getDay());
    case 'date':return condition.date===today;
    case 'period':return typeof condition.start==='string'&&typeof condition.end==='string'&&/^\d{4}-\d{2}-\d{2}$/.test(condition.start)&&/^\d{4}-\d{2}-\d{2}$/.test(condition.end)&&condition.start<=today&&today<=condition.end;
    case 'weather':return typeof context.weather==='string'&&Array.isArray(condition.values)&&condition.values.includes(context.weather);
    case 'temperature':return Number.isFinite(context.temperature)&&Number.isFinite(condition.min)&&Number.isFinite(condition.max)&&context.temperature>=condition.min&&context.temperature<=condition.max;
    case 'season': {const month=(now.getMonth()+(context.latitude<0?6:0))%12;return ['winter','spring','summer','autumn'][Math.floor(((month+1)%12)/3)]===condition.value;}
    case 'random':return Number.isFinite(condition.chance)&&condition.chance>=0&&condition.chance<=1&&(context.random || Math.random)()<condition.chance;
    case 'login':return typeof condition.value==='boolean'&&context.loggedIn===condition.value;
    case 'device':return ['mobile','tablet','desktop'].includes(condition.value)&&context.device===condition.value;
    case 'screen':return Number.isFinite(context.width)&&Number.isFinite(context.height)&&context.width>=bounded(condition.minWidth,0,0,100000)&&context.width<=bounded(condition.maxWidth,100000,0,100000)&&context.height>=bounded(condition.minHeight,0,0,100000)&&context.height<=bounded(condition.maxHeight,100000,0,100000);
    default:return false;
    }
}
export function ruleScore(rule,context,depth=0) {
    if(depth>12||!rule)return -1;
    if(rule.type)return conditionMatches(rule,context)?1:-1;
    if(!['and','or'].includes(rule.operator)||!Array.isArray(rule.conditions)||!rule.conditions.length||rule.conditions.length>100)return -1;
    const scores=rule.conditions.map(condition=>ruleScore(condition,context,depth+1));
    return rule.operator==='and'?(scores.every(score=>score>=0)?scores.reduce((sum,score)=>sum+score,0):-1):Math.max(...scores);
}
export function selectBackground(items,settings,context={}) {
    const rows=(Array.isArray(items)?items:[]).map(normalizeBackground).filter(row=>row && row.deleted!==true);if(!rows.length)return null;
    const random=context.random || Math.random;
    const pick=values=>values[Math.min(values.length-1,Math.max(0,Math.floor(random()*values.length)))];
    if(settings.backgroundSwitch==='random')return pick(rows);
    if(settings.backgroundSwitch==='rules'){
        const matches=rows.map(item=>({item,score:ruleScore(item.rule,context)})).filter(row=>row.score>=0);
        if(matches.length){const best=Math.max(...matches.map(row=>row.score));return pick(matches.filter(row=>row.score===best)).item;}
    }
    return rows.find(item=>item.id===settings.backgroundSelected) || rows[0];
}
export const backgroundPresets=[
    {id:'preset-terrace-dusk',name:'Terrace at dusk',type:'image',sourceType:'url',url:'/assets/backgrounds/terrace-dusk.png',overlay:.22,overlayColor:'#080e20',localOnly:true},
    {id:'preset-night',name:'Night',type:'gradient',color:'#101723',colorEnd:'#304fc3',angle:135,localOnly:true},
    {id:'preset-forest',name:'Forest',type:'gradient',color:'#11251d',colorEnd:'#557862',angle:90,localOnly:true},
    {id:'preset-dawn',name:'Dawn',type:'gradient',color:'#923b60',colorEnd:'#fff1f4',angle:20,localOnly:true},
];
