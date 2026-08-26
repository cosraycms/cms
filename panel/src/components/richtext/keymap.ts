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
import type { NodeType } from 'prosemirror-model';
import type { Plugin } from 'prosemirror-state';
import { schema } from './schema';

function headingRule(level: number): InputRule {
	const pattern = new RegExp(`^(#{${level}})\\s$`);
	return textblockTypeInputRule(pattern, schema.nodes.heading, () => ({
		level,
	}));
}

function horizontalRuleInputRule(): InputRule {
	return new InputRule(/^---$/, (state, _match, start, end) => {
		return state.tr.delete(start, end).insert(start, schema.nodes.horizontalRule.create());
	});
}

export function buildKeymap(): Plugin {
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

export function buildInputRules(): Plugin {
	return inputRules({
		rules: [
			// Headings: # , ## , ###
			headingRule(1),
			headingRule(2),
			headingRule(3),

			// Bullet list: - or *
			wrappingInputRule(/^\s*([-*])\s$/, schema.nodes.bulletList),

			// Ordered list: 1.
			wrappingInputRule(
				/^\s*(\d+)\.\s$/,
				schema.nodes.orderedList,
				(match) => ({ start: +match[1] }),
				(match, node) => node.childCount + node.attrs.start === +match[1],
			),

			// Blockquote: >
			wrappingInputRule(/^\s*>\s$/, schema.nodes.blockquote),

			// Code block: ```
			textblockTypeInputRule(/^```$/, schema.nodes.codeBlock),

			// Horizontal rule: ---
			horizontalRuleInputRule(),
		],
	});
}
