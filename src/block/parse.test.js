import { getParsedData } from './parse';

describe('getParsedData', () => {
	it('parses a valid JSON value', () => {
		expect(getParsedData('{"symbol":"IBM"}')).toEqual({ symbol: 'IBM' });
	});

	it.each([undefined, null, '', 'not JSON'])('returns an empty object for invalid input', (data) => {
		expect(getParsedData(data)).toEqual({});
	});
});
