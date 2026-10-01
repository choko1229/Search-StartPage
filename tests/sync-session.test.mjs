import assert from 'node:assert/strict';
import {SyncSession,syncInterval} from '../public/assets/js/sync-session.js';
function setup(local={settings:{theme:'light'}},cloud={version:1,document:{settings:{theme:'dark'}}},base=null) {
    const state={local,cloud,base,choice:'later',writes:0,status:'',answers:0};
    const session=new SyncSession({user:async()=>({id:1}),checkpoint:()=>state.base,local:()=>structuredClone(state.local),
        read:async()=>structuredClone(state.cloud),initial:async()=>{state.answers++;return state.choice;},
        conflicts:async conflicts=>({choices:Object.fromEntries(conflicts.map(c=>[c.id,'cloud'])),rules:{}}),
        write:async(version,document)=>{state.writes++;state.cloud={version:version+1,document:structuredClone(document)};return{status:200,data:state.cloud};},
        accept:(document,checkpoint)=>{state.local=document;state.base=checkpoint;},status:value=>state.status=value});
    return {state,session};
}
let {state,session}=setup();
assert.equal(await session.run(),false);assert.equal(state.writes,0);assert.equal(state.local.settings.theme,'light');assert.equal(state.status,'later');
({state,session}=setup());state.choice='cloud';assert.equal(await session.run(),true);assert.equal(state.local.settings.theme,'dark');assert.equal(state.base.userId,'1');
({state,session}=setup());state.choice='local';await session.run();assert.equal(state.cloud.document.settings.theme,'light');
({state,session}=setup({settings:{theme:'light',font:16}},{version:2,document:{settings:{theme:'dark',font:18}}},{document:{settings:{theme:'dark',font:16}}}));
await session.run();assert.deepEqual(state.local.settings,{theme:'light',font:18});assert.equal(state.answers,0);
({state,session}=setup({settings:{theme:'light'}},{version:2,document:{settings:{theme:'custom'}}},{document:{settings:{theme:'dark'}}}));
await session.run();assert.equal(state.local.settings.theme,'custom');
({state,session}=setup());state.choice='local';session.io.write=async()=>{throw new Error('offline');};
await assert.rejects(session.run(),/offline/);assert.equal(state.base,null);assert.equal(session.busy,false);
({state,session}=setup());state.choice='local';const original=session.io.write;
session.io.write=async(v,d)=>{state.local.settings.font=20;return original(v,d);};await session.run();assert.equal(state.local.settings.font,20);assert.equal(state.base.document.settings.font,undefined);
assert.equal(syncInterval(0),10000);assert.equal(syncInterval(60000),60000);assert.equal(syncInterval(400000),300000);
({state,session}=setup());state.choice='cloud';await session.run();assert.equal(state.writes,0);
({state,session}=setup());state.choice='local';let active=true;session.io.current=async()=>active;
const write=session.io.write;session.io.write=async(v,d)=>{const result=await write(v,d);active=false;return result;};
assert.equal(await session.run(),false);assert.equal(state.base,null);assert.equal(state.local.settings.theme,'light');
({state,session}=setup());state.choice='local';session.io.current=async()=>false;
assert.equal(await session.run(),false);assert.equal(state.writes,0);
({state,session}=setup());state.choice='local';const successfulWrite=session.io.write;
session.io.write=async(v,d)=>{state.cloud={version:2,document:{settings:{theme:'other'}}};state.choice='later';return{status:409,data:state.cloud};};
assert.equal(await session.run(),false);assert.equal(state.answers,2);assert.equal(state.base,null);assert.equal(state.cloud.document.settings.theme,'other');
({state,session}=setup({settings:{theme:'light',font:16}},{version:1,document:{settings:{theme:'dark',font:16}}},{document:{settings:{theme:'dark',font:16}}}));
let raced=false;const save=session.io.write;
session.io.write=async(v,d)=>{if(!raced){raced=true;state.cloud={version:2,document:{settings:{theme:'dark',font:18}}};return{status:409,data:state.cloud};}return save(v,d);};
await session.run();assert.deepEqual(state.local.settings,{theme:'light',font:18});assert.equal(state.base.version,3);
({state,session}=setup());state.choice='local';const accepted=session.io.accept;
session.io.accept=async()=>{await Promise.resolve();throw new Error('durable_write_failed');};
await assert.rejects(session.run(),/durable_write_failed/);assert.equal(state.base,null);assert.equal(session.busy,false);
session.io.accept=async(...args)=>{await Promise.resolve();accepted(...args);};
await session.run();assert.ok(state.base);assert.equal(state.status,'synced');
console.log('37 sync session assertions passed.');
