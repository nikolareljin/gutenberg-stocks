export const getParsedData = (data) => {
	if ('string' !== typeof data || '' === data) {
		return {};
	}

	try {
		return JSON.parse(data);
	} catch {
		return {};
	}
};
