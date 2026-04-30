import {laraTranslate} from './laraTranslate'

const mockPostAndGetStream = jest.fn()

jest.mock('@translated/lara', () => {
  class MockTranslator {
    constructor() {
      this.client = {postAndGetStream: mockPostAndGetStream}
    }
  }
  return {
    AuthToken: jest.fn(),
    Translator: MockTranslator,
  }
})

global.config = {
  source_rfc: 'en-US',
  target_rfc: 'it-IT',
}

beforeEach(() => {
  mockPostAndGetStream.mockReset()
})

async function* asyncIterableOf(...items) {
  for (const item of items) {
    yield item
  }
}

test('Returns last streamed translation result', async () => {
  mockPostAndGetStream.mockReturnValue(
    asyncIterableOf({translation: 'partial'}, {translation: 'Ciao mondo'}),
  )

  const result = await laraTranslate({
    token: 'fake-token',
    source: 'Hello world',
    contextListBefore: [],
    contextListAfter: [],
    sid: '1',
    jobId: '100',
    glossaries: [],
    style: undefined,
    reasoning: false,
  })

  expect(result).toEqual({translation: 'Ciao mondo'})
})

test('Builds text blocks with context before and after', async () => {
  mockPostAndGetStream.mockReturnValue(
    asyncIterableOf({translation: 'result'}),
  )

  await laraTranslate({
    token: 'fake-token',
    source: 'Main segment',
    contextListBefore: ['ctx before 1', 'ctx before 2'],
    contextListAfter: ['ctx after 1'],
    sid: '5',
    jobId: '200',
    glossaries: [],
    style: undefined,
    reasoning: false,
  })

  const callArgs = mockPostAndGetStream.mock.calls[0]
  const endpoint = callArgs[0]
  const payload = callArgs[1]

  expect(endpoint).toBe('/v2/translate')
  expect(payload.q).toEqual([
    {text: 'ctx before 1', translatable: false},
    {text: 'ctx before 2', translatable: false},
    {text: 'Main segment', translatable: true},
    {text: 'ctx after 1', translatable: false},
  ])
})

test('Passes correct translation options', async () => {
  mockPostAndGetStream.mockReturnValue(
    asyncIterableOf({translation: 'result'}),
  )

  await laraTranslate({
    token: 'fake-token',
    source: 'Hello',
    contextListBefore: [],
    contextListAfter: [],
    sid: '3',
    jobId: '50',
    glossaries: [{id: 'g1'}],
    style: 'formal',
    reasoning: true,
  })

  const callArgs = mockPostAndGetStream.mock.calls[0]
  const payload = callArgs[1]
  const headers = callArgs[3]

  expect(payload.source).toBe('en-US')
  expect(payload.target).toBe('it-IT')
  expect(payload.multiline).toBe(false)
  expect(payload.content_type).toBe('application/xliff+xml')
  expect(payload.glossaries).toEqual([{id: 'g1'}])
  expect(payload.reasoning).toBe(true)
  expect(payload.style).toBe('formal')
  expect(headers).toEqual({'X-Lara-Engine-Tuid': '50:3'})
})

test('Sets X-Lara-Engine-Tuid header from jobId and sid', async () => {
  mockPostAndGetStream.mockReturnValue(
    asyncIterableOf({translation: 'result'}),
  )

  await laraTranslate({
    token: 'token',
    source: 'text',
    contextListBefore: [],
    contextListAfter: [],
    sid: '42',
    jobId: '999',
    glossaries: [],
    style: undefined,
    reasoning: false,
  })

  const headers = mockPostAndGetStream.mock.calls[0][3]
  expect(headers).toEqual({'X-Lara-Engine-Tuid': '999:42'})
})

test('Throws error when stream yields no results', async () => {
  mockPostAndGetStream.mockReturnValue(asyncIterableOf())

  await expect(
    laraTranslate({
      token: 'token',
      source: 'text',
      contextListBefore: [],
      contextListAfter: [],
      sid: '1',
      jobId: '1',
      glossaries: [],
      style: undefined,
      reasoning: false,
    }),
  ).rejects.toThrow('No translation result received.')
})

test('Returns final result when multiple partials are streamed with reasoning', async () => {
  const partial1 = {translation: 'thinking...', reasoning: 'step 1'}
  const partial2 = {translation: 'final answer', reasoning: 'step 2'}
  mockPostAndGetStream.mockReturnValue(asyncIterableOf(partial1, partial2))

  const result = await laraTranslate({
    token: 'token',
    source: 'text',
    contextListBefore: [],
    contextListAfter: [],
    sid: '1',
    jobId: '1',
    glossaries: [],
    style: undefined,
    reasoning: true,
  })

  expect(result).toEqual(partial2)
})
