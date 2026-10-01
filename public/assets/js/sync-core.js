const missing = Symbol('missing');
const forbidden = new Set(['__proto__', 'prototype', 'constructor']);
const object = value => value !== missing && value !== null && typeof value === 'object' && !Array.isArray(value);
function equal(a, b) {
    if (a === missing || b === missing) return a === b;
    if (Object.is(a, b)) return true;
    if (Array.isArray(a) && Array.isArray(b)) return a.length === b.length && a.every((v, i) => equal(v, b[i]));
    if (object(a) && object(b)) {
        const keys = Object.keys(a);
        return keys.length === Object.keys(b).length && keys.every(key => Object.hasOwn(b, key) && equal(a[key], b[key]));
    }
    return false;
}
function copy(value) {
    if (value === missing) return missing;
    if (Array.isArray(value)) return value.map(copy);
    if (object(value)) {
        const result = {};
        for (const key of Object.keys(value)) {
            if (forbidden.has(key)) throw new Error('invalid_sync_key');
            result[key] = copy(value[key]);
        }
        return result;
    }
    return value;
}
const read = (value, key) => object(value) && Object.hasOwn(value, key) ? value[key] : missing;
const choiceValue = value => ({present: value !== missing, value: value === missing ? null : copy(value)});

// Collections use ID-keyed maps before merging, so distinct records and fields
// merge independently. Arrays inside records (for example tags) are one field.
export function mergeSync(previous, local, cloud, rules = {}) {
    const conflicts = [];
    function merge(base, left, right, path) {
        if (equal(left, right)) return copy(left);
        if (equal(left, base)) return copy(right);
        if (equal(right, base)) return copy(left);
        if (object(left) && object(right) && (object(base) || base === missing)) {
            const result = {};
            const keys = new Set([...Object.keys(base === missing ? {} : base), ...Object.keys(left), ...Object.keys(right)]);
            for (const key of keys) {
                if (forbidden.has(key)) throw new Error('invalid_sync_key');
                const value = merge(read(base, key), read(left, key), read(right, key), [...path, key]);
                if (value !== missing) result[key] = value;
            }
            return result;
        }
        const id = JSON.stringify(path);
        if (rules[id] === 'local') return copy(left);
        if (rules[id] === 'cloud') return copy(right);
        conflicts.push({id, path, previous: choiceValue(base), local: choiceValue(left), cloud: choiceValue(right)});
        return copy(left);
    }
    return {data: merge(previous, local, cloud, []), conflicts};
}

export function resolveSync(previous, local, cloud, choices, rules = {}) {
    const first = mergeSync(previous, local, cloud, rules);
    for (const conflict of first.conflicts) {
        if (!['local', 'cloud'].includes(choices[conflict.id])) throw new Error('unresolved_sync_conflict');
    }
    return mergeSync(previous, local, cloud, {...rules, ...choices}).data;
}

export function collectionMap(rows) {
    const result = {};
    if (!Array.isArray(rows)) throw new Error('invalid_sync_collection');
    for (const row of rows) {
        if (!object(row) || typeof row.id !== 'string' || !row.id || forbidden.has(row.id) || Object.hasOwn(result, row.id)) throw new Error('invalid_sync_id');
        result[row.id] = copy(row);
    }
    return result;
}
