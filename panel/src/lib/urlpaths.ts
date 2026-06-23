import type { Content, Node } from '$types/data';
import req from '$lib/req';

export const ROUTE_PATH_PREVIEW_DELAY = 300;

export interface RoutePathPreviewPayload {
	uid: string;
	handle: string | null;
	parent: string | null;
	content: Content;
}

export function routePathPreviewPayload(node: Node): RoutePathPreviewPayload {
	return {
		uid: node.uid,
		handle: node.handle,
		parent: node.parent ?? null,
		content: JSON.parse(JSON.stringify(node.content)) as Content,
	};
}

export function routePathPreviewSignature(type: string, payload: RoutePathPreviewPayload): string {
	return JSON.stringify({ type, payload });
}

export function hasExplicitRoutePath(node: Pick<Node, 'paths'>): boolean {
	return Object.values(node.paths ?? {}).some((path) => path.trim() !== '');
}

export function effectiveRoutePath(
	node: Pick<Node, 'paths' | 'generatedPaths'>,
	locale: string,
	defaultLocale: string,
): string | null {
	return (
		firstPath([
			node.paths?.[locale],
			node.generatedPaths?.[locale],
			node.paths?.[defaultLocale],
			node.generatedPaths?.[defaultLocale],
			...Object.values(node.paths ?? {}),
			...Object.values(node.generatedPaths ?? {}),
		]) ?? null
	);
}

export function isResolvedRoutePath(path: string): boolean {
	return !path.includes('{') && !path.includes('}');
}

export async function previewRoutePaths(
	type: string,
	payload: RoutePathPreviewPayload,
): Promise<Record<string, string> | null> {
	const response = await req.post(`node/${type}/paths`, payload);

	if (!response?.ok) {
		return null;
	}

	return response.data.paths as Record<string, string>;
}

function firstPath(paths: Array<string | undefined>): string | undefined {
	return paths.map((path) => path?.trim() ?? '').find((path) => path !== '');
}
