import { describe, expect, it } from 'vitest';
import { nest } from '../../src/lib/form-json';

describe('nest', () => {
	it('keeps flat names flat', () => {
		expect(nest([['handle', 'news']])).toEqual({ handle: 'news' });
	});

	it('builds nested objects from bracket paths', () => {
		expect(nest([['content[title][value][zxx]', 'Hello']])).toEqual({
			content: { title: { value: { zxx: 'Hello' } } },
		});
	});

	it('merges siblings into one tree', () => {
		expect(
			nest([
				['content[a][value][zxx]', '1'],
				['content[b][value][zxx]', '2'],
			]),
		).toEqual({ content: { a: { value: { zxx: '1' } }, b: { value: { zxx: '2' } } } });
	});

	it('lets the last duplicate win', () => {
		expect(
			nest([
				['published', ''],
				['published', '1'],
			]),
		).toEqual({ published: '1' });
	});

	it('appends empty brackets after the highest integer key', () => {
		expect(
			nest([
				['a[5]', 'x'],
				['a[]', 'y'],
			]),
		).toEqual({ a: { '5': 'x', '6': 'y' } });
	});

	it('replaces scalars and arrays on conflicting reassignment', () => {
		expect(
			nest([
				['a', '1'],
				['a[b]', '2'],
			]),
		).toEqual({ a: { b: '2' } });
		expect(
			nest([
				['a[b]', '1'],
				['a', '2'],
			]),
		).toEqual({ a: '2' });
	});

	it('treats malformed names as literal keys', () => {
		expect(nest([['a[b', '1']])).toEqual({ 'a[b': '1' });
		expect(nest([['a[b]c', '1']])).toEqual({ 'a[b]c': '1' });
	});

	it('accepts URLSearchParams entries', () => {
		const params = new URLSearchParams();
		params.append('content[title][value][zxx]', 'Hello');
		params.append('_complete', '1');

		expect(nest(params.entries())).toEqual({
			content: { title: { value: { zxx: 'Hello' } } },
			_complete: '1',
		});
	});
});
