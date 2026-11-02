// The building blocks the media controls render from: a file row, the
// image card, the block figures, the fallback preview and the small menus
// and mirrors they share.

/** @import { AssetInfo, FileItem, LocaleMap, RequiredItem, RichtextDoc, UploadType } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */

import { ZXX } from '../../lib/content.js';
import { filled, resolveTextFallback } from '../../lib/fallback.js';
import { icon } from '../../lib/icons.js';
import { showPart } from '../../lib/image-geometry.js';
import { docNodes, docText } from '../../lib/inline-doc.js';
import { assetLine, extension, fileIcon, humanSize } from '../../lib/library.js';
import { __ } from '../../lib/locale.js';

let ids = 0;

/**
 * @param {string} tag
 * @param {string} [className]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
export function create(tag, className, text) {
	const element = document.createElement(tag);

	if (className) element.className = className;
	if (text !== undefined) element.textContent = text;

	return element;
}

/**
 * @param {string} name
 * @returns {Element}
 */
export function svg(name) {
	const template = document.createElement('template');
	template.innerHTML = icon(name);

	return /** @type {Element} */ (template.content.firstElementChild);
}

/**
 * An image from a rendition, or from the original when the rendition fails to
 * load.
 *
 * @param {string} source
 * @param {string} [original]
 * @returns {HTMLImageElement}
 */
export function rendition(source, original) {
	const image = document.createElement('img');
	image.src = source;
	image.alt = '';

	if (original && original !== source) {
		image.addEventListener('error', () => (image.src = original), { once: true });
	}

	return image;
}

/**
 * An image as its use shows it: the crop's part of it in a box of that shape.
 *
 * @param {HTMLImageElement} image
 * @param {FileItem} item
 * @param {AssetInfo | undefined} info
 * @returns {HTMLElement}
 */
export function cropped(image, item, info) {
	const crop = item.meta?.crop;

	if (!crop) return image;

	const box = create('div', 'cms-crop-window');
	const ratio = info?.width && info.height ? info.width / info.height : 1;
	box.style.setProperty('--crop-ratio', String((crop.w * ratio) / crop.h));
	showPart(box, image, crop);
	box.append(image);

	return box;
}

/**
 * @param {string} className
 * @param {string} label
 * @param {() => void} onclick
 * @param {string} [iconName] Makes an icon button named by `label`.
 * @returns {HTMLButtonElement}
 */
export function button(className, label, onclick, iconName) {
	const element = document.createElement('button');
	element.type = 'button';
	element.className = className;

	if (iconName) {
		element.title = label;
		element.setAttribute('aria-label', label);
		element.append(svg(iconName));
	} else {
		element.textContent = label;
	}

	element.addEventListener('click', onclick);

	return element;
}

/**
 * A button with an icon and a visible label.
 *
 * @param {string} className
 * @param {string} label
 * @param {() => void} onclick
 * @param {string} iconName
 * @returns {HTMLButtonElement}
 */
function labelled(className, label, onclick, iconName) {
	const element = button(className, '', onclick);
	element.append(svg(iconName), ' ', label);

	return element;
}

/**
 * A mirror of the screen's content-language selector for a dialog or slot
 * that holds translated texts: choosing here switches the whole screen. The
 * request travels as an event; the content-locales behavior resolves it.
 *
 * @param {{ id: string, title: string }[]} locales
 * @param {string} locale
 * @returns {{ element: HTMLElement, setLocale: (locale: string) => void }}
 */
export function contentLocales(locales, locale) {
	const id = `cms-media-locales-${++ids}`;
	const element = create('div', 'cms-content-language');
	/** @param {string} chosen */
	const choose = (chosen) =>
		control.dispatchEvent(
			new CustomEvent('content-locale:select', { bubbles: true, detail: { locale: chosen } }),
		);
	/** @type {HTMLElement} */
	let control;

	if (locales.length < 4) {
		const label = create('span', 'cms-sub-label', __('editor:content-language'));
		label.id = `${id}-label`;
		control = create('div', 'cms-content-locales');
		control.setAttribute('role', 'group');
		control.setAttribute('aria-labelledby', label.id);

		for (const entry of locales) {
			const option = button('option', entry.title, () => choose(entry.id));
			option.dataset.locale = entry.id;
			control.append(option);
		}

		element.append(label, control);
	} else {
		const label = document.createElement('label');
		label.className = 'cms-sub-label';
		label.htmlFor = id;
		label.textContent = __('editor:content-language');
		const select = document.createElement('select');
		select.className = 'cms-select';
		select.id = id;

		for (const entry of locales) {
			select.append(new Option(entry.title, entry.id));
		}

		select.addEventListener('change', () => choose(select.value));
		control = select;
		element.append(label, select);
	}

	/** @param {string} next */
	function setLocale(next) {
		if (control instanceof HTMLSelectElement) {
			control.value = next;
			return;
		}

		for (const option of control.querySelectorAll('button')) {
			option.setAttribute('aria-pressed', String(option.dataset.locale === next));
		}
	}

	setLocale(locale);

	return { element, setLocale };
}

/**
 * The menu a filled single field replaces its file through.
 *
 * @param {() => void} upload
 * @param {() => void} library
 * @param {boolean} [quiet] A figure's hover bar draws its own quiet trigger.
 * @param {'start' | 'end'} [align]
 * @returns {HTMLElement[]}
 */
export function replaceMenu(upload, library, quiet = false, align = 'end') {
	const id = `cms-replace-${++ids}`;
	const trigger = create('button', quiet ? 'replace quiet' : 'replace cms-button secondary small');
	trigger.setAttribute('type', 'button');
	trigger.setAttribute('popovertarget', id);
	trigger.setAttribute('aria-haspopup', 'menu');
	trigger.append(__('media:replace'), ' ', svg('chevron-down'));
	const menu = create('div', 'cms-action-menu cms-replace-menu');
	menu.id = id;
	menu.setAttribute('popover', 'auto');
	menu.dataset.actionMenu = '';
	menu.dataset.align = align;

	/**
	 * @param {string} name
	 * @param {string} label
	 * @param {string} hint
	 * @param {() => void} onclick
	 */
	const choice = (name, label, hint, onclick) => {
		const entry = document.createElement('button');
		entry.type = 'button';
		const text = create('span', 'choice');
		text.append(label, ' ', create('small', undefined, hint));
		entry.append(svg(name), ' ', text);
		entry.addEventListener('click', onclick);
		menu.append(entry);
	};

	choice('folder2-open', __('media:browse'), __('media:browse-hint'), library);
	choice('cloud-upload', __('upload:from-device'), __('upload:from-device-hint'), upload);

	return [trigger, menu];
}

/**
 * @typedef {object} FileRowOptions
 * @property {FileItem} item
 * @property {AssetInfo | undefined} info
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {boolean} [loading]
 * @property {boolean} [inert] Shown, not editable, as in a fallback preview or a read-only field.
 * @property {() => void} [edit]
 * @property {() => void} [remove]
 * @property {{ upload: () => void, library: () => void }} [replace] A single field replaces its
 *     file from the row.
 */

/**
 * @param {FileRowOptions} options
 * @returns {HTMLElement}
 */
export function fileRow({
	item,
	info,
	translate,
	contentLocale,
	loading = false,
	inert = false,
	edit,
	remove,
	replace,
}) {
	const row = create('div', 'cms-file-row');
	const filename = info?.filename ?? item.uid ?? '';
	const thumb = info?.kind === 'image' ? (info.thumbUrl ?? info.url) : '';
	const iconBox = create('span', 'icon');

	if (thumb) {
		const image = rendition(thumb, info?.url);
		image.loading = 'lazy';
		iconBox.append(image);
	} else {
		iconBox.append(svg(fileIcon({ filename, mime: info?.mime })));
	}

	const name = create('span', 'name');

	if (info?.url) {
		const link = document.createElement('a');
		link.className = 'filename';
		link.href = info.url;
		link.target = '_blank';
		link.rel = 'noopener';
		link.title = filename;
		link.textContent = filename;
		name.append(link);
	} else {
		const label = create('span', 'filename', filename);
		label.title = filename;
		name.append(label);
	}

	const size = create(
		'span',
		'size',
		loading ? __('upload:uploading') : typeof info?.bytes === 'number' ? humanSize(info.bytes) : '',
	);
	row.append(iconBox, name, size);
	retitle(row, item, translate, contentLocale);

	if (!inert) {
		if (edit) row.append(button('tool edit', __('common:edit'), edit, 'pencil'));

		if (replace) {
			row.append(...replaceMenu(replace.upload, replace.library));
		}

		row.append(button('tool remove', __('common:remove'), () => remove?.(), 'x-lg'));
	}

	return row;
}

/**
 * Shows the title a row's file carries in a language, else its neutral one,
 * next to the file name. A language switch updates it in place, so the
 * row's buttons keep focus.
 *
 * @param {Element} row
 * @param {FileItem} item
 * @param {boolean} translate
 * @param {string} contentLocale
 */
export function retitle(row, item, translate, contentLocale) {
	const name = row.querySelector('.name');
	/** @type {LocaleMap<string> | undefined} */
	const titles = item.meta?.title;
	const title = titles?.[translate ? contentLocale : ZXX] || titles?.[ZXX] || '';
	let shown = name?.querySelector('.title');

	if (!title) {
		shown?.remove();

		return;
	}

	if (!shown) {
		shown = create('span', 'title');
		name?.append(shown);
	}

	shown.textContent = title;
	/** @type {HTMLElement} */ (shown).title = title;
}

/**
 * What another language holds, shown read-only above an empty field.
 *
 * @param {FileItem[]} items
 * @param {UploadType} type
 * @param {string} label
 * @param {(uid: string) => AssetInfo | undefined} asset
 * @param {boolean} translate
 * @param {string} contentLocale
 * @returns {HTMLElement}
 */
export function fallbackMedia(items, type, label, asset, translate, contentLocale) {
	const root = create('div', 'cms-media-fallback');
	root.setAttribute('aria-label', label);
	root.append(create('div', 'label', label));

	if (type === 'image') {
		const images = create('div', 'images');

		for (const item of items) {
			const info = asset(item.uid ?? '');
			const filename = info?.filename ?? item.uid ?? '';
			const source = info?.thumbUrl ?? info?.url ?? '';
			const box = create('div', 'image');
			box.title = filename;

			if (source) {
				const image = rendition(source, info?.url);
				image.loading = 'lazy';
				box.append(image);
			} else {
				box.append(create('span', undefined, extension(filename)));
			}

			images.append(box);
		}

		root.append(images);
	} else if (type === 'video') {
		for (const item of items) {
			const video = document.createElement('video');
			video.controls = true;
			video.preload = 'metadata';
			const track = document.createElement('track');
			track.kind = 'captions';
			const source = document.createElement('source');
			source.src = asset(item.uid ?? '')?.url ?? '';
			video.append(track, source);
			root.append(video);
		}
	} else {
		const files = create('div', 'files');

		for (const item of items) {
			files.append(
				fileRow({ item, info: asset(item.uid ?? ''), translate, contentLocale, inert: true }),
			);
		}

		root.append(files);
	}

	return root;
}

/**
 * @param {AssetInfo | undefined} info
 * @param {AssetInfo | undefined} [poster]
 * @returns {HTMLElement}
 */
export function videoPlayer(info, poster) {
	const box = create('div', 'cms-video');
	const video = document.createElement('video');
	video.controls = true;
	video.preload = 'metadata';
	video.className = 'cms-video-player';
	video.src = info?.url ?? '';
	video.poster = poster?.previewUrl ?? poster?.url ?? '';
	box.append(video);

	return box;
}

/**
 * The single image of a form field: a picture that opens its details, its
 * facts, and the actions on it.
 *
 * @param {object} options
 * @param {FileItem} options.item
 * @param {AssetInfo | undefined} options.info
 * @param {boolean} options.loading
 * @param {boolean} options.readonly
 * @param {() => void} options.edit
 * @param {() => void} options.remove
 * @param {() => void} options.upload
 * @param {() => void} options.library
 * @param {('alt' | 'caption' | 'attribution')[]} options.offered The texts the field offers.
 * @param {HTMLElement | null} options.link Where the image links to, when it does.
 * @param {RequiredItem[]} options.required What the field requires of each image.
 * @param {boolean} options.translate
 * @param {string} options.contentLocale
 * @param {ControlLocales | undefined} options.locales
 * @returns {{ element: HTMLElement, setLocale: (locale: string) => void }}
 */
export function imageCard({
	item,
	info,
	loading,
	readonly,
	edit,
	remove,
	upload,
	library,
	offered,
	link,
	required,
	translate,
	contentLocale,
	locales,
}) {
	const card = create('div', 'cms-image-card');
	card.classList.toggle('is-readonly', readonly);
	const filename = info?.filename ?? item.uid ?? '';
	const thumb = info?.thumbUrl ?? info?.url ?? '';
	const facts = info ? assetLine(info).split(' · ') : [];
	// A pointer shortcut; the edit button is the way there for the keyboard.
	const picture = create('div', 'thumb');
	picture.addEventListener('click', edit);

	if (info?.width && info.height) {
		const crop = item.meta?.crop ?? { w: 1, h: 1 };
		picture.style.setProperty(
			'--thumb-ratio',
			String((info.width * crop.w) / (info.height * crop.h)),
		);
	}

	if (thumb) {
		picture.append(cropped(rendition(thumb, info?.url), item, info));
	} else {
		picture.append(create('span', 'plate', extension(filename)));
	}

	const details = create('div', 'details');
	const name = create('span', 'filename', filename);
	name.title = filename;
	const factLine = create('span', 'facts');

	if (loading) {
		factLine.textContent = __('upload:uploading');
	} else {
		for (const [index, fact] of facts.entries()) {
			factLine.append(
				create('span', undefined, `${fact}${index < facts.length - 1 ? ' ·' : ''}`),
				' ',
			);
		}
	}

	const texts = create('dl', 'texts');
	const controls = create('div', 'controls');

	/**
	 * @param {string} label
	 * @param {...(Node | string)} content
	 */
	const text = (label, ...content) => {
		const row = document.createElement('div');
		const value = document.createElement('dd');
		value.append(...content);
		row.append(create('dt', undefined, `${label}:`), value);
		texts.append(row);

		return value;
	};

	/** @param {string} locale */
	const showTexts = (locale) => {
		texts.replaceChildren();

		if (loading || !info) return;

		const alt = docText(effectiveText('alt', item, info, translate, locale, locales));
		const caption = offered.includes('caption')
			? effectiveText('caption', item, info, translate, locale, locales)
			: '';
		const attribution = offered.includes('attribution') ? effectiveAttribution(item, info) : '';

		const add = () =>
			button('add', __('image:alt-text-add'), () => {
				// The link is gone once the text is there, so the dialog returns focus to the edit button.
				/** @type {HTMLElement | null} */ (controls.querySelector('.edit'))?.focus();
				edit();
			});

		if (alt) {
			text(__('image:alt-text'), alt).title = alt;
		} else if (!readonly) {
			text(__('image:alt-text'), add());
		}

		if (docText(caption)) {
			text(__('image:caption'), ...docNodes(caption)).title = docText(caption);
		} else if (!readonly && required.includes('caption')) {
			text(__('image:caption'), add());
		}

		if (attribution) {
			text(__('image:attribution'), attribution).title = attribution;
		} else if (!readonly && required.includes('attribution')) {
			text(__('image:attribution'), add());
		}

		if (link) {
			text(__('image:link'), link);
		} else if (!readonly && required.includes('link')) {
			text(__('image:link'), add());
		}
	};

	showTexts(contentLocale);

	if (readonly) {
		controls.append(labelled('cms-button secondary small', __('image:details'), edit, 'eye'));
	} else {
		controls.append(
			...replaceMenu(upload, library, false, 'start'),
			labelled('edit cms-button secondary small', __('common:edit'), edit, 'pencil'),
			labelled('discard cms-button secondary small', __('common:remove'), remove, 'x-lg'),
		);
	}

	details.append(name, factLine, texts, controls);
	card.append(picture, details);

	return { element: card, setLocale: showTexts };
}

/**
 * What an item lacks of what its field requires: the check the server makes
 * when saving, where a translated text has to exist in the default language.
 *
 * @param {FileItem} item
 * @param {AssetInfo | undefined} info
 * @param {RequiredItem[]} required
 * @param {ControlLocales | undefined} locales
 * @returns {string[]} Their labels.
 */
export function missingItems(item, info, required, locales) {
	const main = locales?.default ?? '';
	/** @param {unknown} value */
	const has = (value) => docText(/** @type {string | RichtextDoc} */ (value ?? '')).trim() !== '';
	const labels = {
		alt: __('image:alt-text'),
		caption: __('image:caption'),
		attribution: __('image:attribution'),
		link: __('image:link'),
	};

	return required
		.filter((name) => {
			if (name === 'link') return !item.meta?.link;
			if (name === 'attribution') {
				return !has(item.meta?.attribution) && !has(info?.meta?.attribution);
			}

			const own = /** @type {LocaleMap<unknown> | undefined} */ (item.meta?.[name]);
			const catalog = /** @type {LocaleMap<unknown> | undefined} */ (info?.meta?.[name]);

			return !has(own?.[main]) && !has(own?.[ZXX]) && !has(catalog?.[main]);
		})
		.map((name) => labels[name]);
}

/**
 * A text of an image as the site will render it: per use, else the catalog's.
 *
 * @param {'alt' | 'caption'} name
 * @param {FileItem} item
 * @param {AssetInfo | undefined} info
 * @param {boolean} translate
 * @param {string} contentLocale
 * @param {ControlLocales | undefined} locales
 * @returns {string | RichtextDoc} A caption can be a document.
 */
export function effectiveText(name, item, info, translate, contentLocale, locales) {
	const key = translate ? contentLocale : ZXX;
	// A neutral value reads its catalog text in the site's default locale.
	const catalogLocale = translate ? contentLocale : (locales?.default ?? contentLocale);
	const own = /** @type {LocaleMap<string | RichtextDoc> | undefined} */ (item.meta?.[name]);

	if (filled(own?.[key])) {
		return own?.[key] ?? '';
	}

	return (
		resolveTextFallback(
			own,
			/** @type {LocaleMap<string | RichtextDoc> | undefined} */ (info?.meta?.[name]),
			key,
			locales?.all ?? [],
			catalogLocale,
		)?.value ?? ''
	);
}

/**
 * Who made an image as the site will show it: per use, else the catalog's.
 *
 * @param {FileItem} item
 * @param {AssetInfo | undefined} info
 * @returns {string}
 */
export function effectiveAttribution(item, info) {
	return item.meta?.attribution || info?.meta?.attribution || '';
}

/**
 * An image or video as a block shows it: the media at block width with a
 * hover bar of the actions on it, and an image's caption below.
 *
 * @param {object} options
 * @param {'image' | 'video'} options.type
 * @param {AssetInfo | undefined} options.info
 * @param {FileItem} options.item
 * @param {string} options.filename
 * @param {string | RichtextDoc} options.caption
 * @param {string} options.attribution
 * @param {boolean} options.loading
 * @param {boolean} options.readonly
 * @param {() => void} [options.edit] Opens the details, which a read-only image offers too.
 * @param {() => void} options.remove
 * @param {() => void} options.upload
 * @param {() => void} options.library
 * @returns {{ element: HTMLElement, setCaption: (caption: string | RichtextDoc) => void }}
 */
export function figure({
	type,
	info,
	item,
	filename,
	caption,
	attribution,
	loading,
	readonly,
	edit,
	remove,
	upload,
	library,
}) {
	const root = create('div', type === 'image' ? 'cms-image-figure' : 'cms-video-figure');
	const shape = document.createElement('figure');
	const frame = create('div', 'frame');

	if (type === 'video') {
		const video = document.createElement('video');
		video.controls = true;
		video.preload = 'metadata';
		video.src = info?.url ?? '';
		frame.append(video);
	} else {
		const src = info?.previewUrl ?? info?.url ?? '';

		if (src) {
			frame.append(cropped(rendition(src, info?.url), item, info));
		} else {
			frame.append(create('span', 'plate', extension(filename)));
		}
	}

	// Along the top edge: a player's own controls own the bottom.
	const overlay = create('div', 'overlay');
	const name = create('span', 'filename', loading ? __('upload:uploading') : filename);
	name.title = filename;
	overlay.append(name);

	if (!readonly) {
		overlay.append(...replaceMenu(upload, library, true));
	}

	if (edit) {
		overlay.append(button('quiet edit', readonly ? __('image:details') : __('common:edit'), edit));
	}

	if (!readonly) {
		overlay.append(button('quiet discard', __('common:remove'), remove));
	}

	frame.append(overlay);
	shape.append(frame);
	const figcaption = document.createElement('figcaption');
	root.append(shape);

	/** @param {string | RichtextDoc} text */
	function setCaption(text) {
		const written = docText(text) !== '';
		figcaption.replaceChildren(...(written ? docNodes(text) : []));

		if (attribution) figcaption.append(written ? ' ' : '', create('small', undefined, attribution));

		if ((written || attribution) && type === 'image') shape.append(figcaption);
		else figcaption.remove();
	}

	setCaption(caption);

	return { element: root, setCaption };
}
