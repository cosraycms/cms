import { expect, test } from '@playwright/test';
import { openNode, save } from './support';

test('a failed save names the open fields and follows the edits', async ({ page }) => {
	await openNode(page, 'article');
	const title = page.getByLabel('Title');
	const date = page.getByLabel('Date');
	const summary = page.locator('#editor-errors');
	const problems = page.locator('#editor-problems');

	await title.fill('');
	await date.fill('');
	await save(page);

	await expect(summary).toBeVisible();
	await expect(summary.getByText('Entry was not saved')).toBeVisible();
	await expect(summary.getByText('2 required fields are empty:')).toBeVisible();
	await expect(summary.locator('.chip')).toHaveText(['Title →', 'Date →']);
	await expect(title).toBeFocused();
	await expect(title).toHaveAttribute('aria-invalid', 'true');
	await expect(page.locator('.cms-field[data-field="title"] .cms-field-error')).toHaveText(
		'Please fill this in.',
	);
	await expect(page.locator('.cms-field[data-field="date"] .cms-field-error')).toHaveText(
		'Please choose something.',
	);
	await expect(problems.getByRole('button', { name: '2 problems' })).toBeVisible();
	await expect(page.locator('#editor-status')).toBeEmpty();

	await problems.getByRole('button', { name: '2 problems' }).click();
	const menu = page.getByRole('menu');
	await expect(menu.getByText('Fix before saving')).toBeVisible();
	await menu.getByRole('menuitem', { name: /Date/ }).click();
	await expect(menu).toBeHidden();
	await expect(date).toBeFocused();

	await title.fill('Back again');
	await expect(summary.locator('.chip')).toHaveText(['Date →']);
	await expect(problems.getByRole('button', { name: '1 problem' })).toBeVisible();
	await expect(title).not.toHaveAttribute('aria-invalid');

	await date.fill('2026-05-11');
	await expect(summary).toHaveClass(/is-resolved/);
	await expect(
		summary.getByText('All reported fields have been edited. Save again.'),
	).toBeVisible();
	await expect(problems).toBeHidden();

	await save(page);
	await expect(summary).toBeHidden();
	await expect(page.locator('#cms-toasts')).toContainText(/saved/i);
});
