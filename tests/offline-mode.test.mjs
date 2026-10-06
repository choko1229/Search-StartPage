import assert from 'node:assert/strict';
const classes=new Set(),notices=[];
let online=false;
globalThis.window=new EventTarget();
Object.defineProperty(globalThis,'navigator',{configurable:true,value:{get onLine(){return online;}}});
globalThis.fetch=()=>{throw new Error('Offline presentation must not fetch');};
globalThis.localStorage={getItem(){throw new Error('Presentation must not read private state');},setItem(){throw new Error('Presentation must not alter preferences');}};
globalThis.document={
    getElementById:()=>({textContent:JSON.stringify({providers:{web:[],ai:[]},messages:{offline_local_mode:'Saved local files remain available.'}})}),
    createElement:tag=>({tag,hidden:false,attributes:{},setAttribute(key,value){this.attributes[key]=value;}}),
    querySelector:selector=>{assert.equal(selector,'.search-home');return {prepend:notice=>notices.push(notice)};},
    body:{classList:{toggle(key,on){if(on)classes.add(key);else classes.delete(key);}}},
};
const {initializeOfflineMode}=await import('../public/assets/js/offline-mode.js');
initializeOfflineMode();
assert.equal(notices.length,1);assert.equal(notices[0].textContent,'Saved local files remain available.');
assert.equal(notices[0].attributes.role,'status');assert.equal(notices[0].attributes['aria-live'],'polite');
assert.equal(notices[0].hidden,false);assert.equal(classes.has('offline-mode'),true);
online=true;window.dispatchEvent(new Event('online'));assert.equal(notices[0].hidden,true);assert.equal(classes.has('offline-mode'),false);
online=false;window.dispatchEvent(new Event('offline'));assert.equal(notices[0].hidden,false);assert.equal(classes.has('offline-mode'),true);
assert.equal(notices.length,1,'connection changes update one notice without duplicate content');
console.log('Offline accessibility notice and simple display recover without fetching or altering saved preferences.');
