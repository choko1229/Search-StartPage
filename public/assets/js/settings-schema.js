export const categories=['general','search','ai','favorites','background','appearance','sync','shortcuts','privacy'];
// Fixed reset lists deliberately exclude entity collections and operational state.
export const categoryKeys={
    general:['greetingEnabled','greetingMessage','clockEnabled','dateEnabled'],
    search:['initialMode','webDefault','urlPolicy','suggestOnFocus','historyArea','historyLimit','historyDays'],
    ai:['aiDefault','aiOrder'],
    favorites:['favoritePosition','favoriteWidth','favoriteHeight','favoriteGap','favoriteLimit','favoriteColumns','favoriteContextMenu','favoriteStats','favoriteSearch','favoriteSort','favoriteDisplay','folderSort','rememberFavoritesExpanded'],
    background:[],
    appearance:['theme','themeTransition','fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','animationLevel'],
    sync:['syncEnabled','syncHistory'],
    shortcuts:['webKey','aiKey','historyKey'],
    privacy:['saveHistory','externalSuggest','clearSyncedOnLogout'],
};
export const categoryOf=key=>categories.find(category=>categoryKeys[category].includes(key)) || 'general';
