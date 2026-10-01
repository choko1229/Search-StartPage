const collections = ['favorites', 'favorite-folders', 'history', 'providers-web', 'providers-ai', 'backgrounds'];

// Sync records ownership only after a successful server acknowledgement.
// Unowned records (including local-only backgrounds) are never removed here.
export function removeSyncedData(state, userId) {
    const ownership = state.syncOwnership;
    if (!ownership || String(ownership.userId) !== String(userId)) return state;
    const next = {...state};
    for (const key of collections) {
        const ids = ownership.collections?.[key];
        if (!Array.isArray(ids) || !Array.isArray(state[key])) continue;
        const owned = new Set(ids);
        next[key] = state[key].filter(item => !owned.has(item.id) || (key === 'backgrounds' && item.localOnly === true));
    }
    next.settings = {...state.settings};
    for (const key of Array.isArray(ownership.settings) ? ownership.settings : []) delete next.settings[key];
    delete next.syncOwnership;
    delete next.syncStatus;
    delete next.syncCheckpoint;
    return next;
}

export function localUsage(state) {
    return {
        bytes: new TextEncoder().encode(JSON.stringify(state)).byteLength,
        lastSync: state.syncStatus?.lastSync || null,
        // Files will live in IndexedDB in Phase 7; JSON references are not file bytes.
        backgroundBytes: (Array.isArray(state.backgrounds) ? state.backgrounds : [])
            .reduce((sum, item) => sum + (Number.isSafeInteger(item.size) && item.size > 0 ? item.size : 0), 0),
    };
}
