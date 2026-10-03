import {validShortcut,keySignature,defaultKeys} from './search-preferences.js';
import {backgroundPresets,normalizeBackground} from './background-core.js';
export function onboardingBackgrounds(items=[]) {
    const seen=new Set();
    return [...backgroundPresets,...(Array.isArray(items)?items:[])].map(normalizeBackground).filter(row=>{
        if(!row||row.deleted===true||seen.has(row.id))return false;seen.add(row.id);return true;
    });
}
export function onboardingSteps(authenticated=false){return ['welcome','appearance','background','search','ai','favorites',...authenticated?[]:['discord'],'complete'];}
export function onboardingPosition(saved,steps){return steps.includes(saved?.step)?steps.indexOf(saved.step):0;}
export function onboardingPatch(step,values,allowed={web:[],ai:[]}) {
    const choose=(key,choices)=>{if(!choices.includes(values[key]))throw new Error('INVALID_INPUT');return values[key];};
    if(step==='appearance'){
        const size=Number(values.fontSize);if(!Number.isInteger(size)||size<10||size>32)throw new Error('INVALID_INPUT');
        return {theme:choose('theme',['solar','light','dark','os','forest','rose','custom']),fontSize:size,animationLevel:choose('animationLevel',['none','low','standard','rich'])};
    }
    if(step==='background'){
        if(values.backgroundMode==='library')return {backgroundMode:'library',backgroundSelected:choose('backgroundSelected',allowed.backgrounds||[]),backgroundSwitch:'manual'};
        if(!/^#[a-f\d]{6}$/i.test(values.backgroundColor))throw new Error('INVALID_INPUT');
        return {backgroundMode:choose('backgroundMode',['theme','solid']),backgroundColor:values.backgroundColor};
    }
    if(step==='search')return {initialMode:choose('initialMode',['web','ai','last']),webDefault:choose('webDefault',allowed.web)};
    if(step==='ai')return {aiDefault:choose('aiDefault',allowed.ai),aiOrder:choose('aiOrder',['fixed','usage','recent'])};
    if(step==='favorites'){
        const signatures=[values.webKey,values.aiKey,allowed.historyKey || defaultKeys.historyKey].map(keySignature);
        if(!validShortcut(values.webKey)||!validShortcut(values.aiKey)||new Set(signatures).size!==3)throw new Error('INVALID_INPUT');
        return {favoriteDisplay:choose('favoriteDisplay',['auto','icon-name','icon','card']),webKey:values.webKey,aiKey:values.aiKey};
    }
    return {};
}
