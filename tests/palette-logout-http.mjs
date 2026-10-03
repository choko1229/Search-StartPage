import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {logoutFromPalette,recoverPaletteLogout} from '../public/assets/js/palette-logout-core.js';
import {syncedDataRemoval} from '../public/assets/js/account-data.js';
const fixture=JSON.parse(await readFile(process.argv[2],'utf8')),origin=process.argv[3];
if(!['http://127.0.0.1:8083','http://127.0.0.1:8084'].includes(origin))throw new Error('Isolated origin required');
function device(credentials,owner,clear=true){
    const value={cookies:{...credentials},state:{settings:{clearSyncedOnLogout:clear},favorites:[{id:'owned'},{id:'local'}],backgrounds:[{id:'cloud',fileId:'cloud',cloudSync:true,cloudOwner:owner},{id:'off',fileId:'off',cloudSync:false}],syncOwnership:{userId:owner,collections:{favorites:['owned']},settings:[]},backgroundOwnership:{userId:owner,ids:['cloud','off']}},files:new Map([['cloud',new Blob(['cloud'])],['off',new Blob(['local'])]]),posts:0,failPrepare:false,failComplete:false,lost:false};
    value.io={pending:()=>value.state.paletteLogoutPending,clearOnLogout:()=>value.state.settings.clearSyncedOnLogout,
        prepare:async intent=>{if(value.failPrepare)throw new Error('test_quota');value.state={...value.state,paletteLogoutPending:structuredClone(intent)};},
        complete:async intent=>{if(value.failComplete)throw new Error('test_quota');const plan=intent.clear?syncedDataRemoval(value.state,intent.userId):{values:{},files:[]};value.state={...value.state,...plan.values,paletteLogoutPending:null};for(const file of plan.files)value.files.delete(file.id);},
        request:async(path,options={})=>{
            if(path==='/api/auth/logout')value.posts++;
            const response=await fetch(new URL(path,origin),{...options,headers:{...options.headers,Cookie:Object.entries(value.cookies).map(([key,cookie])=>key+'='+cookie).join('; ')}});
            for(const header of response.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(header);if(match)value.cookies[match[1]]=match[2];}
            const payload=await response.json();if(path==='/api/auth/logout'&&value.lost)throw new Error('test_response_lost');return {status:response.status,data:payload.data};
        }};return value;
}
const a=device(fixture[0].devices[0],fixture[0].userId);
a.failPrepare=true;await assert.rejects(logoutFromPalette(a.io),/test_quota/);assert.equal(a.posts,0);assert.equal((await a.io.request('/api/user')).status,200);
console.log('PASS: storage preflight failure makes no logout request and retains real authentication');
a.failPrepare=false;a.state.paletteLogoutPending={userId:fixture[0].userId,clear:true};assert.equal(await recoverPaletteLogout(a.io),false);assert.equal(a.state.favorites.length,2);
const csrf=await a.io.request('/api/csrf');const forbidden=await a.io.request('/api/auth/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf.data.csrf_token},body:JSON.stringify({user_id:fixture[1].userId})});assert.equal(forbidden.status,403);assert.equal((await a.io.request('/api/user')).status,200);a.posts=0;
console.log('PASS: real owner mismatch denied; recovery does not clear while original owner is still authenticated');
a.failComplete=true;await assert.rejects(logoutFromPalette(a.io),/test_quota/);assert.equal(a.posts,1);assert.equal((await a.io.request('/api/user')).status,401);assert.ok(a.state.paletteLogoutPending);assert.ok(a.files.has('cloud'));
a.failComplete=false;assert.equal(await recoverPaletteLogout(a.io),true);assert.deepEqual(a.state.favorites.map(row=>row.id),['local']);assert.deepEqual(a.state.backgrounds.map(row=>row.id),['off']);assert.equal(a.files.has('cloud'),false);assert.equal(a.files.has('off'),true);assert.equal(a.state.paletteLogoutPending,null);assert.equal(await recoverPaletteLogout(a.io),false);
console.log('PASS: committed logout plus failed local cleanup recovers from real guest status; owned data removed, local/sync-off files retained');
const b=device(fixture[0].devices[1],fixture[0].userId,false);await logoutFromPalette(b.io);assert.equal(b.posts,1);assert.equal(b.state.favorites.length,2);assert.equal(b.files.size,2);assert.equal(b.state.paletteLogoutPending,null);assert.equal((await b.io.request('/api/user')).status,401);
console.log('PASS: clear OFF retains data and files after real authenticated logout');
const c=device(fixture[1].devices[0],fixture[1].userId);c.lost=true;await assert.rejects(logoutFromPalette(c.io),/test_response_lost/);assert.ok(c.state.paletteLogoutPending);c.lost=false;await logoutFromPalette(c.io);assert.equal(c.posts,1);assert.equal(c.state.paletteLogoutPending,null);
console.log('PASS: lost real HTTP logout response recovers without repeating POST');
const d=device(fixture[1].devices[1],fixture[0].userId);d.state.paletteLogoutPending={userId:fixture[0].userId,clear:true};assert.equal(await recoverPaletteLogout(d.io),true);assert.equal((await d.io.request('/api/user')).data.user.id,Number(fixture[1].userId));await logoutFromPalette(d.io);assert.equal(d.posts,1);assert.equal((await d.io.request('/api/user')).status,401);
console.log('PASS: old-owner cleanup does not sign out the current account; explicit subsequent logout signs out current account');
