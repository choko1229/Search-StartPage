export async function request(url, options={}) {
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000),...options});
    const payload=await response.json();
    if(payload.error?.code==='FEATURE_DISABLED')throw new Error('FEATURE_DISABLED');
    return {status:response.status,data:payload.data};
}
let observedUserId;
function observeUser(user) {
    const userId=user?String(user.id):null;
    if(userId!==observedUserId){
        observedUserId=userId;
        if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function')window.dispatchEvent(new CustomEvent('search-auth-change',{detail:{userId,authenticated:user!==null}}));
    }
    return user;
}
export async function syncUser() {
    const result=await request('/api/user');
    if(result.status===401)return observeUser(null);
    if(result.status!==200 || !result.data?.user)throw new Error('sync_auth_unavailable');
    return observeUser(result.data.user);
}
export async function writeSync(version, document, userId) {
    const csrf=await request('/api/csrf');
    if(csrf.status!==200 || typeof csrf.data?.csrf_token!=='string')throw new Error('sync_csrf_failed');
    return request('/api/sync',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf.data.csrf_token},body:JSON.stringify({version,document,user_id:userId})});
}
