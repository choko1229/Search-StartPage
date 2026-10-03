import {weatherRegion} from './weather-context.js';

export function createRegionSettings(panel,{readRegion,saveRegion,locateRegion,t,node}) {
    const group=node('fieldset',undefined,{'aria-label':t('appearance_region'),class:'region-settings'});group.append(node('legend',t('appearance_region')));
    const inputs={};
    for(const [key,limit] of [['latitude',90],['longitude',180]]){
        const label=node('label',t(key)),input=node('input',undefined,{type:'number',min:String(-limit),max:String(limit),step:'any','aria-label':t(key)});
        inputs[key]=input;label.append(input);group.append(label);
    }
    const locate=node('button',t('region_locate'),{type:'button',class:'secondary'}),clear=node('button',t('region_clear'),{type:'button',class:'secondary'}),status=node('p','',{role:'status','aria-live':'polite'});
    group.append(locate,clear,status,node('p',t('region_shared_help'),{class:'muted'}));
    const credit=node('p',t('region_weather_source')+' ',{class:'muted'});credit.append(node('a','Open-Meteo',{href:'https://open-meteo.com/',target:'_blank',rel:'noopener noreferrer'}));group.append(credit);panel.append(group);
    let dirty=false,generation=0,busy=false;
    const read=()=>{if(dirty)return;const value=readRegion();for(const key of Object.keys(inputs))inputs[key].value=value?.[key]??'';};
    const stop=()=>{generation++;busy=false;locate.disabled=false;};
    const persist=async(value,expected=generation)=>{
        try{await saveRegion(value);if(expected===generation){dirty=false;group.dataset.pending='false';read();status.textContent=t(value?'region_saved':'region_cleared');}}
        catch{if(expected===generation){dirty=true;group.dataset.pending='true';status.textContent=t('storage_unavailable');}}
    };
    const save=()=>{
        if(!dirty)return;
        if(Object.values(inputs).some(input=>input.value===''||!input.validity.valid)){status.textContent=t('region_invalid');return;}
        const value=weatherRegion(Object.fromEntries(Object.entries(inputs).map(([key,input])=>[key,Number(input.value)])));
        if(!value){status.textContent=t('region_invalid');return;}persist(value);
    };
    for(const input of Object.values(inputs)){
        input.addEventListener('input',()=>{stop();dirty=true;group.dataset.pending='true';status.textContent='';});
        input.addEventListener('change',save);input.addEventListener('blur',save);
    }
    locate.addEventListener('click',async()=>{
        if(busy)return;const expected=++generation;busy=true;locate.disabled=true;status.textContent=t('region_locating');
        try{const value=await locateRegion();if(expected!==generation)return;
            dirty=true;group.dataset.pending='true';for(const key of Object.keys(inputs))inputs[key].value=value[key];await persist(value,expected);
        }catch(error){if(expected===generation)status.textContent=t(error.message);}
        finally{if(expected===generation){busy=false;locate.disabled=false;}}
    });
    clear.addEventListener('click',()=>{stop();dirty=true;group.dataset.pending='true';for(const input of Object.values(inputs))input.value='';persist(null);});
    read();
    return {read,discard:()=>{stop();dirty=false;group.dataset.pending='false';status.textContent='';read();}};
}
