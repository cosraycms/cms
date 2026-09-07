import { describe, expect, it } from 'vitest';
import { icon } from '../../src/lib/icons';

describe('bundled panel icons', () => {
	it('renders decorative scalable artwork', () => {
		const host = document.createElement('div');
		host.innerHTML = icon('plus');
		const svg = host.querySelector('svg')!;
		expect(svg.getAttribute('aria-hidden')).toBe('true');
		expect(svg.getAttribute('focusable')).toBe('false');
		expect(svg.getAttribute('viewBox')).toBe('0 0 16 16');
		expect(svg.getAttribute('fill')).toBe('currentColor');
	});

	it.each([
		'../icons/plus',
		'/etc/passwd',
		'plus" onload="alert(1)',
		'bi:plus',
		'not-a-bundled-icon',
		'__proto__',
	])('never treats %s as a path, markup, or provider request', (name) =>
		expect(icon(name)).toBe(''),
	);
});
