import assert from 'node:assert/strict';
import {logoutFromPalette,recoverPaletteLogout} from '../public/assets/js/palette-logout-core.js';
function fixture(){
    const state={pending:null,user:'42',clear:true,posts:0,completed:[],failPrepare:false,failComplete:false,lost:false,status:200};
    const io={pending:()=>state.pending,clearOnLogout:()=>state.clear,
        prepare:async intent=>{if(state.failPrepare)throw new Error('quota');state.pending=intent;},
        complete:async intent=>{if(state.failComplete)throw new Error('quota');state.completed.push(intent);state.pending=null;},
        request:async(path,options)=>{
            if(path==='/api/user')return state.status!==200?{status:state.status}:{status:state.user?200:401,data:{user:state.user?{id:state.user}:null}};
            if(path==='/api/csrf')return {status:200,data:{csrf_token:'token'}};
            assert.equal(path,'/api/auth/logout');assert.equal(options.headers['X-CSRF-Token'],'token');assert.equal(JSON.parse(options.body).user_id,state.user);
            state.posts++;const id=state.user;state.user=null;if(state.lost)throw new Error('response_lost');return {status:200,data:{logged_out:true,user_id:id}};
        }};return {state,io};
}
let {state,io}=fixture();await logoutFromPalette(io);assert.equal(state.posts,1);assert.equal(state.pending,null);assert.deepEqual(state.completed,[{userId:'42',clear:true}]);
({state,io}=fixture());state.clear=false;await logoutFromPalette(io);assert.equal(state.completed[0].clear,false);
({state,io}=fixture());state.failPrepare=true;await assert.rejects(logoutFromPalette(io),/quota/);assert.equal(state.posts,0);assert.equal(state.user,'42');
({state,io}=fixture());state.failComplete=true;await assert.rejects(logoutFromPalette(io),/quota/);assert.equal(state.user,null);assert.ok(state.pending);state.failComplete=false;await recoverPaletteLogout(io);assert.equal(state.pending,null);assert.equal(state.posts,1);
({state,io}=fixture());state.lost=true;await assert.rejects(logoutFromPalette(io),/response_lost/);state.lost=false;await logoutFromPalette(io);assert.equal(state.posts,1);assert.equal(state.pending,null);
({state,io}=fixture());state.pending={userId:'42',clear:true};assert.equal(await recoverPaletteLogout(io),false);assert.equal(state.completed.length,0);
state.status=503;await assert.rejects(recoverPaletteLogout(io),/logout_unavailable/);assert.ok(state.pending);
({state,io}=fixture());state.pending={userId:'41',clear:true};await logoutFromPalette(io);assert.equal(state.posts,1);assert.deepEqual(state.completed.map(item=>item.userId),['41','42']);
({state,io}=fixture());state.pending={userId:'__proto__',clear:true};assert.equal(await recoverPaletteLogout(io),false);assert.equal(state.completed.length,0);
console.log('Palette logout: durable intent, preflight storage failure, response loss, cleanup retry, confirmed auth and account switch passed.');
