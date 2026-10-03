export const backgroundConditionTypes=['time','day','date','period','weather','temperature','season','random','login','device','screen'];
const invalid=()=>{throw new Error('BACKGROUND_RULE_INVALID');};
const number=(value,min,max)=>{if(typeof value!=='number'||!Number.isFinite(value)||value<min||value>max)invalid();};
function date(value) {
    if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))invalid();
    const [year,month,day]=value.split('-').map(Number),check=new Date(0);check.setUTCHours(0,0,0,0);check.setUTCFullYear(year,month-1,day);
    if(year<1||check.getUTCFullYear()!==year||check.getUTCMonth()!==month-1||check.getUTCDate()!==day)invalid();
}
export function validateBackgroundRule(rule) {
    if(rule===null)return null;
    let nodes=0;
    function visit(row,depth) {
        if(!row||typeof row!=='object'||Array.isArray(row)||depth>12||++nodes>100)invalid();
        if(Object.hasOwn(row,'operator')) {
            if(!['and','or'].includes(row.operator)||Object.keys(row).some(key=>!['operator','conditions'].includes(key))||!Array.isArray(row.conditions)||!row.conditions.length)invalid();
            for(const child of row.conditions)visit(child,depth+1);return;
        }
        if(!backgroundConditionTypes.includes(row.type))invalid();
        const keys={time:['start','end'],day:['days'],date:['date'],period:['start','end'],weather:['values'],temperature:['min','max'],season:['value'],random:['chance'],login:['value'],device:['value'],screen:['minWidth','maxWidth','minHeight','maxHeight']}[row.type];
        if(!keys||Object.keys(row).some(key=>!['type',...keys].includes(key)))invalid();
        switch(row.type){
        case 'time':if(![row.start,row.end].every(value=>typeof value==='string'&&/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)))invalid();break;
        case 'day':if(!Array.isArray(row.days)||!row.days.length||row.days.some(value=>!Number.isInteger(value)||value<0||value>6))invalid();break;
        case 'date':date(row.date);break;
        case 'period':date(row.start);date(row.end);if(row.start>row.end)invalid();break;
        case 'weather':if(!Array.isArray(row.values)||!row.values.length||row.values.some(value=>!['clear','cloudy','fog','rain','snow','storm'].includes(value)))invalid();break;
        case 'temperature':number(row.min,-100,100);number(row.max,-100,100);if(row.min>row.max)invalid();break;
        case 'season':if(!['spring','summer','autumn','winter'].includes(row.value))invalid();break;
        case 'random':number(row.chance,0,1);break;
        case 'login':if(typeof row.value!=='boolean')invalid();break;
        case 'device':if(!['mobile','tablet','desktop'].includes(row.value))invalid();break;
        case 'screen':for(const axis of ['Width','Height']){const min=row['min'+axis]??0,max=row['max'+axis]??100000;number(min,0,100000);number(max,0,100000);if(min>max)invalid();}break;
        }
    }
    visit(rule,0);if(new TextEncoder().encode(JSON.stringify(rule)).length>32768)invalid();return structuredClone(rule);
}
export function defaultBackgroundCondition(type,now=new Date()) {
    const today=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    const values={time:{start:'08:00',end:'18:00'},day:{days:[1,2,3,4,5]},date:{date:today},period:{start:today,end:today},weather:{values:['clear']},temperature:{min:0,max:30},season:{value:'spring'},random:{chance:.5},login:{value:true},device:{value:'desktop'},screen:{minWidth:0,maxWidth:1920,minHeight:0,maxHeight:1080}};
    if(!Object.hasOwn(values,type))invalid();return {type,...structuredClone(values[type])};
}
