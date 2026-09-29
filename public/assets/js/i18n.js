const boot = JSON.parse(document.getElementById('search-bootstrap').textContent);
export const presets = boot.providers;
export const t = key => boot.messages[key] ?? key;
export function node(tag, text, attributes = {}) {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value);
    return element;
}
