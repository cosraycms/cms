import { afterEach, describe, expect, it } from 'vitest';
import { icon } from '../../src/lib/icons';
import { configureRuntime } from '../../src/lib/runtime';

afterEach(() => {
	configureRuntime({ assetsBase: '/panel/assets/dev/' });
});

describe('panel icons', () => {
	it('renders decorative scalable artwork from the served sprite', () => {
		configureRuntime({ assetsBase: '/cp/assets/0123456789ab' });
		const host = document.createElement('div');
		host.innerHTML = icon('plus');
		const svg = host.querySelector('svg')!;
		expect(svg.getAttribute('aria-hidden')).toBe('true');
		expect(svg.getAttribute('focusable')).toBe('false');
		expect(svg.getAttribute('viewBox')).toBe('0 0 16 16');
		expect(svg.getAttribute('fill')).toBe('currentColor');
		expect(svg.classList.contains('cms-icon')).toBe(true);
		expect(svg.querySelector('use')?.getAttribute('href')).toBe(
			'/cp/assets/0123456789ab/icons.svg#plus',
		);
	});

	it.each(['../icons/plus', '/etc/passwd', 'plus" onload="alert(1)', 'bi:plus', '__proto__', ''])(
		'never treats %s as a path, markup, or provider request',
		(name) => expect(icon(name)).toBe(''),
	);
});
