import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {AsyncLocalStorage} from 'node:async_hooks';
import {SyncSession} from '../public/assets/js/sync-session.js';
import {syncDocument,syncValues} from '../public/assets/js/sync-data.js';
import {syncUser,request,writeSync} from '../public/assets/js/sync-api.js';
import {requiresCommandConfirmation} from '../public/assets/js/command-registry.js';
const fixture=JSON.parse(await readFile(process.argv[2],'utf8')),origin=process.argv[3];
if(!['http://127.0.0.1:8083','http://127.0.0.1:8084'].includes(origin))throw new Error('Isolated origin required');
const context=new AsyncLocalStorage(),nativeFetch=globalThis.fetch,presets={web:[],ai:[]};
globalThis.fetch=async(path,options={})=>{
    const device=context.getStore();if(!device)throw new Error('Missing device context');
    const response=await nativeFetch(new URL(path,origin),{...options,headers:{...options.headers,Cookie:Object.entries(device.cookies).map(([key,value])=>key+'='+value).join('; ')}});
    for(const header of response.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(header);if(match)device.cookies[match[1]]=match[2];}
    return response;
};
function device(cookies,settings,choice){
    const value={cookies:{...cookies},state:{settings},cloudHistory:{}};
    value.session=new SyncSession({user:syncUser,current:async owner=>String((await syncUser())?.id)===String(owner),
        checkpoint:owner=>value.state.syncCheckpoint?.userId===String(owner)?value.state.syncCheckpoint:null,
        preferences:()=>({historyEnabled:false}),
        local:checkpoint=>syncDocument(value.state,presets,value.cloudHistory,checkpoint),
        read:async()=>{const result=await request('/api/sync');assert.equal(result.status,200);value.cloudHistory=result.data.document.history||{};return result.data;},
        write:writeSync,initial:async()=>choice,
        conflicts:async()=>{throw new Error('Unexpected conflict');},
        accept:(document,checkpoint)=>{value.state={...value.state,...syncValues(value.state,document,checkpoint,new Date().toISOString(),presets)};},
        status:status=>{value.status=status;}});
    value.run=()=>context.run(value,()=>value.session.run());return value;
}
const a=device(fixture[0].devices[0],{commandConfirmations:{'theme-change':false,'ai-change':true},syncEnabled:true},'local');
const b=device(fixture[0].devices[1],{commandConfirmations:{'theme-change':true},syncEnabled:true},'cloud');
const command={effect:'state',confirmationKey:'theme-change'};
await a.run();await b.run();
assert.equal(requiresCommandConfirmation(command,b.state.settings.commandConfirmations),false);
assert.equal(b.state.settings.commandConfirmations['ai-change'],true);
console.log('PASS: operation-specific confirmation preferences reach second device through real sync API');
a.state.settings.commandConfirmations={'theme-change':true,'ai-change':false};await a.run();await b.run();
assert.equal(requiresCommandConfirmation(command,b.state.settings.commandConfirmations),true);
assert.equal(requiresCommandConfirmation({...command,confirmationKey:'ai-change'},b.state.settings.commandConfirmations),false);
console.log('PASS: re-enabled confirmation and independent AI setting both propagate');
delete a.state.settings.commandConfirmations;await a.run();await b.run();
assert.equal(Object.hasOwn(b.state.settings,'commandConfirmations'),false);
assert.equal(requiresCommandConfirmation(command,b.state.settings.commandConfirmations||{}),true);
console.log('PASS: category reset removes preferences in cloud and restores default confirmation on second device');
const other=device(fixture[1].devices[0],{commandConfirmations:{'theme-change':false}},'local');await other.run();await a.run();
assert.equal(requiresCommandConfirmation(command,a.state.settings.commandConfirmations||{}),true);
console.log('PASS: separate account preferences do not change first account');
