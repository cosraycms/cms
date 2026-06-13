import { goto } from '$app/navigation';
import { browser, dev } from '$app/environment';
import { get as getStore } from 'svelte/store';
import { system } from '$lib/sys';

const domain = browser ? `${window.location.protocol}/${window.location.host}` : '';
export const base = getBase();

class Response {
	constructor(
		public ok: boolean,
		public data: any,
	) {}
}

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE';
type Headers = {
	'X-Requested-With': 'xmlhttprequest';
	Accept: string;
	'X-CSRF-Token'?: string;
};

export function getBase() {
	if (browser) {
		return window.COSRAY_BASE_PATH;
	}

	return '/cms/';
}

function getDefaultOptions(): RequestInit {
	const headers: Headers = {
		'X-Requested-With': 'xmlhttprequest',
		Accept: 'application/json',
	};

	const $system = getStore(system);

	if ($system) {
		headers['X-CSRF-Token'] = $system.csrfToken;
	}

	return {
		headers,
		mode: 'cors',
		cache: 'no-cache',
		credentials: 'same-origin',
		redirect: 'follow',
		referrerPolicy: 'no-referrer',
	};
}

function getBodyOptions(method: Method, data?: any) {
	const options = Object.assign(getDefaultOptions(), { method });

	if (data) {
		if (!(data instanceof FormData)) {
			options.body = JSON.stringify(data);
			options.headers['Content-Type'] = 'application/json';
		} else {
			options.body = data;
		}
	}

	return options;
}

async function fetchit(
	path: string,
	params: Record<string, string>,
	options: RequestInit,
	fetchFn: typeof window.fetch | null,
) {
	const url = path.startsWith('/')
		? new URL(path, domain)
		: new URL(`${base}api/${path}`, domain);

	if (fetchFn === null) {
		fetchFn = window.fetch;
	}

	if (params) {
		// dynamically append GET params when value is set
		Object.keys(params).forEach(key => {
			if (params[key]) url.searchParams.append(key, params[key]);
		});
	}
	const response = await fetchFn(url.href, options);

	if (response.status >= 400 && response.status < 800) {
		let message: any;

		try {
			message = await response.json();

			if (response.status === 401) {
				if (message?.loginType !== 'token') {
					goto(`${base}login`);
				}

				return null;
			}

			// The user logged out in another tab and logged in again.
			// Now the csrf token is invalid.
			if (response.status === 400 && message.error_message === 'CSRF Error') {
				window.location.reload();
			}
		} catch {
			message = { error: 'Fatal error occured' };
		}

		return new Response(false, message);
	}

	return new Response(true, await response.json());
}

async function get(
	url: string,
	params: Record<string, string>,
	fetchFn: typeof window.fetch | null = null,
) {
	const options = getBodyOptions('GET');

	return fetchit(url, params, options, fetchFn);
}

async function post(url: string, data = {}, fetchFn: typeof window.fetch | null = null) {
	const options = getBodyOptions('POST', data);

	return fetchit(url, {}, options, fetchFn);
}

async function put(url: string, data = {}, fetchFn: typeof window.fetch | null = null) {
	const options = getBodyOptions('PUT', data);

	return fetchit(url, {}, options, fetchFn);
}

async function del(url: string, fetchFn: typeof window.fetch | null = null) {
	const options = getBodyOptions('DELETE');

	return fetchit(url, {}, options, fetchFn);
}

export default {
	post,
	get,
	put,
	del,
	base,
};
