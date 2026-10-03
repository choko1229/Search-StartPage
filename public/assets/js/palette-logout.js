import * as store from './store.js';
import {request} from './sync-api.js';
import {createPaletteStorage} from './palette-storage.js';
import {logoutFromPalette,recoverPaletteLogout} from './palette-logout-core.js';
const io={request,...createPaletteStorage(store)};
export const logoutPaletteAccount=()=>logoutFromPalette(io);
export const recoverPaletteAccount=()=>recoverPaletteLogout(io);
