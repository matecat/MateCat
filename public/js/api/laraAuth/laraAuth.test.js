import {laraAuth, laraAuthJob} from './laraAuth'

jest.mock('../../utils/getMatecatApiDomain', () => ({
  getMatecatApiDomain: jest.fn(() => 'http://localhost/'),
}))

describe('laraAuth API', () => {
  beforeEach(() => {
    global.fetch = jest.fn()
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  test('laraAuthJob calls expected endpoint with credentials include', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], token: 'tok'}),
    })

    await laraAuthJob({idJob: 10, password: 'pwd'})

    expect(fetch).toHaveBeenCalledWith(
      'http://localhost/api/app/jobs/10/pwd/lara/auth?reasoning=true',
      {
        credentials: 'include',
      },
    )
  })

  test('laraAuthJob supports reasoning=false in query string', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], token: 'tok'}),
    })

    await laraAuthJob({idJob: 10, password: 'pwd', reasoning: false})

    expect(fetch).toHaveBeenCalledWith(
      'http://localhost/api/app/jobs/10/pwd/lara/auth?reasoning=false',
      {
        credentials: 'include',
      },
    )
  })

  test('laraAuth returns response data without errors field on success', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest.fn().mockResolvedValue({errors: [], token: 'tok'}),
    })

    const result = await laraAuth()

    expect(result).toEqual({token: 'tok'})
    expect(fetch).toHaveBeenCalledWith('http://localhost/api/app/lara/auth', {
      credentials: 'include',
    })
  })

  test('rejects with first error object when response is not ok and has body', async () => {
    const response = {
      ok: false,
      headers: {get: jest.fn(() => '12')},
      json: jest.fn().mockResolvedValue({
        errors: [{message: 'forbidden'}],
      }),
    }
    fetch.mockResolvedValue(response)

    await expect(laraAuth()).rejects.toEqual({
      response,
      errors: {message: 'forbidden'},
    })
  })

  test('rejects with response only when response is not ok and content-length is 0', async () => {
    const response = {
      ok: false,
      headers: {get: jest.fn(() => '0')},
      json: jest.fn(),
    }
    fetch.mockResolvedValue(response)

    await expect(laraAuth()).rejects.toEqual({response})
    expect(response.json).not.toHaveBeenCalled()
  })

  test('rejects with errors array when response ok but errors are present', async () => {
    fetch.mockResolvedValue({
      ok: true,
      json: jest
        .fn()
        .mockResolvedValue({errors: [{message: 'auth failed'}], token: 'tok'}),
    })

    await expect(laraAuth()).rejects.toEqual([{message: 'auth failed'}])
  })

  test('rejects with payload when non-ok response body has empty errors array', async () => {
    const response = {
      ok: false,
      headers: {get: jest.fn(() => '34')},
      json: jest
        .fn()
        .mockResolvedValue({errors: [], message: 'generic error'}),
    }
    fetch.mockResolvedValue(response)

    await expect(laraAuthJob({idJob: 3, password: 'x'})).rejects.toEqual({
      response,
      errors: {errors: [], message: 'generic error'},
    })
  })
})
