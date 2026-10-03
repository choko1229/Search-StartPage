export const categories=['general','search','ai','favorites','background','appearance','sync','shortcuts','privacy'];
// Fixed reset lists deliberately exclude entity collections and operational state.
export const categoryKeys={
    general:['greetingEnabled','greetingMessage','clockEnabled','dateEnabled','clockSeconds','dateWeekday','clockFormat','dateFormat',...['clock','date'].flatMap(prefix=>['Position','Size','Font','Color','Opacity'].map(name=>prefix+name)),'headerPosition','headerAlignment','headerSize','headerOpacity','headerBackground','headerBlur','headerOrder','headerVisibility'],
    search:['initialMode','webDefault','urlPolicy','suggestOnFocus','historyArea','historyLimit','historyDays'],
    ai:['aiDefault','aiOrder'],
    favorites:['favoritePosition','favoriteWidth','favoriteHeight','favoriteGap','favoriteLimit','favoriteColumns','favoriteContextMenu','favoriteStats','favoriteSearch','favoriteSort','favoriteDisplay','folderSort','rememberFavoritesExpanded'],
    background:['backgroundMode','backgroundColor','backgroundSelected','backgroundSwitch','backgroundInterval','backgroundLibrarySort'],
    appearance:['theme','themeTransition','themeRegion','customThemeId','fontMode','googleFont','customFontUrl','fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','animationLevel','searchPosition','searchWidthMode','searchWidth','searchHeight','searchBackground','searchOpacity','searchBlur','searchBorder','searchBorderWidth','searchRadius','searchShadow','searchText','searchPlaceholder'],
    sync:['syncEnabled','syncHistory'],
    shortcuts:['webKey','aiKey','historyKey'],
    privacy:['saveHistory','externalSuggest','clearSyncedOnLogout'],
};
export const categoryOf=key=>key==='customThemes'?'appearance':categories.find(category=>categoryKeys[category].includes(key)) || 'general';
