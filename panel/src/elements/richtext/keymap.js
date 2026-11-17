/** @import { NodeType } from 'prosemirror-model' */
/** @import { Plugin } from 'prosemirror-state' */
/** @import { Vocabulary } from './vocabulary.js' */

import { keymap } from 'prosemirror-keymap';
import {
	chainCommands,
	exitCode,
	joinDown,
	joinUp,
	selectParentNode,
	toggleMark,
} from 'prosemirror-commands';
import { liftListItem, sinkListItem, splitListItem } from 'prosemirror-schema-list';
import { undo, redo } from 'prosemirror-history';
import {
	inputRules,
	wrappingInputRule,
	textblockTypeInputRule,
	InputRule,
} from 'prosemirror-inputrules';

import { toggleBulletList, toggleOrderedList } from './commands.js';
import { schema } from './schema.js';

/**
 * @param {number} level
 * @returns {InputRule}
 */
function headingRule(level) {
	const pattern = new RegExp(`^(#{${level}})\\s$`);
	return textblockTypeInputRule(pattern, schema.nodes.heading, () => ({
		level,
	}));
}

/** @returns {InputRule} */
function horizontalRuleInputRule() {
	return new InputRule(/^---$/, (state, _match, start, end) => {
		return state.tr.delete(start, end).insert(start, schema.nodes.horizontalRule.create());
	});
}

/**
 * The shortcuts of what the field offers, and the ones every field has.
 *
 * @param {Vocabulary} v
 * @returns {Plugin}
 */
export function buildKeymap(v) {
	const { bold, italic, strike } = schema.marks;
	const { listItem } = schema.nodes;

	const hardBreakCmd = chainCommands(exitCode, (state, dispatch) => {
		if (!dispatch) return false;
		dispatch(state.tr.replaceSelectionWith(schema.nodes.hardBreak.create()).scrollIntoView());
		return true;
	});

	return keymap({
		...(v.marks.has('bold') ? { 'Mod-b': toggleMark(bold), 'Mod-B': toggleMark(bold) } : {}),
		...(v.marks.has('italic') ? { 'Mod-i': toggleMark(italic), 'Mod-I': toggleMark(italic) } : {}),
		...(v.marks.has('strike')
			? { 'Mod-Shift-x': toggleMark(strike), 'Mod-Shift-X': toggleMark(strike) }
			: {}),
		...(v.nodes.has('orderedList') ? { 'Mod-Shift-7': toggleOrderedList() } : {}),
		...(v.nodes.has('bulletList') ? { 'Mod-Shift-8': toggleBulletList() } : {}),
		'Mod-z': undo,
		'Mod-y': redo,
		'Mod-Shift-z': redo,
		'Shift-Enter': hardBreakCmd,
		Enter: splitListItem(listItem),
		Tab: sinkListItem(listItem),
		'Shift-Tab': liftListItem(listItem),
		'Mod-[': liftListItem(listItem),
		'Mod-]': sinkListItem(listItem),
		Backspace: chainCommands(liftListItem(listItem)),
		'Alt-ArrowUp': joinUp,
		'Alt-ArrowDown': joinDown,
		Escape: selectParentNode,
	});
}

/**
 * Markdown-like typing for what the field offers; none without any.
 *
 * @param {Vocabulary} v
 * @returns {Plugin | null}
 */
export function buildInputRules(v) {
	const rules = [
		...v.headings.map(headingRule),
		...(v.nodes.has('bulletList')
			? [wrappingInputRule(/^\s*([-*])\s$/, schema.nodes.bulletList)]
			: []),
		...(v.nodes.has('orderedList')
			? [
					wrappingInputRule(
						/^\s*(\d+)\.\s$/,
						schema.nodes.orderedList,
						(match) => ({ start: +match[1] }),
						(match, node) => node.childCount + node.attrs.start === +match[1],
					),
				]
			: []),
		...(v.nodes.has('blockquote') ? [wrappingInputRule(/^\s*>\s$/, schema.nodes.blockquote)] : []),
		...(v.nodes.has('horizontalRule') ? [horizontalRuleInputRule()] : []),
	];

	return rules.length > 0 ? inputRules({ rules }) : null;
}
