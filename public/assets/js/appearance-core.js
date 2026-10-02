export const palettes={
    light:{background:'#f4f6fa',text:'#1e293b',panel:'#ffffff',border:'#d2d9e4',muted:'#526174',accent:'#304fc3'},
    dark:{background:'#101723',text:'#e4e9f2',panel:'#1b2534',border:'#425169',muted:'#a9b7ca',accent:'#a8baff'},
    forest:{background:'#11251d',text:'#edf7ef',panel:'#1d382a',border:'#557862',muted:'#b5cbb9',accent:'#b1e3b9'},
    rose:{background:'#fff1f4',text:'#482532',panel:'#fffafd',border:'#dfbbc6',muted:'#795866',accent:'#923b60'},
};
export const color=(value,fallback)=>typeof value==='string' && /^#[a-f\d]{6}$/i.test(value)?value:fallback;
export const bounded=(value,fallback,min,max)=>Number.isFinite(Number(value)) && value!=='' && value!==null?Math.min(max,Math.max(min,Number(value))):fallback;
export function fontUrl(value) {
    try {const url=new URL(value);return url.protocol==='https:' && !url.username && !url.password && url.href.length<=2048?url.href:null;}catch{return null;}
}
export const fontName=value=>typeof value==='string' && /^[a-zA-Z0-9 _,-]{1,120}$/.test(value)?value:null;
// NOAA fractional-year equations; longitude is positive east.
// https://gml.noaa.gov/grad/solcalc/solareqns.PDF
export function solarTimes(date,latitude,longitude) {
    if(!Number.isFinite(latitude)||!Number.isFinite(longitude)||Math.abs(latitude)>90||Math.abs(longitude)>180)return null;
    const rad=Math.PI/180,year=date.getUTCFullYear(),start=Date.UTC(year,0,1),day=Math.floor((date-start)/86400000)+1;
    const days=(Date.UTC(year+1,0,1)-start)/86400000,gamma=2*Math.PI/days*(day-1);
    const equation=229.18*(0.000075+0.001868*Math.cos(gamma)-0.032077*Math.sin(gamma)-0.014615*Math.cos(2*gamma)-0.040849*Math.sin(2*gamma));
    const declination=0.006918-0.399912*Math.cos(gamma)+0.070257*Math.sin(gamma)-0.006758*Math.cos(2*gamma)+0.000907*Math.sin(2*gamma)-0.002697*Math.cos(3*gamma)+0.00148*Math.sin(3*gamma);
    const cosine=(Math.cos(90.833*rad)-Math.sin(latitude*rad)*Math.sin(declination))/(Math.cos(latitude*rad)*Math.cos(declination));
    if(cosine>1)return {polar:'night',sunrise:null,sunset:null};
    if(cosine< -1)return {polar:'day',sunrise:null,sunset:null};
    const noon=720-4*longitude-equation,angle=Math.acos(Math.max(-1,Math.min(1,cosine)))/rad;
    const midnight=Date.UTC(year,date.getUTCMonth(),date.getUTCDate());
    return {polar:null,sunrise:midnight+(noon-4*angle)*60000,sunset:midnight+(noon+4*angle)*60000};
}
export function daytime(date,latitude,longitude) {
    if(!Number.isFinite(latitude)||!Number.isFinite(longitude))return null;
    // Align the calculation to the geographic solar day, including the date line.
    const solarDate=new Date(date.getTime()+longitude*240000);
    const reference=new Date(Date.UTC(solarDate.getUTCFullYear(),solarDate.getUTCMonth(),solarDate.getUTCDate(),12));
    const times=solarTimes(reference,latitude,longitude);if(!times)return null;
    return times.polar?times.polar==='day':date.getTime()>=times.sunrise && date.getTime()<times.sunset;
}
export function themePalette(settings,date=new Date(),osDark=false) {
    const mode=settings.theme || 'solar';
    let key=mode;
    if(mode==='solar') {const region=settings.themeRegion;const day=region?daytime(date,region.latitude,region.longitude):null;key=day===null?(osDark?'dark':'light'):(day?'light':'dark');}
    if(mode==='os')key=osDark?'dark':'light';
    const custom=mode==='custom'?settings.customThemes?.[settings.customThemeId]:null;
    const base=palettes[key] || palettes.light;
    return Object.fromEntries(Object.entries(base).map(([name,value])=>[name,color(custom?.[name],value)]));
}
export function greetingKey(hour){return hour<11?'greeting_morning':hour<18?'greeting_day':'greeting_evening';}

export function searchStyles(settings,palette) {
    const heights={compact:48,standard:56,large:72},positions={upper:'8vh',center:'20vh',lower:'30vh'};
    const background=color(settings.searchBackground,palette.panel);
    const rgb=[1,3,5].map(offset=>parseInt(background.slice(offset,offset+2),16)).join(',');
    return {
        '--search-offset':positions[settings.searchPosition] || positions.upper,
        '--search-width':settings.searchWidthMode==='fixed'?`${bounded(settings.searchWidth,700,320,900)}px`:'900px',
        '--search-height':`${heights[settings.searchHeight] || heights.standard}px`,
        '--search-background':`rgba(${rgb},${bounded(settings.searchOpacity,.7,0,1)})`,
        '--search-blur':`${bounded(settings.searchBlur,12,0,40)}px`,
        '--search-border':color(settings.searchBorder,palette.border),
        '--search-border-width':`${bounded(settings.searchBorderWidth,1,0,8)}px`,
        '--search-radius':`${bounded(settings.searchRadius,16,0,60)}px`,
        '--search-shadow':settings.searchShadow===false?'none':'0 8px 40px #182a4920',
        '--search-text':color(settings.searchText,palette.text),
        '--search-placeholder':color(settings.searchPlaceholder,palette.muted),
    };
}
