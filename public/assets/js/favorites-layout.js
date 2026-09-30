export function layoutNumber(value, fallback, min, max) {
    const number = Number(value);
    return Number.isInteger(number) && number >= min && number <= max ? number : fallback;
}
export function favoriteLayout({width, count, display = 'icon-name', columns = 0, limit = 0}) {
    const mode = display === 'auto'
        ? (width >= 700 && count <= 6 ? 'card' : count > 20 || width < 300 ? 'icon' : 'icon-name')
        : ['icon', 'icon-name', 'card'].includes(display) ? display : 'icon-name';
    const minimum = mode === 'card' ? 220 : mode === 'icon' ? 80 : 110;
    const capacity = Math.max(1, Math.floor((width + 13) / (minimum + 13)));
    const requested = layoutNumber(columns, 0, 0, 12);
    const actualColumns = requested ? Math.min(requested, capacity) : capacity;
    const requestedLimit = layoutNumber(limit, 0, 0, 1000);
    return {mode, columns: actualColumns, limit: requestedLimit || Math.min(10, actualColumns * 2)};
}
