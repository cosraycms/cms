import { Translator } from './translator.js';
let active = null;
let fallback = null;
/**
 * Wires the global functions to a translator. With no translator active,
 * lookups return the message id (with interpolation), which keeps
 * translation calls safe during SSR, in tests, and before boot.
 */
export function activate(translator) {
    active = translator;
}
export function deactivate() {
    active = null;
}
export function translator() {
    return active;
}
/**
 * Reads the JSON payload inlined in the given script element and wraps it in
 * a Translator. Returns null without a DOM (SSR), when the element is
 * missing or empty, or when its content is not valid JSON.
 */
export function load(elementId = 'verba-catalog') {
    const el = typeof document === 'undefined' ? null : document.getElementById(elementId);
    if (!el?.textContent) {
        return null;
    }
    try {
        return new Translator(JSON.parse(el.textContent));
    }
    catch {
        return null;
    }
}
export function loadAndActivate(elementId) {
    const loaded = load(elementId);
    if (loaded) {
        activate(loaded);
    }
    return loaded;
}
/** Translate a message through the active domain cascade. */
export function __(id, args = {}) {
    return current().translate(id, args);
}
/** Translate a contextual message through the active domain cascade. */
export function __p(context, id, args = {}) {
    return current().translateContext(context, id, args);
}
/** Translate a pluralized message, choosing the form for n. */
export function __n(one, many, n, args = {}) {
    return current().translatePlural(one, many, n, args);
}
/** Translate a contextual pluralized message, choosing the form for n. */
export function __np(context, one, many, n, args = {}) {
    return current().translateContextPlural(context, one, many, n, args);
}
/** Translate a message from a specific domain. */
export function __d(domain, id, args = {}) {
    return current().translateDomain(domain, id, args);
}
/** Translate a contextual message from a specific domain. */
export function __dp(domain, context, id, args = {}) {
    return current().translateDomainContext(domain, context, id, args);
}
/** Translate a pluralized message from a specific domain. */
export function __dn(domain, one, many, n, args = {}) {
    return current().translateDomainPlural(domain, one, many, n, args);
}
/** Translate a contextual pluralized message from a specific domain. */
export function __dnp(domain, context, one, many, n, args = {}) {
    return current().translateDomainContextPlural(domain, context, one, many, n, args);
}
function current() {
    return active ?? (fallback ??= new Translator());
}
