// JSON transport for forms marked data-json-form (the editor form).
//
// A urlencoded POST is parsed on the server under PHP's max_input_vars
// cap, which silently TRUNCATES oversized submissions — and since
// entries rows are replaced wholesale on save, a truncated submit would
// silently delete content. Marked forms are therefore re-encoded into
// one nested JSON body, which the server decodes without a per-key cap.
//
// htmx 4 finishes its own urlencoded encoding after htmx:config:request,
// so this hooks htmx:before:request — the last point before fetch()
// where the request can still be rewritten. The body is the final
// URLSearchParams there; requests without one (boosted GETs, multipart)
// pass through untouched.

import { nest } from '$lib/form-json';

interface TransportRequest {
	form?: unknown;
	body?: unknown;
	headers?: Record<string, string>;
}

function encode(event: Event): void {
	const detail = (event as CustomEvent<{ ctx?: { request?: TransportRequest } }>).detail;
	const request = detail?.ctx?.request;

	if (
		!request ||
		!(request.form instanceof HTMLFormElement) ||
		!request.form.hasAttribute('data-json-form') ||
		!(request.body instanceof URLSearchParams)
	) {
		return;
	}

	request.body = JSON.stringify(nest(request.body.entries()));
	request.headers = { ...request.headers, 'Content-Type': 'application/json' };
}

export function install(): () => void {
	document.addEventListener('htmx:before:request', encode);

	return () => document.removeEventListener('htmx:before:request', encode);
}
