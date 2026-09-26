import { assetsBase } from './runtime.js';

/**
 * A panel icon for script-built markup. It references the icon sprite PHP
 * serves next to the panel files, so no icon source has to travel with the
 * scripts; views inline their icons server-side instead.
 *
 * @param {string} name A file name from `panel/icons/` without extension.
 * @returns {string} SVG markup, or an empty string for a malformed name.
 */
export function icon(name) {
	if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name)) {
		return '';
	}

	return (
		`<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="cms-icon bi bi-${name}" viewBox="0 0 16 16">` +
		`<use href="${assetsBase()}icons.svg#${name}"></use></svg>`
	);
}
