import {getContributions} from './getContributions'

jest.mock('../../utils/getMatecatApiDomain', () => ({
  getMatecatApiDomain: jest.fn(() => 'http://localhost/'),
}))

describe('getContributions', () => {
  beforeEach(() => {
    global.fetch = jest.fn()

    global.config = {
      id_job: 'job-1',
      password: 'pwd-1',
      id_client: 'client-1',
      currentPassword: 'curr-1',
    }

    global.globalFunctions = {
      getContextBefore: jest.fn(() => 'before-context'),
      getIdBefore: jest.fn(() => 'before-id'),
      getContextAfter: jest.fn(() => 'after-context'),
      getIdAfter: jest.fn(() => 'after-id'),
    }
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  test('posts FormData with defaults including lara_model think and reasoning true', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], contribution: []}),
    })

    const result = await getContributions({
      idSegment: '12',
      target: 'target text',
      translation: 'translated text',
      crossLanguages: ['en-US', 'it-IT'],
      contextListBefore: ['b1', 'b2'],
      contextListAfter: ['a1'],
    })

    expect(result).toEqual({contribution: []})
    expect(fetch).toHaveBeenCalledWith(
      'http://localhost/api/app/get-contribution',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
      }),
    )

    const request = fetch.mock.calls[0][1]
    const body = request.body

    expect(body.get('id_segment')).toBe('12')
    expect(body.get('text')).toBe('target text')
    expect(body.get('translation')).toBe('translated text')
    expect(body.get('id_job')).toBe('job-1')
    expect(body.get('password')).toBe('pwd-1')
    expect(body.get('id_client')).toBe('client-1')
    expect(body.get('current_password')).toBe('curr-1')
    expect(body.get('context_before')).toBe('before-context')
    expect(body.get('id_before')).toBe('before-id')
    expect(body.get('context_after')).toBe('after-context')
    expect(body.get('id_after')).toBe('after-id')
    expect(body.get('cross_language')).toBe('en-US,it-IT')
    expect(body.get('context_list_before')).toBe(JSON.stringify(['b1', 'b2']))
    expect(body.get('context_list_after')).toBe(JSON.stringify(['a1']))
    expect(body.get('lara_model')).toBeNull()
    expect(body.get('reasoning')).toBe('true')
  })

  test('includes lara_style and custom lara_model/reasoning when provided', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], ok: true}),
    })

    await getContributions({
      idSegment: '22',
      target: 'foo',
      translation: 'bar',
      crossLanguages: [],
      contextListBefore: [],
      contextListAfter: [],
      laraStyle: 'formal',
      laraModel: 'prosa',
      reasoning: false,
    })

    const body = fetch.mock.calls[0][1].body
    expect(body.get('lara_style')).toBe('formal')
    expect(body.get('lara_model')).toBe('prosa')
    expect(body.get('reasoning')).toBe('false')
  })

  test('does not include lara_style when it is not a string', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], ok: true}),
    })

    await getContributions({
      idSegment: '22',
      target: 'foo',
      translation: 'bar',
      crossLanguages: [],
      contextListBefore: [],
      contextListAfter: [],
      laraStyle: 123,
    })

    const body = fetch.mock.calls[0][1].body
    expect(body.get('lara_style')).toBeNull()
  })

  test('rejects with response when fetch response is not ok', async () => {
    const response = {ok: false}
    fetch.mockResolvedValue(response)

    await expect(
      getContributions({
        idSegment: '12',
        target: 'target text',
        translation: 'translated text',
        crossLanguages: ['en-US'],
        contextListBefore: [],
        contextListAfter: [],
      }),
    ).rejects.toBe(response)
  })

  test('rejects with errors array when backend returns errors', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [{message: 'bad'}], data: []}),
    })

    await expect(
      getContributions({
        idSegment: '12',
        target: 'target text',
        translation: 'translated text',
        crossLanguages: ['en-US'],
        contextListBefore: [],
        contextListAfter: [],
      }),
    ).rejects.toEqual([{message: 'bad'}])
  })

  test('filters out null/undefined fields from form data', async () => {
    global.globalFunctions.getContextAfter.mockReturnValue(null)
    global.globalFunctions.getIdAfter.mockReturnValue(undefined)

    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], contribution: []}),
    })

    await getContributions({
      idSegment: '12',
      target: 'target text',
      translation: 'translated text',
      crossLanguages: ['en-US'],
      contextListBefore: [],
      contextListAfter: [],
      idClient: null,
    })

    const body = fetch.mock.calls[0][1].body
    expect(body.get('id_client')).toBeNull()
    expect(body.get('context_after')).toBeNull()
    expect(body.get('id_after')).toBeNull()
  })
})
