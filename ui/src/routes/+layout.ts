import type { LayoutLoad } from './$types';

import '../styles/main.css';
import { goto } from '$app/navigation';
import { configureRuntime } from '$lib/runtime';
import { setup } from '$lib/sys';

export const ssr = false;

export const load: LayoutLoad = async ({ fetch, url }) => {
	configureRuntime({
		navigate: (href, options) => goto(href, options),
	});

	const system = await setup(fetch, url);

	return { system };
};
