/** @import { NodeType } from 'prosemirror-model' */
/** @import { Plugin } from 'prosemirror-state' */

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

/** @returns {Plugin} */
export function buildKeymap() {
	const { bold, italic, strike, code } = schema.marks;
	const { listItem, hardBreak } = schema.nodes;

	const hardBreakCmd = chainCommands(exitCode, (state, dispatch) => {
		if (!dispatch) return false;
		dispatch(state.tr.replaceSelectionWith(schema.nodes.hardBreak.create()).scrollIntoView());
		return true;
	});

	return keymap({
		'Mod-b': toggleMark(bold),
		'Mod-B': toggleMark(bold),
		'Mod-i': toggleMark(italic),
		'Mod-I': toggleMark(italic),
		'Mod-Shift-x': toggleMark(strike),
		'Mod-Shift-X': toggleMark(strike),
		'Mod-e': toggleMark(code),
		'Mod-E': toggleMark(code),
		'Mod-Shift-7': toggleOrderedList(),
		'Mod-Shift-8': toggleBulletList(),
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

/** @returns {Plugin} */
export function buildInputRules() {
	return inputRules({
		rules: [
			headingRule(1),
			headingRule(2),
			headingRule(3),

			wrappingInputRule(/^\s*([-*])\s$/, schema.nodes.bulletList),

			wrappingInputRule(
				/^\s*(\d+)\.\s$/,
				schema.nodes.orderedList,
				(match) => ({ start: +match[1] }),
				(match, node) => node.childCount + node.attrs.start === +match[1],
			),

			wrappingInputRule(/^\s*>\s$/, schema.nodes.blockquote),
			textblockTypeInputRule(/^```$/, schema.nodes.codeBlock),
			horizontalRuleInputRule(),
		],
	});
}
