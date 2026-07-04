import type { AssetInfo, AssetMap, UploadResponse } from '$types/data';

import { getContext, setContext } from 'svelte';
import { writable, type Writable } from 'svelte/store';

export type AssetStore = Writable<AssetMap>;

const KEY = Symbol('cosray-assets');

/**
 * Element-root scope for resolved asset data. The SSR payload seeds the
 * map with everything the entry references; uploads and library picks
 * register their results so previews resolve without a reload. Takes a
 * getter so callers can hand over their reactive prop.
 */
export function provideAssets(initial: () => AssetMap = () => ({})): AssetStore {
	const store: AssetStore = writable({ ...initial() });
	setContext(KEY, store);

	return store;
}

export function useAssets(): AssetStore {
	return getContext<AssetStore | undefined>(KEY) ?? writable({});
}

export function registerAsset(store: AssetStore, uid: string, info: AssetInfo): void {
	store.update((map) => ({ ...map, [uid]: info }));
}

export function uploadInfo(item: UploadResponse, kind: string): AssetInfo {
	return {
		filename: item.filename,
		url: item.url,
		kind,
		mime: item.mime,
		width: item.width,
		height: item.height,
	};
}
