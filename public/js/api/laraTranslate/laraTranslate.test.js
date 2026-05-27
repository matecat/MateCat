import {laraTranslate} from './laraTranslate'

const mockTranslate = jest.fn()
const mockTranslator = jest.fn()
const mockAuthToken = jest.fn()

jest.mock('@translated/lara', () => ({
  AuthToken: jest.fn((...args) => mockAuthToken(...args)),
  Translator: jest.fn((...args) => mockTranslator(...args)),
}))

describe('laraTranslate', () => {
  beforeEach(() => {
    global.config = {
      source_rfc: 'en-US',
      target_rfc: 'it-IT',
    }

    mockTranslate.mockReset()
    mockAuthToken.mockReset()
    mockTranslator.mockReset()

    mockAuthToken.mockImplementation((token, second) => ({token, second}))
    mockTranslator.mockImplementation(() => ({
      translate: mockTranslate,
    }))
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  test('creates AuthToken and Translator with expected params', async () => {
    mockTranslate.mockResolvedValue({translation: []})

    await laraTranslate({
      token: 'abc',
      source: 'Hello world',
      contextListBefore: [],
      contextListAfter: [],
      sid: '12',
      jobId: '77',
      glossaries: [],
      style: 'faithful',
      styleguideId: 'sg1',
    })

    expect(mockAuthToken).toHaveBeenCalledWith('abc', null)
    expect(mockTranslator).toHaveBeenCalledWith(
      {token: 'abc', second: null},
      {connectionTimeoutMs: 30000},
    )
  })

  test('builds text blocks with context before/source/context after', async () => {
    mockTranslate.mockResolvedValue({translation: []})

    await laraTranslate({
      token: 'abc',
      source: 'Main source',
      contextListBefore: ['before-1', 'before-2'],
      contextListAfter: ['after-1'],
      sid: '12',
      jobId: '77',
      glossaries: [],
      style: 'fluid',
      styleguideId: 'sg2',
      reasoning: false,
    })

    const [textBlocks] = mockTranslate.mock.calls[0]
    expect(textBlocks).toEqual([
      {text: 'before-1', translatable: false},
      {text: 'before-2', translatable: false},
      {text: 'Main source', translatable: true},
      {text: 'after-1', translatable: false},
    ])
  })

  test('passes source/target RFC and translate options', async () => {
    mockTranslate.mockResolvedValue({translation: []})

    await laraTranslate({
      token: 'abc',
      source: 'Main source',
      contextListBefore: [],
      contextListAfter: [],
      sid: '88',
      jobId: '99',
      glossaries: ['g1'],
      style: 'creative',
      styleguideId: 'sg3',
      reasoning: false,
    })

    const [, sourceRfc, targetRfc, options] = mockTranslate.mock.calls[0]
    expect(sourceRfc).toBe('en-US')
    expect(targetRfc).toBe('it-IT')
    expect(options).toEqual({
      multiline: false,
      contentType: 'application/xliff+xml',
      headers: {'X-Lara-Engine-Tuid': '99:88'},
      glossaries: ['g1'],
      reasoning: false,
      style: 'creative',
      styleguideId: 'sg3',
    })
  })

  test('uses reasoning=true by default', async () => {
    mockTranslate.mockResolvedValue({translation: []})

    await laraTranslate({
      token: 'abc',
      source: 'Main source',
      contextListBefore: [],
      contextListAfter: [],
      sid: '88',
      jobId: '99',
      glossaries: [],
      style: 'creative',
      styleguideId: 'sg3',
    })

    const [, , , options] = mockTranslate.mock.calls[0]
    expect(options.reasoning).toBe(true)
  })

  test('returns translate result', async () => {
    const payload = {
      translation: [{text: 'Ciao', translatable: true}],
      score: 0.98,
    }
    mockTranslate.mockResolvedValue(payload)

    const result = await laraTranslate({
      token: 'abc',
      source: 'Hello',
      contextListBefore: [],
      contextListAfter: [],
      sid: '1',
      jobId: '2',
      glossaries: [],
      style: 'faithful',
      styleguideId: 'sg1',
    })

    expect(result).toEqual(payload)
  })
})
