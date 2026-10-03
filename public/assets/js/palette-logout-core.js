const owner=value=>typeof value==='number'&&Number.isSafeInteger(value)&&value>0?String(value):typeof value==='string'&&/^[1-9][0-9]{0,18}$/.test(value)?value:null;
const valid=intent=>intent&&owner(intent.userId)&&typeof intent.clear==='boolean';
export async function recoverPaletteLogout(io) {
    const intent=io.pending();if(!valid(intent))return false;
    const current=await io.request('/api/user');
    if(current.status===200){const id=owner(current.data?.user?.id);if(!id)throw new Error('logout_unavailable');if(id===String(intent.userId))return false;}
    else if(current.status!==401)throw new Error('logout_unavailable');
    await io.complete(intent);return true;
}
export async function logoutFromPalette(io) {
    const recovered=await recoverPaletteLogout(io);
    const current=await io.request('/api/user'),id=owner(current.data?.user?.id);
    if(recovered&&current.status===401)return;
    if(current.status!==200||!id)throw new Error('logout_unavailable');
    const intent={userId:id,clear:io.clearOnLogout()!==false};
    await io.prepare(intent);
    const csrf=await io.request('/api/csrf');
    if(csrf.status!==200||typeof csrf.data?.csrf_token!=='string'||!csrf.data.csrf_token)throw new Error('logout_unavailable');
    const response=await io.request('/api/auth/logout',{method:'POST',headers:{'X-CSRF-Token':csrf.data.csrf_token,'Content-Type':'application/json'},body:JSON.stringify({user_id:id})});
    if(response.status!==200||response.data?.logged_out!==true||owner(response.data.user_id)!==id)throw new Error('logout_unavailable');
    await io.complete(intent);
}
