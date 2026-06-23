function asArray(url: URL, field: string): string[] {
	const fieldsParam = url.searchParams.get(field);

	return fieldsParam ? fieldsParam.split(',').map((field) => field.trim()) : [];
}

export default {
	asArray,
};
