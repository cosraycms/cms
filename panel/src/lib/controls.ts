import Blocks from '$shell/controls/Blocks.svelte';
import Checkbox from '$shell/controls/Checkbox.svelte';
import Code from '$shell/controls/Code.svelte';
import Date from '$shell/controls/Date.svelte';
import Datetime from '$shell/controls/Datetime.svelte';
import Element from '$shell/controls/Element.svelte';
import Entries from '$shell/controls/Entries.svelte';
import File from '$shell/controls/File.svelte';
import Group from '$shell/controls/Group.svelte';
import Hidden from '$shell/controls/Hidden.svelte';
import Iframe from '$shell/controls/Iframe.svelte';
import Image from '$shell/controls/Image.svelte';
import Number from '$shell/controls/Number.svelte';
import Option from '$shell/controls/Option.svelte';
import Repeater from '$shell/controls/Repeater.svelte';
import Text from '$shell/controls/Text.svelte';
import Textarea from '$shell/controls/Textarea.svelte';
import Time from '$shell/controls/Time.svelte';
import Video from '$shell/controls/Video.svelte';

/**
 * The fixed control vocabulary. Fields reference these by name via
 * their server-side control descriptor; the panel has no knowledge of
 * field type classes. Plugins extend the UI through the `element`
 * control, not by adding entries here.
 */
export default {
	blocks: Blocks,
	checkbox: Checkbox,
	code: Code,
	date: Date,
	datetime: Datetime,
	element: Element,
	entries: Entries,
	file: File,
	group: Group,
	hidden: Hidden,
	iframe: Iframe,
	image: Image,
	number: Number,
	option: Option,
	repeater: Repeater,
	text: Text,
	textarea: Textarea,
	time: Time,
	video: Video,
};
