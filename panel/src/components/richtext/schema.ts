import { type MarkSpec, type NodeSpec, DOMParser, DOMSerializer, Schema } from 'prosemirror-model';

function parseTextAlign(dom: HTMLElement): string | null {
	return dom.style.textAlign || null;
}

function textAlignAttrs(textAlign: string | null): Record<string, string> {
	if (!textAlign) return {};
	return { style: `text-align: ${textAlign}` };
}

const nodes: Record<string, NodeSpec> = {
	doc: {
		content: 'block+',
	},

	paragraph: {
		content: 'inline*',
		group: 'block',
		attrs: {
			class: { default: 'default' },
			textAlign: { default: null },
		},
		parseDOM: [
			{
				tag: 'p',
				getAttrs(dom) {
					const el = dom as HTMLElement;
					return {
						class: el.getAttribute('class') || 'default',
						textAlign: parseTextAlign(el),
					};
				},
			},
		],
		toDOM(node) {
			const attrs: Record<string, string> = {};
			if (node.attrs.class && node.attrs.class !== 'default') {
				attrs.class = node.attrs.class;
			}
			Object.assign(attrs, textAlignAttrs(node.attrs.textAlign));
			return ['p', attrs, 0];
		},
	},

	heading: {
		content: 'inline*',
		group: 'block',
		defining: true,
		attrs: {
			level: { default: 1 },
			textAlign: { default: null },
		},
		parseDOM: [1, 2, 3, 4, 5, 6].map((level) => ({
			tag: `h${level}`,
			getAttrs(dom: unknown) {
				return {
					level,
					textAlign: parseTextAlign(dom as HTMLElement),
				};
			},
		})),
		toDOM(node) {
			return [`h${node.attrs.level}`, textAlignAttrs(node.attrs.textAlign), 0];
		},
	},

	bulletList: {
		content: 'listItem+',
		group: 'block',
		parseDOM: [{ tag: 'ul' }],
		toDOM() {
			return ['ul', 0];
		},
	},

	orderedList: {
		content: 'listItem+',
		group: 'block',
		attrs: {
			start: { default: 1 },
		},
		parseDOM: [
			{
				tag: 'ol',
				getAttrs(dom) {
					const el = dom as HTMLElement;
					return {
						start: el.hasAttribute('start') ? parseInt(el.getAttribute('start')!, 10) : 1,
					};
				},
			},
		],
		toDOM(node) {
			return node.attrs.start === 1 ? ['ol', 0] : (['ol', { start: node.attrs.start }, 0] as const);
		},
	},

	listItem: {
		content: 'paragraph block*',
		defining: true,
		parseDOM: [{ tag: 'li' }],
		toDOM() {
			return ['li', 0];
		},
	},

	blockquote: {
		content: 'block+',
		group: 'block',
		defining: true,
		parseDOM: [{ tag: 'blockquote' }],
		toDOM() {
			return ['blockquote', 0];
		},
	},

	codeBlock: {
		content: 'text*',
		group: 'block',
		marks: '',
		code: true,
		defining: true,
		parseDOM: [{ tag: 'pre', preserveWhitespace: 'full' as const }],
		toDOM() {
			return ['pre', ['code', 0]];
		},
	},

	horizontalRule: {
		group: 'block',
		attrs: {
			class: { default: null },
		},
		parseDOM: [
			{
				tag: 'hr',
				getAttrs(dom) {
					return {
						class: (dom as HTMLElement).getAttribute('class') || null,
					};
				},
			},
		],
		toDOM(node) {
			return node.attrs.class ? ['hr', { class: node.attrs.class }] : ['hr'];
		},
	},

	hardBreak: {
		inline: true,
		group: 'inline',
		selectable: false,
		parseDOM: [{ tag: 'br' }],
		toDOM() {
			return ['br'];
		},
	},

	image: {
		inline: true,
		group: 'inline',
		draggable: true,
		attrs: {
			uid: {},
			meta: { default: null },
		},
		parseDOM: [
			{
				tag: 'img[data-uid]',
				getAttrs(dom) {
					const uid = (dom as HTMLElement).getAttribute('data-uid');
					return uid ? { uid, meta: null } : false;
				},
			},
		],
		toDOM(node) {
			// Display resolution happens in the image node view; this
			// carrier form is what clipboard and parse round-trips see.
			return ['img', { 'data-uid': node.attrs.uid }];
		},
	},

	text: {
		group: 'inline',
	},
};

const marks: Record<string, MarkSpec> = {
	bold: {
		parseDOM: [
			{ tag: 'strong' },
			{
				tag: 'b',
				getAttrs: (dom) => (dom as HTMLElement).style.fontWeight !== 'normal' && null,
			},
			{
				style: 'font-weight=400',
				clearMark: (m) => m.type.name === 'bold',
			},
			{
				style: 'font-weight',
				getAttrs: (value) => /^(bold(er)?|[5-9]\d{2,})$/.test(value as string) && null,
			},
		],
		toDOM() {
			return ['strong', 0];
		},
	},

	italic: {
		parseDOM: [
			{ tag: 'em' },
			{
				tag: 'i',
				getAttrs: (dom) => (dom as HTMLElement).style.fontStyle !== 'normal' && null,
			},
			{ style: 'font-style=italic' },
		],
		toDOM() {
			return ['em', 0];
		},
	},

	underline: {
		parseDOM: [
			{ tag: 'u' },
			{
				style: 'text-decoration',
				getAttrs: (value) => (value as string).includes('underline') && null,
			},
		],
		toDOM() {
			return ['u', 0];
		},
	},

	strike: {
		parseDOM: [
			{ tag: 's' },
			{ tag: 'del' },
			{ tag: 'strike' },
			{
				style: 'text-decoration',
				getAttrs: (value) => (value as string).includes('line-through') && null,
			},
		],
		toDOM() {
			return ['s', 0];
		},
	},

	code: {
		parseDOM: [{ tag: 'code' }],
		toDOM() {
			return ['code', 0];
		},
	},

	link: {
		// Exactly one of href/node/asset carries the target; `rel` is a
		// render-time policy of the server renderer and never stored.
		attrs: {
			href: { default: null },
			node: { default: null },
			asset: { default: null },
			target: { default: null },
			class: { default: null },
		},
		inclusive: false,
		parseDOM: [
			{
				tag: 'a[data-node]',
				getAttrs(dom) {
					const el = dom as HTMLElement;
					return {
						node: el.getAttribute('data-node'),
						target: el.getAttribute('target') || null,
						class: el.getAttribute('class') || null,
					};
				},
			},
			{
				tag: 'a[data-asset]',
				getAttrs(dom) {
					const el = dom as HTMLElement;
					return {
						asset: el.getAttribute('data-asset'),
						target: el.getAttribute('target') || null,
						class: el.getAttribute('class') || null,
					};
				},
			},
			{
				tag: 'a[href]',
				getAttrs(dom) {
					const el = dom as HTMLElement;
					return {
						href: el.getAttribute('href'),
						target: el.getAttribute('target') || null,
						class: el.getAttribute('class') || null,
					};
				},
			},
		],
		toDOM(mark) {
			const { href, node, asset, target, class: cls } = mark.attrs;
			const attrs: Record<string, string> = {};
			if (node) {
				attrs.href = '#';
				attrs['data-node'] = node;
			} else if (asset) {
				attrs.href = '#';
				attrs['data-asset'] = asset;
			} else {
				attrs.href = href ?? '#';
			}
			if (target) attrs.target = target;
			if (cls) attrs.class = cls;
			return ['a', attrs, 0];
		},
	},

	subscript: {
		excludes: 'superscript',
		parseDOM: [{ tag: 'sub' }, { style: 'vertical-align=sub' }],
		toDOM() {
			return ['sub', 0];
		},
	},

	superscript: {
		excludes: 'subscript',
		parseDOM: [{ tag: 'sup' }, { style: 'vertical-align=super' }],
		toDOM() {
			return ['sup', 0];
		},
	},

	// Named character styles ("Text styles"): the class list comes from
	// the app's `richtext.styles` config; undeclared classes are
	// rejected on save. Replaces the old fixed fontSize ladder.
	style: {
		attrs: {
			class: {},
		},
		parseDOM: [
			{
				tag: 'span[class]',
				getAttrs(dom) {
					const cls = (dom as HTMLElement).getAttribute('class') || '';
					return cls ? { class: cls } : false;
				},
			},
		],
		toDOM(mark) {
			return ['span', { class: mark.attrs.class }, 0];
		},
	},
};

export const schema = new Schema({ nodes, marks });
export const parser = DOMParser.fromSchema(schema);
export const serializer = DOMSerializer.fromSchema(schema);
