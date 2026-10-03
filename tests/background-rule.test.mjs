import assert from 'node:assert/strict';
import {backgroundConditionTypes,defaultBackgroundCondition,validateBackgroundRule} from '../public/assets/js/background-rule-core.js';
import {conditionMatches,ruleScore,selectBackground} from '../public/assets/js/background-core.js';
const now=new Date(2026,3,1,12),context={now,weather:'clear',temperature:15,latitude:35,loggedIn:true,device:'desktop',width:1024,height:768,random:()=>.1};
const conditions=backgroundConditionTypes.map(type=>defaultBackgroundCondition(type,now));
assert.equal(conditions.length,11);
for(const condition of conditions){assert.deepEqual(validateBackgroundRule(condition),condition);assert.equal(conditionMatches(condition,context),true,condition.type);}
const combined={operator:'and',conditions};assert.equal(ruleScore(validateBackgroundRule(combined),context),11);
const nested={operator:'and',conditions:[{type:'day',days:[3]},{operator:'or',conditions:[{type:'weather',values:['rain']},{type:'temperature',min:10,max:20}]}]};
assert.equal(ruleScore(validateBackgroundRule(nested),context),2);assert.equal(ruleScore(nested,{...context,temperature:25}),-1);
const backgrounds=[{id:'time',name:'Time',type:'solid',rule:conditions[0]},{id:'specific',name:'Specific',type:'solid',rule:nested}];
assert.equal(selectBackground(backgrounds,{backgroundSwitch:'rules'},context).id,'specific');
for(const bad of [
    {type:'constructor'},{type:'time',start:'24:00',end:'01:00'},{type:'day',days:[]},{type:'day',days:[1.5]},
    {type:'date',date:'2026-02-30'},{type:'date',date:'0000-01-01'},{type:'period',start:'2026-12-01',end:'2026-01-01'},
    {type:'weather',values:['sunny']},{type:'temperature',min:20,max:10},{type:'temperature',min:NaN,max:10},
    {type:'season',value:'monsoon'},{type:'random',chance:1.01},{type:'login',value:'true'},{type:'device',value:'watch'},
    {type:'screen',minWidth:1920,maxWidth:320},{operator:'and',conditions:[]},
    {operator:'or',type:'time',conditions:[conditions[0]]},{type:'date',date:'2026-04-01',extra:true}
])assert.throws(()=>validateBackgroundRule(bad),/BACKGROUND_RULE_INVALID/);
assert.deepEqual(validateBackgroundRule({type:'date',date:'2024-02-29'}),{type:'date',date:'2024-02-29'});
assert.throws(()=>validateBackgroundRule({type:'weather',values:Array(10000).fill('clear')}),/BACKGROUND_RULE_INVALID/);
let deep=conditions[0];for(let i=0;i<13;i++)deep={operator:'and',conditions:[deep]};assert.throws(()=>validateBackgroundRule(deep),/BACKGROUND_RULE_INVALID/);
assert.throws(()=>validateBackgroundRule({operator:'and',conditions:Array(100).fill(conditions[0])}),/BACKGROUND_RULE_INVALID/);
const copy=validateBackgroundRule(combined);copy.conditions[0].start='00:00';assert.equal(combined.conditions[0].start,'08:00');assert.equal(validateBackgroundRule(null),null);
console.log('Background rules: all 11 condition types, nested AND/OR, priority, invalid ranges/dates/types, depth/count/size bounds and immutable drafts passed.');
