import assert from 'node:assert/strict';
import {assertFeatures,requireFeatures,rejectDisabled} from '../public/assets/js/site-policy.js';
import {request,syncUser} from '../public/assets/js/sync-api.js';
import {readBackgrounds} from '../public/assets/js/background-api.js';
const originalFetch=globalThis.fetch;
try {
    assertFeatures({cloud_sync:true},['cloud_sync']);
    rejectDisabled({success:true});
    assert.throws(()=>rejectDisabled({error:{code:'FEATURE_DISABLED'}}),/FEATURE_DISABLED/);
    rejectDisabled({error:{code:'AUTH_REQUIRED'}});
    assert.throws(()=>assertFeatures({cloud_sync:false},['cloud_sync']),/FEATURE_DISABLED/);
    assert.throws(()=>assertFeatures({},['cloud_sync']),/site_policy_unavailable/);
    assert.throws(()=>assertFeatures({cloud_sync:'true'},['cloud_sync']),/site_policy_unavailable/);
    let flags={cloud_sync:false};
    globalThis.fetch=async()=>Response.json({success:true,data:{flags}});
    await assert.rejects(requireFeatures(['cloud_sync']),/FEATURE_DISABLED/);
    flags={cloud_sync:true};await requireFeatures(['cloud_sync']);
    globalThis.fetch=async()=>Response.json({success:false,error:{code:'FEATURE_DISABLED'}},{status:403});
    await assert.rejects(request('/api/sync'),/FEATURE_DISABLED/);
    await assert.rejects(readBackgrounds('1'),/FEATURE_DISABLED/);
    // A policy rejection never becomes a signed-out user observation.
    await assert.rejects(syncUser(),/FEATURE_DISABLED/);
    globalThis.fetch=async()=>Response.json({success:false,error:{code:'AUTH_REQUIRED'}},{status:401});
    assert.equal(await syncUser(),null);
    globalThis.fetch=async()=>Response.json({success:true,data:{user:{id:'1'}}});
    assert.equal((await syncUser()).id,'1');
    globalThis.fetch=async()=>Response.json({success:false},{status:503});
    await assert.rejects(requireFeatures(['cloud_sync']),/site_policy_unavailable/);
} finally {globalThis.fetch=originalFetch;}
console.log('Site policy validation, disable responses, recovery and authentication separation passed.');
