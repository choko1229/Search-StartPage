import assert from 'node:assert/strict';
import {clientPresets} from '../public/assets/js/provider-presets.js';
const rows={web:[{id:'web',name:'Site web',url:'https://web.example/?q={query}',prefix:'web',icon:'W',enabled:true,copy:false,sortOrder:0}],ai:[{id:'ai',name:'Site AI',url:'https://ai.example',prefix:'ai',icon:'A',enabled:true,copy:true,sortOrder:0}]};
assert.deepEqual(clientPresets(rows),rows);
const received=structuredClone(rows);received.web[0].untrusted='ignored';assert.deepEqual(clientPresets(received),rows);
for(const value of [null,[],{}, {...rows,extra:[]},{...rows,web:[]},{...rows,web:Array(51).fill(rows.web[0])}])assert.throws(()=>clientPresets(value),/PRESETS_INVALID/);
for(const patch of [{id:'../web'},{url:'javascript:alert(1)'},{enabled:'true'},{copy:1},{sortOrder:-1},{sortOrder:NaN},{icon:'abcde'},{enabled:false}]){
    const bad=structuredClone(rows);Object.assign(bad.web[0],patch);assert.throws(()=>clientPresets(bad),/PRESETS_INVALID/);
}
for(const field of ['id','prefix']){const bad=structuredClone(rows);bad.ai[0][field]=bad.web[0][field];assert.throws(()=>clientPresets(bad),/PRESETS_INVALID/);}
console.log('Public installation presets validate shapes, URLs, uniqueness, bounds and enabled options without importing unknown fields.');
