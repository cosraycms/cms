import type { Content, Node } from '$types/data';
import req from '$lib/req';

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
