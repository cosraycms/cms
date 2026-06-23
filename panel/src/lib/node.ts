import type { Node } from '$types/data';
import { _ } from '$lib/locale';
import { navigate, panelBase } from '$lib/runtime';
import { setPristine, broadcastOk } from '$lib/state';
import req from '$lib/req';
import toast from '$lib/toast';

export interface Result {
	success: boolean;
	uid: string;
}

function successToast() {
	toast.add({
		kind: 'success',
		message: _('Dokument erfolgreich gespeichert!'),
	});
}

function errorToast(data: any) {
	toast.add({
		kind: 'error',
		message:
			data?.message ?? data?.description ?? _('Fehler beim Speichern des Dokuments aufgetreten!'),
	});
}

export async function save(uid: string, node: Node) {
	const response = await req.put(`node/${uid}`, node);

	if (response?.ok) {
		successToast();
		setPristine();

		return response?.data as Result;
	} else {
		const data = response?.data;
		errorToast(data);

		return response?.data as Result;
	}
}

export async function saveAndClose(uid: string, node: Node) {
	const result = await save(uid, node);

	if (result.success) {
		broadcastOk();
	}
}

export async function create(node: Node, type: string, documentPath: string) {
	const response = await req.post(`node/${type}`, node);

	if (response?.ok) {
		toast.add({
			kind: 'success',
			message: _('Dokument erfolgreich erstellt!'),
		});

		setPristine();

		const result = response.data as Result;

		await navigate(`${panelBase()}${documentPath}/${result.uid}`, {
			invalidateAll: true,
		});

		return result;
	} else {
		errorToast(response?.data);

		return response?.data as Result;
	}
}

export async function createAndClose(node: Node, type: string, documentPath: string) {
	const result = await create(node, type, documentPath);

	if (result.success) {
		broadcastOk();
	}
}

export async function remove(uid: string, collectionPath: string) {
	const response = await req.del(`node/${uid}`);

	if (response?.ok) {
		await navigate(`${panelBase()}${collectionPath}`, { invalidateAll: true });

		toast.add({
			kind: 'success',
			message: _('Dokument erfolgreich gelöscht!'),
		});
	} else {
		toast.add({
			kind: 'error',
			message: _('Fehler beim Löschen des Dokuments aufgetreten!'),
		});
	}
}
