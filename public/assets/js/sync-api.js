export async function request(url, options={}) {
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000),...options});
    const payload=await response.json();return {status:response.status,data:payload.data};
}
export async function syncUser() {
    const result=await request('/api/user');
    if(result.status===401)return null;
    if(result.status!==200 || !result.data?.user)throw new Error('sync_auth_unavailable');
    return result.data.user;
}
export async function writeSync(version, document, userId) {
    const csrf=await request('/api/csrf');
    if(csrf.status!==200 || typeof csrf.data?.csrf_token!=='string')throw new Error('sync_csrf_failed');
    return request('/api/sync',{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf.data.csrf_token},body:JSON.stringify({version,document,user_id:userId})});
}
