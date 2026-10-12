import {renderHook, act, waitFor} from '@testing-library/react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import useOutsourceQuote from './useOutsourceQuote'
import {getOutsourceQuote} from '../api/getOutsourceQuote'
import CommonUtils from '../utils/commonUtils'

jest.mock('../api/getOutsourceQuote', () => ({
  getOutsourceQuote: jest.fn(),
}))
jest.mock('../utils/commonUtils', () => ({
  getGMTDate: jest.fn(),
}))

const baseJob = () => fromJS({id: 10, password: 'jobpwd', outsource: null})
const baseProject = () => fromJS({id: 1, password: 'projpwd'})

const successQuotePayload = (overrides = {}) => ({
  data: [
    [
      {
        quote_available: '1',
        outsourced: '0',
        quote_result: '1',
        id: 99,
        typeOfService: 'professional',
        delivery: '2026-08-01T00:00:00.000Z',
        r_delivery: '2026-08-02T00:00:00.000Z',
        ...overrides,
      },
    ],
  ],
  return_url: {
    url_ok: 'http://ok',
    url_ko: 'http://ko',
    confirm_urls: ['http://confirm'],
  },
})

describe('useOutsourceQuote', () => {
  beforeEach(() => {
    jest.useFakeTimers()
    jest.clearAllMocks()
    global.config = {...global.config, enable_outsource: true}
    Cookies.set('matecat_timezone', '2')
    CommonUtils.getGMTDate.mockReturnValue({time2: '14:00'})
  })

  afterEach(() => {
    jest.runOnlyPendingTimers()
    jest.useRealTimers()
    Cookies.remove('matecat_timezone')
  })

  test('does not auto-fetch when outsourcing is disabled', () => {
    global.config = {...global.config, enable_outsource: false}
    renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    expect(getOutsourceQuote).not.toHaveBeenCalled()
  })

  test('fetches quote on mount and applies successful response', async () => {
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )

    await waitFor(() => expect(result.current.outsource).toBe(true))
    expect(getOutsourceQuote).toHaveBeenCalledWith(
      1,
      'projpwd',
      10,
      'jobpwd',
      '',
      'professional',
      '2',
      'EUR',
    )
    expect(result.current.quoteNotAvailable).toBe(false)
    expect(result.current.errorQuote).toBe(false)
    expect(result.current.chunkQuote.get('id')).toBe(99)
    expect(result.current.jobOutsourced).toBe(false)
    expect(result.current.outsourceConfirmed).toBe(false)
    expect(result.current.urlOkRef.current).toBe('http://ok')
    expect(result.current.dataKeyRef.current).toBe(99)

    act(() => {
      jest.runOnlyPendingTimers()
    })
    await waitFor(() => expect(result.current.selectedTime).toBe('14'))
  })

  test('marks quote as unavailable when quote_available is not 1', async () => {
    getOutsourceQuote.mockResolvedValueOnce(
      successQuotePayload({quote_available: '0'}),
    )
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.quoteNotAvailable).toBe(true))
    expect(result.current.outsource).toBe(true)
    expect(result.current.errorQuote).toBe(false)
  })

  test('marks an error when quote_result is not 1', async () => {
    getOutsourceQuote.mockResolvedValueOnce(
      successQuotePayload({quote_result: '0'}),
    )
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.errorQuote).toBe(true))
    expect(result.current.outsource).toBe(true)
  })

  test('handles empty response data', async () => {
    getOutsourceQuote.mockResolvedValueOnce({data: []})
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.errorOutsource).toBe(true))
    expect(result.current.outsource).toBe(false)
    expect(result.current.errorQuote).toBe(true)
  })

  test('handles rejected quote request', async () => {
    getOutsourceQuote.mockRejectedValueOnce(new Error('network error'))
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.errorOutsource).toBe(true))
    expect(result.current.outsource).toBe(false)
    expect(result.current.errorQuote).toBe(true)
  })

  test('toggleRevision flips revision and re-fetches with the matching service', async () => {
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.outsource).toBe(true))

    getOutsourceQuote.mockResolvedValueOnce(
      successQuotePayload({typeOfService: 'premium'}),
    )
    act(() => {
      result.current.toggleRevision()
    })
    expect(result.current.revision).toBe(true)

    await act(async () => {
      jest.runOnlyPendingTimers()
    })
    await waitFor(() =>
      expect(getOutsourceQuote).toHaveBeenLastCalledWith(
        1,
        'projpwd',
        10,
        'jobpwd',
        '',
        'premium',
        '2',
        'EUR',
      ),
    )
  })

  test('getDeliveryDateFromQuote prefers job.outsource over the fetched chunk', () => {
    global.config = {...global.config, enable_outsource: false}
    const jobWithOutsource = fromJS({
      id: 10,
      password: 'jobpwd',
      outsource: {delivery_date: '2026-08-05T00:00:00.000Z'},
    })
    CommonUtils.getGMTDate.mockReturnValue({day: '5', month: 'Aug'})

    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: jobWithOutsource,
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )

    const date = result.current.getDeliveryDateFromQuote(false)
    expect(CommonUtils.getGMTDate).toHaveBeenCalledWith(
      '2026-08-05T00:00:00.000Z',
    )
    expect(date).toEqual({day: '5', month: 'Aug'})
  })

  test('getDeliveryDateFromQuote falls back to chunkQuote delivery/r_delivery', async () => {
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.outsource).toBe(true))

    CommonUtils.getGMTDate.mockReturnValue({day: '2'})
    expect(result.current.getDeliveryDateFromQuote(true)).toEqual({day: '2'})
    expect(CommonUtils.getGMTDate).toHaveBeenCalledWith(
      '2026-08-02T00:00:00.000Z',
    )

    CommonUtils.getGMTDate.mockReturnValue({day: '1'})
    expect(result.current.getDeliveryDateFromQuote(false)).toEqual({day: '1'})
    expect(CommonUtils.getGMTDate).toHaveBeenCalledWith(
      '2026-08-01T00:00:00.000Z',
    )
  })

  test('checkChosenDateIsAfter compares selectedDateRef against the chunk delivery date', async () => {
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.outsource).toBe(true))

    // no selectedDateRef set yet -> false
    expect(result.current.checkChosenDateIsAfter()).toBe(false)

    result.current.selectedDateRef.current = new Date(
      '2026-09-01T00:00:00.000Z',
    ).getTime()
    expect(result.current.checkChosenDateIsAfter()).toBe(true)

    act(() => {
      result.current.toggleRevision()
    })
    getOutsourceQuote.mockResolvedValueOnce(
      successQuotePayload({typeOfService: 'premium'}),
    )
    await act(async () => {
      jest.runOnlyPendingTimers()
    })
    await waitFor(() => expect(result.current.revision).toBe(true))
    result.current.selectedDateRef.current = new Date(
      '2026-09-05T00:00:00.000Z',
    ).getTime()
    expect(result.current.checkChosenDateIsAfter()).toBe(true)
  })

  test('updateTimezoneRef stores the latest timezone for subsequent fetches', async () => {
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    const {result} = renderHook(() =>
      useOutsourceQuote({
        job: baseJob(),
        project: baseProject(),
        getCurrentCurrency: () => 'EUR',
      }),
    )
    await waitFor(() => expect(result.current.outsource).toBe(true))

    act(() => {
      result.current.updateTimezoneRef('5')
    })
    getOutsourceQuote.mockResolvedValueOnce(successQuotePayload())
    act(() => {
      result.current.fetchQuote('2026-08-10', 'professional')
    })
    await waitFor(() =>
      expect(getOutsourceQuote).toHaveBeenLastCalledWith(
        1,
        'projpwd',
        10,
        'jobpwd',
        '2026-08-10',
        'professional',
        '5',
        'EUR',
      ),
    )
  })
})
