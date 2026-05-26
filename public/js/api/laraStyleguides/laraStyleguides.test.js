import {laraStyleguides} from './laraStyleguides'

const mockList = jest.fn()
const mockAuthToken = jest.fn()
const mockTranslator = jest.fn()

jest.mock('@translated/lara', () => ({
  AuthToken: jest.fn((...args) => mockAuthToken(...args)),
  Translator: jest.fn((...args) => mockTranslator(...args)),
}))

describe('laraStyleguides', () => {
  beforeEach(() => {
    mockList.mockReset()
    mockAuthToken.mockReset()
    mockTranslator.mockReset()

    mockAuthToken.mockImplementation((token, second) => ({token, second}))
    mockTranslator.mockImplementation(() => ({
      styleguides: {
        list: mockList,
      },
    }))
  })

  afterEach(() => {
    jest.clearAllMocks()
  })

  test('creates AuthToken and Translator with expected params', async () => {
    mockList.mockResolvedValue([])

    await laraStyleguides({token: 'abc-token'})

    expect(mockAuthToken).toHaveBeenCalledWith('abc-token', null)
    expect(mockTranslator).toHaveBeenCalledWith(
      {token: 'abc-token', second: null},
      {connectionTimeoutMs: 30000},
    )
  })

  test('calls styleguides.list and returns its value', async () => {
    const payload = [
      {id: 'sg-1', name: 'Guide 1'},
      {id: 'sg-2', name: 'Guide 2'},
    ]
    mockList.mockResolvedValue(payload)

    const result = await laraStyleguides({token: 'abc-token'})

    expect(mockList).toHaveBeenCalledTimes(1)
    expect(result).toEqual(payload)
  })

  test('propagates list errors', async () => {
    const error = new Error('network error')
    mockList.mockRejectedValue(error)

    await expect(laraStyleguides({token: 'abc-token'})).rejects.toBe(error)
  })
})
