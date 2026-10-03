// Shared preferences are usable only after general synchronization has
// acknowledged this account. Legacy background rules remain account scoped.
const owns=(value,owner)=>value && String(value.userId)===String(owner);
const backgroundRules=value=>Object.fromEntries(Object.entries(value||{}).filter(([key,choice])=>{
    try {const path=JSON.parse(key);return Array.isArray(path)&&path.length>=2&&path.length<=16&&path[0]==='backgrounds'&&path.every(part=>typeof part==='string'&&part.length<=1000&&!['__proto__','prototype','constructor'].includes(part))&&['local','cloud'].includes(choice);}catch{return false;}
}));
export function readBackgroundRules(state,owner) {
    const checkpoint=owns(state.backgroundCheckpoint,owner)?state.backgroundCheckpoint:{};
    if(!owns(state.syncCheckpoint,owner))return checkpoint.rules||{};
    const shared=backgroundRules(state.settings?.syncRules);
    return checkpoint.rulesShared?shared:{...checkpoint.rules,...shared};
}
export function shareBackgroundRules(state,owner,selected={}) {
    const checkpoint=owns(state.backgroundCheckpoint,owner)?state.backgroundCheckpoint:{};
    const rules={...(checkpoint.rules||{}),...backgroundRules(selected)};
    if(!owns(state.syncCheckpoint,owner))return {backgroundCheckpoint:{...checkpoint,userId:String(owner),rules}};
    // Promote legacy rules once. Explicit removal of shared settings after
    // promotion must never resurrect old checkpoint preferences.
    const shared={...(!checkpoint.rulesShared?backgroundRules(checkpoint.rules):{}),...(state.settings?.syncRules||{}),...backgroundRules(selected)};
    return {settings:{...state.settings,syncRules:shared},backgroundCheckpoint:{...checkpoint,userId:String(owner),rules:{},rulesShared:true}};
}
