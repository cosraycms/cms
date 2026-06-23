import type { LayoutLoad } from './$types';

import '../styles/main.css';
import { setup } from '$lib/sys';

export const ssr = false;

export const load: LayoutLoad = async ({ fetch, url }) => {
	const system = await setup(fetch, url);

	return { system };
};
