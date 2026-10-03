export function assertFeatures(flags, required) {
    if (!flags || required.some(key => typeof flags[key] !== 'boolean')) throw new Error('site_policy_unavailable');
    if (required.some(key => !flags[key])) throw new Error('FEATURE_DISABLED');
}
export function rejectDisabled(payload) {
    if(payload?.error?.code==='FEATURE_DISABLED')throw new Error('FEATURE_DISABLED');
}
export async function requireFeatures(required) {
    const response=await fetch('/api/site-policy',{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});
    if(response.status!==200)throw new Error('site_policy_unavailable');
    const payload=await response.json();
    assertFeatures(payload.data?.flags,required);
}
