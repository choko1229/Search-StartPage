// Test-only browser harness. This file is not referenced by production views.
const status=document.getElementById('status');
const choose=document.getElementById('choose');
const observer=new MutationObserver(()=>{
    const form=document.querySelector('.background-editor');
    if(form && !form.contains(choose)){form.append(choose,status);}
});
observer.observe(document.body,{childList:true,subtree:true});
document.getElementById('choose').addEventListener('click',()=>{
    const view=window,form=document.querySelector('.background-editor');
    if(!form){status.textContent='背景フォームが見つかりません';return;}
    const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII='),character=>character.charCodeAt(0));
    const transfer=new view.DataTransfer();transfer.items.add(new view.File([bytes],'generated-verification.png',{type:'image/png'}));
    form.elements.name.value='Local upload test';form.elements.sourceType.value='upload';form.elements.type.value='image';
    form.elements.file.files=transfer.files;form.elements.file.dispatchEvent(new view.Event('change',{bubbles:true}));
    status.textContent='生成画像を選択済み。製品の「背景を保存」で保存します';
});
