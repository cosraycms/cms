import { expect, type Page, test } from '@playwright/test';
import { mine, openNode } from './support';

async function edit(page: Page, title: string): Promise<void> {
	const autosaved = page.waitForResponse(
		(response) => response.url().endsWith('/autosave') && response.ok(),
	);
	await page.getByLabel('Title').fill(title);
	await autosaved;
	await expect(mine(page)).toHaveAttribute('data-state', 'present');
}

test('the status tab moves edits up the stages and discards a draft without my changes', async ({
	page,
}) => {
	await openNode(page, 'stages');
	const panel = page.locator('#editor-status-panel');
	const draft = page.locator('#editor-status-dots > [data-stage="draft"]');
	const applyStep = panel.getByRole('button', { name: 'Apply to the main version' });
	const saveStep = panel.getByRole('button', { name: 'Save draft' });

	await expect(applyStep).toBeHidden();
	await expect(saveStep).toBeHidden();
	await expect(draft).toBeHidden();
	await expect(mine(page)).toBeHidden();

	await edit(page, 'Drafted');
	await expect(panel).toContainText('applies my changes');
	await saveStep.click();
	await expect(draft).toHaveAttribute('data-state', 'present');
	await expect(draft).toBeVisible();
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
	await expect(mine(page)).toBeHidden();
	await expect(saveStep).toBeHidden();
	await expect(panel).toContainText('applies the draft');

	await edit(page, 'Drafted, then mine');
	await expect(panel).toContainText('applies the draft and my changes');

	// Discarding the draft can keep my changes, which still hold the draft's.
	await panel.getByRole('button', { name: 'Discard draft…' }).click();
	const discard = page.getByRole('dialog', { name: 'Discard draft?' });
	await expect(discard.getByRole('button', { name: 'Cancel' })).toBeFocused();
	await discard.getByLabel('Discard my changes as well').uncheck();
	await discard.getByRole('button', { name: 'Discard draft' }).click();

	await expect(page.locator('#cms-toasts')).toContainText('Draft discarded');
	await expect(draft).toHaveAttribute('data-state', 'none');
	await expect(draft).toBeVisible();
	await expect(mine(page)).toHaveAttribute('data-state', 'present');
	await expect(mine(page)).toBeVisible();
	await expect(page.getByLabel('Title')).toHaveValue('Drafted, then mine');

	// Applying asks first and names what it takes over.
	await applyStep.click();
	const apply = page.getByRole('dialog', { name: 'Apply to the main version?' });
	await expect(apply.getByText('My changes')).toBeVisible();
	await expect(apply.getByText('Draft', { exact: true })).toBeHidden();
	await expect(apply).toContainText('The page is online.');
	await apply.getByRole('button', { name: 'Apply', exact: true }).click();

	await expect(page.locator('#cms-toasts')).toContainText('Applied');
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
	await expect(applyStep).toBeHidden();
	await expect(page.getByLabel('Title')).toHaveValue('Drafted, then mine');
});
