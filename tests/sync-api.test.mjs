import assert from 'node:assert/strict';
import {writeSync,syncUser} from '../public/assets/js/sync-api.js';
let writes=0;
globalThis.fetch=async(url,options)=>{
    assert.equal(options.credentials,'same-origin');
    if(url==='/api/csrf')return new Response(JSON.stringify({success:true,data:{csrf_token:'test-csrf'}}));
    assert.equal(url,'/api/sync');assert.equal(options.method,'PUT');assert.equal(options.headers['X-CSRF-Token'],'test-csrf');
    assert.deepEqual(JSON.parse(options.body),{version:3,document:{settings:{theme:'dark'}},user_id:'1'});writes++;
    return new Response(JSON.stringify({success:false,data:{version:4,document:{settings:{theme:'other'}}}}),{status:409});
};
const result=await writeSync(3,{settings:{theme:'dark'}},'1');assert.equal(result.status,409);assert.equal(result.data.version,4);assert.equal(writes,1);
globalThis.fetch=async()=>new Response(JSON.stringify({success:true,data:{token:'wrong-key'}}));
await assert.rejects(writeSync(3,{},'1'),/sync_csrf_failed/);
globalThis.fetch=async()=>new Response(JSON.stringify({success:false}),{status:401});assert.equal(await syncUser(),null);
globalThis.fetch=async()=>new Response(JSON.stringify({success:false}),{status:503});await assert.rejects(syncUser(),/sync_auth_unavailable/);
console.log('12 sync API transport assertions passed.');
