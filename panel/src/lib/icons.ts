const assets = import.meta.glob('../../icons/*.svg', {
	query: '?raw',
	import: 'default',
	eager: true,
}) as Record<string, string>;

export function icon(name: string): string {
	const svg = assets[`../../icons/${name}.svg`];
	return svg
		? svg
				.replace('<svg ', '<svg aria-hidden="true" focusable="false" ')
				.replace('class="bi ', 'class="cms-icon bi ')
		: '';
}
