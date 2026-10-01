// Standalone UI fixture: no authentication routes, database or user data.
// Run with Node, then open http://127.0.0.1:8090/ for dialog checks.
import {createServer} from 'node:http';
import {readFile} from 'node:fs/promises';
const messages={sync_initial_title:'最初の同期',sync_initial_help:'端末とクラウドのどちらを利用するか選択してください。',sync_local:'端末のデータ',sync_cloud:'クラウドのデータ',sync_later:'あとで',sync_conflicts:'変更が競合しています',sync_previous:'前回のデータ',sync_deleted:'削除済み',sync_choose:'使用する変更を選択',sync_remember:'この項目では今後も同じ選択を使う',save:'保存'};
const fixtureScript=`import {syncDialog} from '/assets/js/sync-dialogs.js';
document.getElementById('initial').onclick=async()=>{document.getElementById('result').textContent=await syncDialog('initial');};
document.getElementById('conflict').onclick=async()=>{const answer=await syncDialog('conflicts',[{id:'["settings","theme"]',path:['settings','theme'],previous:{present:true,value:'light'},local:{present:true,value:'dark'},cloud:{present:true,value:'custom'}}]);document.getElementById('result').textContent=JSON.stringify(answer);};`;
const html=`<!doctype html><html lang="ja"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/search.css"><title>同期ダイアログ検証</title><body><main><h1>同期ダイアログ検証</h1><p>認証・DBを利用しない独立したUI検証です。</p><button id="initial">初回選択を確認</button><button id="conflict">競合選択を確認</button><p id="result" role="status"></p></main><script type="application/json" id="search-bootstrap">${JSON.stringify({providers:{web:[],ai:[]},messages})}</script><script type="module" src="/fixture.js"></script></body></html>`;
const assets=new Map(['/assets/js/sync-dialogs.js','/assets/js/i18n.js','/assets/css/core.css','/assets/css/search.css'].map(path=>[path,new URL('../public'+path,import.meta.url)]));
const server=createServer(async(req,res)=>{
    if(req.method!=='GET') {res.writeHead(405);res.end();return;}
    const path=new URL(req.url,'http://localhost').pathname;
    try {
        if(path==='/') {res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);}
        else if(path==='/fixture.js') {res.setHeader('Content-Type','text/javascript');res.end(fixtureScript);}
        else if(assets.has(path)) {res.setHeader('Content-Type',path.endsWith('.js')?'text/javascript':'text/css');res.end(await readFile(assets.get(path)));}
        else {res.writeHead(404);res.end();}
    } catch {res.writeHead(500);res.end();}
});
server.listen(8090,'127.0.0.1',()=>console.log('Isolated dialog preview: http://127.0.0.1:8090/'));
