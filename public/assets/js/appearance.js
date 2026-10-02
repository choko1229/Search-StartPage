import {get,setting,setSetting} from './store.js';
import {t,node} from './i18n.js';
import {palettes,color,bounded,fontUrl,fontName,themePalette,greetingKey,searchStyles,widgetStyle,headerPreferences,headerItems} from './appearance-core.js';
import {syncUser} from './sync-api.js';

export function initializeAppearance() {
    const appearance=document.getElementById('settings-appearance'),general=document.getElementById('settings-general');
    const root=document.documentElement,os=matchMedia('(prefers-color-scheme: dark)'),reduced=matchMedia('(prefers-reduced-motion: reduce)');
    const controls=[];
    function field(panel,key,label,type,fallback,options={}) {
        const wrapper=node('label',t(label),{class:'preference'});
        const input=node(type==='select'?'select':'input',undefined,{'aria-label':t(label),...(type==='select'?{}:{type}),...options});
        if(type==='select')for(const [value,text] of options.choices || [])input.append(node('option',t(text),{value}));
        input.removeAttribute('choices');
        input.dataset.historyKey=key;
        const legend=panel.querySelector(':scope > legend');if(legend)input.dataset.historyLabel=`${legend.textContent}: ${t(label)}`;
        let pending=false;
        const read=force=>{if(!force && (pending || document.activeElement===input && ['text','url','number'].includes(type)))return;const value=setting(key,typeof fallback==='function'?fallback():fallback);if(type==='checkbox')input.checked=value===true;else input.value=String(value);};
        read();controls.push(read);
        let inputTimer;
        const save=()=>{
            clearTimeout(inputTimer);pending=false;
            if(!input.validity.valid){read(true);return;}
            if(key==='customFontUrl' && input.value && !fontUrl(input.value)){read(true);return;}
            if(key==='googleFont' && !fontName(input.value)){read(true);return;}
            setSetting(key,type==='checkbox'?input.checked:type==='number'?Number(input.value):input.value);
        };
        input.addEventListener('change',save);
        if(type==='color')input.addEventListener('input',save);
        if(['text','url','number'].includes(type)){
            input.addEventListener('input',()=>{pending=true;clearTimeout(inputTimer);inputTimer=setTimeout(save,250);});
            input.addEventListener('blur',()=>{if(pending)save();});
        }
        wrapper.append(input);panel.append(wrapper);return input;
    }
    field(appearance,'theme','appearance_theme','select','solar',{choices:[['solar','theme_solar'],['light','theme_light'],['dark','theme_dark'],['os','theme_os'],['forest','theme_forest'],['rose','theme_rose'],['custom','theme_custom']]});
    field(appearance,'themeTransition','appearance_transition','number',0.75,{min:'0',max:'5',step:'0.05'});
    const region=node('fieldset'),regionLabel=node('legend',t('appearance_region'));region.append(regionLabel);
    const latitude=node('input',undefined,{type:'number',min:'-90',max:'90',step:'any','aria-label':t('latitude')});
    const longitude=node('input',undefined,{type:'number',min:'-180',max:'180',step:'any','aria-label':t('longitude')});
    let regionDirty=false;
    for(const [input,key] of [[latitude,'latitude'],[longitude,'longitude']]){input.value=setting('themeRegion',{})[key]??'';const label=node('label',t(key));label.append(input);region.append(label);}
    region.append(node('p',t('appearance_region_help'),{class:'muted'}));appearance.append(region);
    const readRegion=()=>{if(regionDirty)return;const value=setting('themeRegion',{});latitude.value=value.latitude??'';longitude.value=value.longitude??'';};controls.push(readRegion);
    const saveRegion=()=>{if(regionDirty && latitude.value && longitude.value && latitude.validity.valid && longitude.validity.valid){regionDirty=false;region.dataset.pending='false';setSetting('themeRegion',{latitude:Number(latitude.value),longitude:Number(longitude.value)});}};
    for(const input of [latitude,longitude]){
        input.addEventListener('input',()=>{regionDirty=true;region.dataset.pending='true';});
        input.addEventListener('change',saveRegion);input.addEventListener('blur',saveRegion);
    }
    document.getElementById('search-settings').addEventListener('settings-discard',()=>{regionDirty=false;region.dataset.pending='false';readRegion();});
    const custom=node('fieldset');custom.append(node('legend',t('theme_custom')));
    const saved=node('select',undefined,{'aria-label':t('custom_theme_saved')}),name=node('input',undefined,{'aria-label':t('custom_theme_name'),maxlength:'80'});
    const colors={};
    for(const key of Object.keys(palettes.light)){const label=node('label',t('palette_'+key)),input=node('input',undefined,{type:'color',value:palettes.light[key]});colors[key]=input;label.append(input);custom.append(label);}
    const save=node('button',t('custom_theme_save'),{type:'button'});custom.prepend(saved,name);custom.append(save);appearance.append(custom);
    let draftNew=false,editorId=null;
    function loadEditor(id){const theme=setting('customThemes',{})[id];name.value=theme?.name || '';for(const key of Object.keys(colors))colors[key].value=color(theme?.[key],palettes.light[key]);editorId=id;custom.dataset.pending='false';}
    function renderThemes(){saved.replaceChildren(node('option',t('custom_theme_new'),{value:''}));for(const [id,theme] of Object.entries(setting('customThemes',{})))if(typeof theme?.name==='string')saved.append(node('option',theme.name,{value:id}));const id=draftNew?'':setting('customThemeId','');saved.value=id;if(editorId!==id)loadEditor(id);}
    saved.addEventListener('change',()=>{const id=saved.value;draftNew=id==='';loadEditor(id);if(id){setSetting('customThemeId',id);setSetting('theme','custom');}});
    for(const input of [name,...Object.values(colors)])input.addEventListener('input',()=>{custom.dataset.pending='true';});
    save.addEventListener('click',()=>{
        if(!name.value.trim())return;
        const id=draftNew || !saved.value?crypto.randomUUID():saved.value;
        const theme={name:name.value.trim(),...Object.fromEntries(Object.entries(colors).map(([key,input])=>[key,input.value]))};
        draftNew=false;custom.dataset.pending='false';
        setSetting('customThemes',{...setting('customThemes',{}),[id]:theme});setSetting('customThemeId',id);setSetting('theme','custom');loadEditor(id);
    });
    document.getElementById('search-settings').addEventListener('settings-discard',()=>{draftNew=false;loadEditor(setting('customThemeId',''));renderThemes();});renderThemes();
    field(appearance,'fontMode','appearance_font','select','system',{choices:[['system','font_system'],['serif','font_serif'],['mono','font_mono'],['google','font_google'],['custom','font_custom']]});
    field(appearance,'googleFont','google_font_name','text','Noto Sans',{maxlength:'120'});
    field(appearance,'customFontUrl','custom_font_url','url','',{maxlength:'2048'});
    const fontStatus=node('p','',{role:'status'});appearance.append(fontStatus);
    appearance.append(node('p',t('external_font_help'),{class:'muted'}));
    for(const [key,label,fallback,min,max,step] of [['fontSize','font_size',16,10,32,1],['fontWeight','font_weight',400,100,900,100],['lineHeight','font_line_height',1.6,1,2.5,0.1],['letterSpacing','font_spacing',0,-2,6,0.1]])field(appearance,key,label,'number',fallback,{min:String(min),max:String(max),step:String(step)});
    field(appearance,'animationLevel','appearance_animation','select','rich',{choices:[['none','animation_none'],['low','animation_low'],['standard','animation_standard'],['rich','animation_rich']]});
    const searchDesign=node('fieldset');searchDesign.append(node('legend',t('search_design')));appearance.append(searchDesign);
    field(searchDesign,'searchPosition','search_position','select','upper',{choices:[['upper','position_upper'],['center','position_center'],['lower','position_lower']]});
    field(searchDesign,'searchWidthMode','search_width_mode','select','responsive',{choices:[['responsive','width_responsive'],['fixed','width_fixed']]});
    field(searchDesign,'searchWidth','search_width','number',700,{min:'320',max:'900',step:'1'});
    field(searchDesign,'searchHeight','search_height','select','standard',{choices:[['compact','height_compact'],['standard','height_standard'],['large','height_large']]});
    for(const [key,label,fallback,min,max,step] of [['searchOpacity','search_opacity',.7,0,1,.05],['searchBlur','search_blur',12,0,40,1],['searchBorderWidth','search_border_width',1,0,8,1],['searchRadius','search_radius',16,0,60,1]])field(searchDesign,key,label,'number',fallback,{min:String(min),max:String(max),step:String(step)});
    for(const [key,label,paletteKey] of [['searchBackground','search_background','panel'],['searchBorder','search_border','border'],['searchText','search_text','text'],['searchPlaceholder','search_placeholder','muted']])field(searchDesign,key,label,'color',()=>themePalette(get('settings',{}),new Date(),os.matches)[paletteKey]);
    field(searchDesign,'searchShadow','search_shadow','checkbox',true);
    for(const [key,label,fallback] of [['greetingEnabled','greeting_enabled',true],['clockEnabled','clock_enabled',false],['dateEnabled','date_enabled',false],['clockSeconds','clock_seconds',false],['dateWeekday','date_weekday',true]])field(general,key,label,'checkbox',fallback);
    field(general,'greetingMessage','greeting_message','text','',{maxlength:'200'});
    field(general,'clockFormat','clock_format','select','24',{choices:[['24','clock_24'],['12','clock_12']]});
    field(general,'dateFormat','date_format','select','long',{choices:[['long','date_long'],['short','date_short'],['iso','date_iso']]});
    for(const prefix of ['clock','date']) {
        const group=node('fieldset');group.append(node('legend',t(prefix+'_design')));general.append(group);
        field(group,prefix+'Position','display_position','select','above',{choices:[['above','display_above'],['below','display_below'],['top-left','display_top_left'],['top-right','display_top_right'],['bottom-left','display_bottom_left'],['bottom-right','display_bottom_right']]});
        field(group,prefix+'Size','display_size','number',prefix==='clock'?48:16,{min:'10',max:'120',step:'1'});
        field(group,prefix+'Font','appearance_font','select','inherit',{choices:[['inherit','font_inherit'],['system','font_system'],['serif','font_serif'],['mono','font_mono']]});
        field(group,prefix+'Color','display_color','color',()=>{const p=themePalette(get('settings',{}),new Date(),os.matches);return prefix==='clock'?p.text:p.muted;});
        field(group,prefix+'Opacity','display_opacity','number',1,{min:'0',max:'1',step:'.05'});
    }
    const header=document.querySelector('.site-header'),headerGroup=node('fieldset');headerGroup.append(node('legend',t('header_design')));general.append(headerGroup);
    field(headerGroup,'headerPosition','header_position','select','top',{choices:[['top','header_top'],['bottom','header_bottom']]});
    field(headerGroup,'headerAlignment','header_alignment','select','right',{choices:[['left','align_left'],['center','align_center'],['right','align_right']]});
    field(headerGroup,'headerSize','display_size','number',16,{min:'10',max:'32',step:'1'});
    field(headerGroup,'headerOpacity','display_opacity','number',1,{min:'0',max:'1',step:'.05'});
    field(headerGroup,'headerBackground','search_background','color',()=>themePalette(get('settings',{}),new Date(),os.matches).background);
    field(headerGroup,'headerBlur','search_blur','number',0,{min:'0',max:'40',step:'1'});
    const headerList=node('ol',undefined,{class:'header-items','aria-label':t('header_items')});headerGroup.append(headerList);
    let headerSignature='';
    function renderHeaderItems() {
        const preferences=headerPreferences(get('settings',{}),palettes.light);
        const signature=JSON.stringify([preferences.order,preferences.visible]);if(signature===headerSignature)return;headerSignature=signature;
        const focused=document.activeElement?.getAttribute('aria-label');headerList.replaceChildren();
        preferences.order.forEach((item,index)=>{
            const row=node('li'),label=node('label',t('header_item_'+item)),toggle=node('input',undefined,{type:'checkbox','aria-label':t('header_item_'+item)});toggle.checked=preferences.visible[item];
            toggle.addEventListener('change',()=>setSetting('headerVisibility',{...setting('headerVisibility',{}),[item]:toggle.checked}));label.prepend(toggle);row.append(label);
            for(const [direction,delta] of [['up',-1],['down',1]]) {
                const button=node('button',t('order_'+direction),{type:'button',class:'secondary','aria-label':`${t('header_item_'+item)}: ${t('order_'+direction)}`});button.disabled=index+delta<0 || index+delta>=preferences.order.length;
                button.addEventListener('click',()=>{const order=[...preferences.order];[order[index],order[index+delta]]=[order[index+delta],order[index]];setSetting('headerOrder',order);});row.append(button);
            }
            headerList.append(row);
        });
        if(focused){const target=[...headerList.querySelectorAll('[aria-label]')].find(element=>element.getAttribute('aria-label')===focused);target?.focus();}
    }
    const display=node('section',undefined,{class:'home-display','aria-label':t('home_display')});
    const greeting=node('p','',{class:'greeting'}),clock=node('time','',{class:'clock'}),date=node('time','',{class:'date'});display.append(greeting,clock,date);document.querySelector('.search-home').prepend(display);
    const belowDisplay=node('section',undefined,{class:'home-display home-display-below','aria-label':t('home_display')});document.querySelector('.search-home').append(belowDisplay);
    let username='',fontSignature='',customFont=null,googleLink=null;
    syncUser().then(user=>{username=user?.discord_display_name || user?.discord_username || '';header.querySelector('[data-header-item=account]').textContent=user?t('header_profile'):t('header_login');renderDisplay();}).catch(()=>{});
    function renderDisplay() {
        const now=new Date(),locale=document.documentElement.lang;
        greeting.hidden=!setting('greetingEnabled',true);greeting.textContent=setting('greetingMessage','') || `${t(greetingKey(now.getHours()))}${username?`${t('greeting_separator')}${username}`:''}`;
        clock.hidden=!setting('clockEnabled',false);clock.dateTime=now.toISOString();clock.textContent=new Intl.DateTimeFormat(locale,{hour:'2-digit',minute:'2-digit',...(setting('clockSeconds',false)?{second:'2-digit'}:{}),hour12:setting('clockFormat','24')==='12'}).format(now);
        date.hidden=!setting('dateEnabled',false);date.dateTime=now.toISOString();date.textContent=setting('dateFormat','long')==='iso'?`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`:new Intl.DateTimeFormat(locale,{year:'numeric',month:setting('dateFormat','long')==='short'?'2-digit':'long',day:'numeric',...(setting('dateWeekday',true)?{weekday:'long'}:{})}).format(now);
    }
    function apply() {
        const settings=get('settings',{}),palette=themePalette(settings,new Date(),os.matches);
        for(const [key,value] of Object.entries(palette))root.style.setProperty('--'+key,value);
        for(const [key,value] of Object.entries(searchStyles(settings,palette)))root.style.setProperty(key,value);
        for(const [element,prefix] of [[clock,'clock'],[date,'date']]) {
            const style=widgetStyle(settings,prefix,palette);element.dataset.position=style.position;
            element.style.fontSize=style.size;element.style.fontFamily=style.font;element.style.color=style.color;element.style.opacity=style.opacity;
            (style.position==='below'?belowDisplay:display).append(element);
        }
        date.style.setProperty('--display-offset',setting('clockEnabled',false) && clock.dataset.position===date.dataset.position?`calc(${clock.style.fontSize} * ${bounded(settings.lineHeight,1.6,1,2.5)})`:'0px');
        const headerStyle=headerPreferences(settings,palette);header.classList.add('custom-header');header.dataset.position=headerStyle.position;header.dataset.alignment=headerStyle.alignment;
        header.style.fontSize=headerStyle.size;header.style.opacity=headerStyle.opacity;header.style.backgroundColor=headerStyle.background;header.style.backdropFilter=`blur(${headerStyle.blur})`;
        for(const [index,item] of headerStyle.order.entries()){const element=header.querySelector(`[data-header-item=${item}]`);if(element){element.hidden=!headerStyle.visible[item];element.style.order=index;}}
        document.body.classList.toggle('header-at-bottom',headerStyle.position==='bottom');renderHeaderItems();
        root.style.color=palette.text;root.style.backgroundColor=palette.background;
        root.style.setProperty('--button-text',palette.background);root.style.colorScheme=['dark','forest'].includes(settings.theme) || palette.background===palettes.dark.background?'dark':'light';
        root.style.setProperty('--theme-duration',`${reduced.matches?0:bounded(settings.themeTransition,0.75,0,5)}s`);
        root.style.fontSize=`${bounded(settings.fontSize,16,10,32)}px`;root.style.fontWeight=bounded(settings.fontWeight,400,100,900);root.style.lineHeight=bounded(settings.lineHeight,1.6,1,2.5);root.style.letterSpacing=`${bounded(settings.letterSpacing,0,-2,6)}px`;
        root.dataset.animation=reduced.matches?'none':(['none','low','standard','rich'].includes(settings.animationLevel)?settings.animationLevel:'rich');
        const signature=JSON.stringify([settings.fontMode,settings.googleFont,settings.customFontUrl]);
        if(signature!==fontSignature) {
            fontSignature=signature;if(customFont)document.fonts.delete(customFont);customFont=null;googleLink?.remove();googleLink=null;
            fontStatus.textContent='';
            let family='system-ui, -apple-system, Segoe UI, sans-serif';
            if(settings.fontMode==='serif')family='Georgia, Times New Roman, serif';
            if(settings.fontMode==='mono')family='ui-monospace, Consolas, monospace';
            if(settings.fontMode==='google' && fontName(settings.googleFont || 'Noto Sans')) {
                const name=settings.googleFont || 'Noto Sans';googleLink=node('link',undefined,{rel:'stylesheet',href:`https://fonts.googleapis.com/css2?family=${encodeURIComponent(name)}&display=swap`});const link=googleLink;
                fontStatus.textContent=t('font_loading');
                const failed=()=>{if(googleLink===link){fontStatus.textContent=t('font_failed');root.style.fontFamily='system-ui, sans-serif';}};
                link.addEventListener('error',failed);
                link.addEventListener('load',()=>{document.fonts.load(`16px "${name}"`,'Aa').then(fonts=>{if(googleLink===link){if(fonts.length)fontStatus.textContent=t('font_loaded');else failed();}}).catch(failed);});
                document.head.append(link);family=`${name}, sans-serif`;
            }
            if(settings.fontMode==='custom' && fontUrl(settings.customFontUrl)) {
                const face=new FontFace('StartpageCustom',`url(${JSON.stringify(fontUrl(settings.customFontUrl))})`);customFont=face;
                fontStatus.textContent=t('font_loading');
                face.load().then(loaded=>{if(customFont===face){document.fonts.add(loaded);root.style.fontFamily='StartpageCustom, sans-serif';fontStatus.textContent=t('font_loaded');}}).catch(()=>{if(customFont===face)fontStatus.textContent=t('font_failed');});
            }
            root.style.fontFamily=family;
        }
        renderDisplay();for(const read of controls)read();renderThemes();
    }
    window.addEventListener('data-change',event=>{if(event.detail==='settings')apply();});os.addEventListener('change',apply);reduced.addEventListener('change',apply);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)apply();});
    function tick(){if(!document.hidden)renderDisplay();setTimeout(tick,setting('clockEnabled',false)&&setting('clockSeconds',false)?1000:10000);}
    tick();
    setInterval(()=>{if(!document.hidden)apply();},60000);apply();
    document.getElementById('search-settings').dispatchEvent(new CustomEvent('settings-labels'));
}
