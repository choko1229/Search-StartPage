import {t,node} from './i18n.js';
import {backgroundConditionTypes,defaultBackgroundCondition,validateBackgroundRule} from './background-rule-core.js';

export function backgroundRuleEditor(form,changed) {
    const section=node('section',undefined,{class:'background-rule-editor'}),label=node('label',t('background_rule_enable'));
    const enabled=node('input',undefined,{type:'checkbox','aria-label':t('background_rule_enable')});label.append(enabled);
    const content=node('div'),notice=node('p','',{role:'status'});section.append(label,content,notice);form.append(section);
    let draft={operator:'and',conditions:[defaultBackgroundCondition('time')]};
    const count=row=>row.operator?1+row.conditions.reduce((sum,child)=>sum+count(child),0):1;
    function select(parent,key,value,choices,update) {
        const wrapper=node('label',t(key)),input=node('select',undefined,{'aria-label':t(key)});
        for(const [id,name] of choices)input.append(node('option',t(name),{value:id}));input.value=String(value);
        input.addEventListener('change',()=>{update(input.value);changed();});wrapper.append(input);parent.append(wrapper);return input;
    }
    function input(parent,row,key,label,type,options={}) {
        const wrapper=node('label',t(label)),control=node('input',undefined,{type,'aria-label':t(label),...options});control.value=String(row[key]??'');
        control.addEventListener('input',()=>{row[key]=type==='number'?(control.value===''?NaN:Number(control.value)):control.value;changed();});wrapper.append(control);parent.append(wrapper);
    }
    function button(parent,key,action) {
        const control=node('button',t(key),{type:'button',class:'secondary'});control.addEventListener('click',()=>{action();changed();render();});parent.append(control);return control;
    }
    function checkboxes(parent,row,key,choices) {
        const container=node('div',undefined,{class:'background-rule-checks'});parent.append(container);
        for(const [value,label] of choices){const wrapper=node('label',t(label)),control=node('input',undefined,{type:'checkbox','aria-label':t(label)});control.checked=row[key].includes(value);wrapper.append(control);container.append(wrapper);
            control.addEventListener('change',()=>{row[key]=choices.filter(([id])=>id===value?control.checked:row[key].includes(id)).map(([id])=>id);changed();});}
    }
    function draw(row,parent,path,remove=null) {
        const group=Boolean(row.operator),box=node('fieldset',undefined,{class:'background-rule-node'});
        box.append(node('legend',`${t(group?'background_rule_group':'background_condition')} ${path.join('.')}`));parent.append(box);
        if(group){
            select(box,'background_rule_operator',row.operator,[['and','background_rule_and'],['or','background_rule_or']],value=>{row.operator=value;});
            row.conditions.forEach((child,index)=>draw(child,box,[...path,index+1],()=>row.conditions.splice(index,1)));
            const actions=node('div',undefined,{class:'background-rule-actions'});box.append(actions);
            const add=button(actions,'background_rule_add',()=>row.conditions.push(defaultBackgroundCondition('time')));
            const nested=button(actions,'background_rule_add_group',()=>row.conditions.push({operator:'and',conditions:[defaultBackgroundCondition('time')]}));
            add.disabled=path.length>12||count(draft)>=100;nested.disabled=path.length>=12||count(draft)>98;
        }else {
            select(box,'background_condition_type',row.type,backgroundConditionTypes.map(type=>[type,'background_condition_'+type]),value=>{for(const key of Object.keys(row))delete row[key];Object.assign(row,defaultBackgroundCondition(value));render();});
            switch(row.type){
            case 'time':input(box,row,'start','background_time_start','time');input(box,row,'end','background_time_end','time');break;
            case 'day':checkboxes(box,row,'days',Array.from({length:7},(_,day)=>[day,'background_day_'+day]));break;
            case 'date':input(box,row,'date','background_condition_date','date');break;
            case 'period':input(box,row,'start','background_period_start','date');input(box,row,'end','background_period_end','date');break;
            case 'weather':checkboxes(box,row,'values',['clear','cloudy','fog','rain','snow','storm'].map(value=>[value,'background_weather_'+value]));break;
            case 'temperature':input(box,row,'min','background_temperature_min','number',{min:'-100',max:'100',step:'0.1'});input(box,row,'max','background_temperature_max','number',{min:'-100',max:'100',step:'0.1'});break;
            case 'season':select(box,'background_condition_season',row.value,['spring','summer','autumn','winter'].map(value=>[value,'background_season_'+value]),value=>{row.value=value;});break;
            case 'random':input(box,row,'chance','background_random_chance','number',{min:'0',max:'1',step:'0.01'});break;
            case 'login':select(box,'background_condition_login',String(row.value),[['true','background_logged_in'],['false','background_logged_out']],value=>{row.value=value==='true';});break;
            case 'device':select(box,'background_condition_device',row.value,['mobile','tablet','desktop'].map(value=>[value,'background_device_'+value]),value=>{row.value=value;});break;
            case 'screen':for(const axis of ['Width','Height'])for(const bound of ['min','max'])input(box,row,bound+axis,'background_screen_'+bound+axis,'number',{min:'0',max:'100000',step:'1'});break;
            }
        }
        if(remove)button(box,'background_rule_remove',remove);
    }
    function render(){content.replaceChildren();draw(draft,content,[1]);content.querySelector('fieldset').disabled=!enabled.checked;notice.textContent=enabled.checked?t('background_rule_priority'):'';}
    enabled.addEventListener('change',()=>{changed();render();});render();
    return {value:()=>enabled.checked?validateBackgroundRule(draft):null,
        set:rule=>{const valid=validateBackgroundRule(rule);draft=valid===null?{operator:'and',conditions:[defaultBackgroundCondition('time')]}:valid.type?{operator:'and',conditions:[valid]}:valid;enabled.checked=rule!==null;render();}};
}
