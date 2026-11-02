import { expect, test } from '@playwright/test';
import {
	colorAt,
	COVER,
	field,
	GALLERY,
	openDetails,
	openNode,
	publish,
	save,
	STRICT,
	upload,
} from './support';

test('a tall image shows whole in its details', async ({ page }) => {
	await openNode(page, 'focal');
	await upload(field(page, COVER), 'portrait.jpg');
	const dialog = await openDetails(field(page, COVER), page);
	const picture = dialog.locator('.stage .frame img').first();
	await expect(picture).toBeVisible();

	const box = await picture.boundingBox();
	expect(box).not.toBeNull();
	expect((box?.width ?? 0) / (box?.height ?? 1)).toBeCloseTo(0.5, 1);
});

test('a focal point set on the image comes back after saving', async ({ page }) => {
	await openNode(page, 'focal');
	await upload(field(page, COVER), 'landscape.jpg');
	let dialog = await openDetails(field(page, COVER), page);
	const frame = dialog.locator('.stage .frame');
	const box = await frame.boundingBox();
	await frame.click({ position: { x: (box?.width ?? 0) * 0.25, y: (box?.height ?? 0) * 0.75 } });
	await expect(dialog.locator('.focus-status')).toContainText('25');
	await dialog.getByRole('button', { name: 'Apply' }).click();
	await save(page);

	await page.reload();
	dialog = await openDetails(field(page, COVER), page);
	await expect(dialog.locator('.focus-status')).toContainText('25');
	await expect(dialog.locator('.focus-status')).toContainText('75');
});

test('a crop drawn on the image reaches the page', async ({ page }) => {
	await openNode(page, 'crop');
	await upload(field(page, COVER), 'landscape.jpg');
	const dialog = await openDetails(field(page, COVER), page);
	await dialog.getByRole('button', { name: 'Crop', exact: true }).click();
	const frame = dialog.locator('.stage .frame');
	const box = await frame.boundingBox();
	const left = box?.x ?? 0;
	const top = box?.y ?? 0;
	// The frame starts around the whole image: this pulls its top left corner to the middle.
	await page.mouse.move(left + 2, top + 2);
	await page.mouse.down();
	await page.mouse.move(left + (box?.width ?? 0) / 2 + 4, top + (box?.height ?? 0) / 2 + 4, {
		steps: 8,
	});
	await page.mouse.up();
	await dialog.getByRole('button', { name: 'Apply' }).click();
	await publish(page);

	await page.goto('/crop');
	const picture = page.locator('main figure img');
	await expect(picture).toHaveAttribute('src', /-wide~[0-9a-f]{8}~c[0-9._]+~[0-9a-f]{16}\.jpg$/);
	// Only the white bottom right quarter is left.
	for (const [x, y] of [
		[0.05, 0.05],
		[0.95, 0.05],
		[0.05, 0.95],
		[0.95, 0.95],
	]) {
		const [red, green, blue] = await colorAt(page, picture, x, y);
		expect(Math.min(red, green, blue)).toBeGreaterThan(200);
	}

	const width = Number(await picture.getAttribute('width'));
	const height = Number(await picture.getAttribute('height'));
	expect(await picture.evaluate((image: HTMLImageElement) => image.naturalWidth)).toBe(width);
	expect(await picture.evaluate((image: HTMLImageElement) => image.naturalHeight)).toBe(height);
});

test('texts set per use reach the page', async ({ page }) => {
	await openNode(page, 'texts');
	await upload(field(page, COVER), 'landscape.jpg');
	const dialog = await openDetails(field(page, COVER), page);
	await dialog.getByRole('textbox').first().fill('Four quarters');
	await dialog.getByRole('button', { name: 'Apply' }).click();
	await publish(page);

	await page.goto('/texts');
	await expect(page.locator('main figure img')).toHaveAttribute('alt', 'Four quarters');
});

test('a missing required text blocks saving and says where', async ({ page }) => {
	await openNode(page, 'strict');
	await upload(field(page, STRICT), 'landscape.jpg');
	await expect(field(page, STRICT).locator('img').first()).toBeVisible();
	await save(page);

	await expect(page.getByText(/Strict.*Alternative text/).first()).toBeVisible();

	const dialog = await openDetails(field(page, STRICT), page);
	await dialog.getByRole('textbox').first().fill('Described');
	await dialog.getByRole('button', { name: 'Apply' }).click();
	await save(page);
	await expect(page.getByText(/Strict.*Alternative text/)).toHaveCount(0);
});

test('a long gallery folds to full rows and unfolds', async ({ page }) => {
	await page.setViewportSize({ width: 1000, height: 1000 });
	await openNode(page, 'gallery');
	const gallery = field(page, GALLERY);
	await upload(gallery, ...Array.from({ length: 12 }, (_, i) => `tile-${i + 1}.jpg`));
	const tiles = gallery.locator('.tile');
	await expect(tiles).toHaveCount(12);
	const viewport = gallery.locator('.viewport');
	const fold = gallery.locator('button.fold');

	// New tiles stay in view until the editor folds them away.
	await expect(fold).toHaveText(/Show fewer/);
	const open = await viewport.boundingBox();
	const end = await tiles.nth(11).boundingBox();
	expect((end?.y ?? 0) + (end?.height ?? 0)).toBeLessThanOrEqual(
		(open?.y ?? 0) + (open?.height ?? 0) + 1,
	);

	await fold.click();
	await expect(fold).toHaveText(/Show all 12 images/);
	await expect
		.poll(async () => (await viewport.boundingBox())?.height ?? 0)
		.toBeLessThan(open?.height ?? 0);
	await expect(viewport).toHaveClass(/is-clipped/);
	const folded = await viewport.boundingBox();
	const visible = await tiles.evaluateAll(
		(all) => all.filter((tile) => !(tile as HTMLElement).inert).length,
	);
	expect(visible).toBe(8);
	const last = await tiles.nth(visible - 1).boundingBox();
	expect((last?.y ?? 0) + (last?.height ?? 0)).toBeLessThanOrEqual(
		(folded?.y ?? 0) + (folded?.height ?? 0) + 1,
	);

	await fold.click();
	await expect(fold).toHaveText(/Show fewer/);
	await expect
		.poll(async () => (await viewport.boundingBox())?.height ?? 0)
		.toBeGreaterThan(folded?.height ?? 0);
});

test('gallery order follows a drag and survives saving', async ({ page }) => {
	await openNode(page, 'gallery');
	const gallery = field(page, GALLERY);
	const tiles = gallery.locator('.tile');
	if ((await tiles.count()) === 0) {
		await upload(gallery, ...[1, 2, 3].map((i) => `tile-${i}.jpg`));
		await expect(tiles).toHaveCount(3);
		await save(page);
		await page.reload();
	}

	const order = () =>
		tiles.locator('img').evaluateAll((all) => all.map((image) => image.getAttribute('src')));
	const before = await order();
	const from = await tiles.nth(0).boundingBox();
	const to = await tiles.nth(1).boundingBox();
	await page.mouse.move((from?.x ?? 0) + 20, (from?.y ?? 0) + 20);
	await page.mouse.down();
	await page.mouse.move((to?.x ?? 0) + (to?.width ?? 0) / 2, (to?.y ?? 0) + 20, { steps: 12 });
	await page.mouse.move((to?.x ?? 0) + (to?.width ?? 0) - 5, (to?.y ?? 0) + 20, { steps: 6 });
	await page.mouse.up();
	await expect.poll(order).not.toEqual(before);
	const moved = await order();
	await save(page);

	await page.reload();
	expect(await order()).toEqual(moved);
});
