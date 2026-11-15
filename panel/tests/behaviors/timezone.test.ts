import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/timezone';

let uninstall: () => void = () => {};

function browser(timeZone: string): void {
	const resolved = Intl.DateTimeFormat.prototype.resolvedOptions;
	vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockImplementation(function (
		this: Intl.DateTimeFormat,
	) {
		return { ...resolved.call(this), timeZone };
	});
}

function select(): HTMLSelectElement {
	return document.querySelector<HTMLSelectElement>('select[data-timezone]')!;
}

function button(): HTMLButtonElement {
	return document.querySelector<HTMLButtonElement>('[data-timezone-browser]')!;
}

beforeEach(() => {
	document.body.innerHTML = `
		<div class="cms-field">
			<select data-timezone name="panel_timezone">
				<option value="" data-zone="Europe/Berlin">Site default (Europe/Berlin)</option>
				<option value="Europe/Berlin">Berlin</option>
				<option value="America/New_York">New York</option>
			</select>
			<button type="button" data-timezone-browser hidden></button>
		</div>`;
});

afterEach(() => {
	uninstall();
	vi.restoreAllMocks();
	document.body.innerHTML = '';
});

describe('timezone hint', () => {
	it('offers the browser zone when it differs from the site default', () => {
		browser('America/New_York');
		uninstall = install();

		expect(button().hidden).toBe(false);
		expect(button().textContent).toContain('America/New_York');
	});

	it('stays hidden when the default already is the browser zone', () => {
		browser('Europe/Berlin');
		uninstall = install();

		expect(button().hidden).toBe(true);
	});

	it('stays hidden for a zone the select does not offer', () => {
		browser('Mars/Olympus');
		uninstall = install();

		expect(button().hidden).toBe(true);
	});

	it('chooses the browser zone and hides itself', () => {
		browser('America/New_York');
		uninstall = install();
		const changed = vi.fn();
		select().addEventListener('change', changed);

		button().click();

		expect(select().value).toBe('America/New_York');
		expect(changed).toHaveBeenCalledOnce();
		expect(button().hidden).toBe(true);
	});
});
