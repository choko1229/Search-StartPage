import {setting,saveSettings} from './store.js';
import {t,node} from './i18n.js';
import {requestRegion} from './region-core.js';
import {createRegionSettings} from './region-editor.js';
export function regionSettings(panel) {
    return createRegionSettings(panel,{readRegion:()=>setting('themeRegion',null),saveRegion:value=>saveSettings({themeRegion:value},'appearance'),locateRegion:requestRegion,t,node});
}