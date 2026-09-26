import { interpolate } from './interpolate.js';
import { pluralRule } from './plurals.js';
/**
 * Resolves messages for one locale across an ordered cascade of domains —
 * the JavaScript mirror of the PHP Translator, fed by the payload that
 * `Translator::exportMany()` produces. The first entry whose catalog holds
 * a translation wins; a miss falls back to the message id itself. A domain
 * may appear once per locale of the PHP-side fallback chain, each entry
 * carrying its own plural rule, so walking entries in payload order
 * resolves the same chain the PHP runtime does.
 */
export class Translator {
    locale;
    domains;
    constructor(payload = {}) {
        this.locale = payload.locale ?? 'en';
        this.domains = (payload.domains ?? []).map((entry) => ({
            name: entry.domain,
            messages: readMessages(entry.messages),
            contexts: readContexts(entry.contexts),
            rule: pluralRule(entry.plural ?? this.locale),
        }));
    }
    translate(id, args = {}) {
        return this.translateFrom(null, id, args);
    }
    translateContext(context, id, args = {}) {
        return this.translateFrom(context, id, args);
    }
    translateDomain(name, id, args = {}) {
        return this.translateDomainFrom(name, null, id, args);
    }
    translateDomainContext(name, context, id, args = {}) {
        return this.translateDomainFrom(name, context, id, args);
    }
    translatePlural(one, many, n, args = {}) {
        return this.translatePluralFrom(null, one, many, n, args);
    }
    translateContextPlural(context, one, many, n, args = {}) {
        return this.translatePluralFrom(context, one, many, n, args);
    }
    translateDomainPlural(name, one, many, n, args = {}) {
        return this.translateDomainPluralFrom(name, null, one, many, n, args);
    }
    translateDomainContextPlural(name, context, one, many, n, args = {}) {
        return this.translateDomainPluralFrom(name, context, one, many, n, args);
    }
    translateFrom(context, id, args) {
        for (const domain of this.domains) {
            const entry = messageFrom(domain, context, id);
            if (typeof entry === 'string') {
                return interpolate(entry, args);
            }
        }
        return interpolate(id, args);
    }
    translateDomainFrom(name, context, id, args) {
        for (const domain of this.domains) {
            if (domain.name !== name) {
                continue;
            }
            const entry = messageFrom(domain, context, id);
            if (typeof entry === 'string') {
                return interpolate(entry, args);
            }
        }
        return interpolate(id, args);
    }
    translatePluralFrom(context, one, many, n, args) {
        for (const domain of this.domains) {
            const form = pluralFrom(domain, context, one, n, args);
            if (form !== null) {
                return form;
            }
        }
        return interpolate(n === 1 ? one : many, pluralArgs(args, n));
    }
    translateDomainPluralFrom(name, context, one, many, n, args) {
        for (const domain of this.domains) {
            if (domain.name !== name) {
                continue;
            }
            const form = pluralFrom(domain, context, one, n, args);
            if (form !== null) {
                return form;
            }
        }
        return interpolate(n === 1 ? one : many, pluralArgs(args, n));
    }
}
/** PHP encodes an empty message map as a JSON array, so lists read as empty. */
function readMessages(messages) {
    return messages === undefined || Array.isArray(messages) ? {} : messages;
}
function readContexts(contexts) {
    if (contexts === undefined || Array.isArray(contexts)) {
        return {};
    }
    return Object.fromEntries(Object.entries(contexts).map(([context, messages]) => [context, readMessages(messages)]));
}
function messageFrom(domain, context, id) {
    return context === null ? domain.messages[id] : domain.contexts[context]?.[id];
}
/** An empty form list counts as untranslated, like a missing id — mirrors PHP. */
function pluralFrom(domain, context, one, n, args) {
    const entry = messageFrom(domain, context, one);
    if (Array.isArray(entry) && entry.length > 0) {
        const form = entry[domain.rule(n)] ?? entry[entry.length - 1];
        return interpolate(form, pluralArgs(args, n));
    }
    if (typeof entry === 'string') {
        return interpolate(entry, pluralArgs(args, n));
    }
    return null;
}
/** Binds `:count` to the count unless the caller already set it. */
function pluralArgs(args, n) {
    return 'count' in args ? args : { ...args, count: n };
}
