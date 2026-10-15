import {renderHook, act, waitFor} from '@testing-library/react'
import Cookies from 'js-cookie'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../mocks/mswServer'
import useOutsourceQuote, {
  clearOutsourceQuoteCache,
  evictOutsourceQuoteCache,
} from './useOutsourceQuote'
import CommonUtils from '../utils/commonUtils'

// jsdom has no global setImmediate; take Node's, which fake timers leave alone
const {setImmediate: realSetImmediate} = jest.requireActual('timers')

jest.mock('../utils/commonUtils', () => ({
  getGMTDate: jest.fn(),
}))

const QUOTE_URL = 'http://localhost/api/app/outsource-to'
const FAKE_TIMERS = {doNotFake: ['nextTick', 'queueMicrotask']}

// Lets pending network work settle without advancing (possibly fake) timers
const flushNetwork = async () => {
  for (let i = 0; i < 20; i++) {
    await act(() => new Promise((resolve) => realSetImmediate(resolve)))
  }
}

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

// Every request that reached the server, as plain {field: value} objects,
// with the job id and password exposed as jid/jpassword
let requests = []

const readRequest = async (request) => {
  const formData = await request.formData()
  const params = {}
  formData.forEach((value, key) => {
    if (/jid\]?$/.test(key)) params.jid = value
    else if (/jpassword\]?$/.test(key)) params.jpassword = value
    else params[key] = value
  })
  requests.push(params)
  return params
}

const mockQuote = (respond) =>
  mswServer.use(
    http.post(QUOTE_URL, async ({request}) => {
      const params = await readRequest(request)
      return respond(params)
    }),
  )

const defaultOptions = (overrides = {}) => ({
  projectId: 1,
  projectPassword: 'projpwd',
  jobId: 10,
  jobPassword: 'jobpwd',
  jobOutsource: null,
  enabled: true,
  getCurrentCurrency: () => 'EUR',
  ...overrides,
})

const renderQuoteHook = (overrides = {}) =>
  renderHook((props) => useOutsourceQuote(props), {
    initialProps: defaultOptions(overrides),
  })

describe('useOutsourceQuote', () => {
  beforeEach(() => {
    requests = []
    clearOutsourceQuoteCache()
    jest.clearAllMocks()
    global.config = {
      ...global.config,
      basepath: 'http://localhost/',
      enableMultiDomainApi: false,
    }
    Cookies.set('matecat_timezone', '2')
    CommonUtils.getGMTDate.mockReturnValue({time2: '14:00'})
  })

  afterEach(() => {
    clearOutsourceQuoteCache()
    jest.useRealTimers()
    Cookies.remove('matecat_timezone')
  })

  test('stays idle and does not fetch when disabled', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook({enabled: false})
    expect(result.current.status).toBe('idle')
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(requests).toHaveLength(0)
  })

  test('does not fetch without a job identity', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook({jobId: null, jobPassword: null})
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(requests).toHaveLength(0)
    expect(result.current.status).toBe('idle')
  })

  test('goes from loading to ready and applies the quote', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook()

    expect(result.current.status).toBe('loading')
    await waitFor(() => expect(result.current.status).toBe('ready'))

    expect(requests).toHaveLength(1)
    expect(requests[0]).toEqual(
      expect.objectContaining({
        pid: '1',
        ppassword: 'projpwd',
        jid: '10',
        jpassword: 'jobpwd',
        fixedDelivery: '',
        typeOfService: 'professional',
        timezone: '2',
        currency: 'EUR',
      }),
    )
    expect(result.current.outsource).toBe(true)
    expect(result.current.quoteNotAvailable).toBe(false)
    expect(result.current.errorQuote).toBe(false)
    expect(result.current.chunkQuote.get('id')).toBe(99)
    expect(result.current.jobOutsourced).toBe(false)
    expect(result.current.outsourceConfirmed).toBe(false)
    expect(result.current.urlOkRef.current).toBe('http://ok')
    expect(result.current.dataKeyRef.current).toBe(99)
    await waitFor(() => expect(result.current.selectedTime).toBe('14'))
  })

  test('reports unavailable when quote_available is not 1', async () => {
    mockQuote(() =>
      HttpResponse.json(successQuotePayload({quote_available: '0'})),
    )
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('unavailable'))
    expect(result.current.quoteNotAvailable).toBe(true)
    expect(result.current.outsource).toBe(true)
    expect(result.current.errorQuote).toBe(false)
  })

  test('reports an error when quote_result is not 1', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload({quote_result: '0'})))
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.errorQuote).toBe(true)
    expect(result.current.outsource).toBe(true)
    expect(result.current.errorOutsource).toBe(false)
  })

  test('reports an error on empty response data', async () => {
    mockQuote(() => HttpResponse.json({data: []}))
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.errorOutsource).toBe(true)
    expect(result.current.outsource).toBe(false)
    expect(result.current.errorQuote).toBe(true)
  })

  test('reports an error on network failure', async () => {
    mockQuote(() => HttpResponse.error())
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.errorOutsource).toBe(true)
    expect(result.current.outsource).toBe(false)
    expect(result.current.errorQuote).toBe(true)
  })

  test('retry refetches with the current options', async () => {
    let fail = true
    mockQuote(() =>
      fail ? HttpResponse.error() : HttpResponse.json(successQuotePayload()),
    )
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('error'))

    fail = false
    act(() => {
      result.current.retry()
    })
    expect(result.current.status).toBe('loading')
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(requests).toHaveLength(2)
    expect(requests[1]).toEqual(requests[0])
  })

  test('retry reuses the delivery and service of the last fetch', async () => {
    mockQuote(({typeOfService}) =>
      typeOfService === 'premium'
        ? HttpResponse.error()
        : HttpResponse.json(successQuotePayload()),
    )
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

    act(() => {
      result.current.fetchQuote('2026-08-10', 'premium')
    })
    await waitFor(() => expect(result.current.status).toBe('error'))

    act(() => {
      result.current.retry()
    })
    await waitFor(() => expect(requests).toHaveLength(3))
    expect(requests[2]).toEqual(
      expect.objectContaining({
        fixedDelivery: '2026-08-10',
        typeOfService: 'premium',
      }),
    )
  })

  test('waits debounceMs before fetching', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook({debounceMs: 300})

    expect(result.current.status).toBe('loading')
    act(() => {
      jest.advanceTimersByTime(299)
    })
    await flushNetwork()
    expect(requests).toHaveLength(0)

    act(() => {
      jest.advanceTimersByTime(1)
    })
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(requests).toHaveLength(1)
  })

  test('changing job within the debounce window fires only one request', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result, rerender} = renderQuoteHook({debounceMs: 300})

    act(() => {
      jest.advanceTimersByTime(200)
    })
    rerender(defaultOptions({debounceMs: 300, jobId: 11, jobPassword: 'pwd11'}))
    act(() => {
      jest.advanceTimersByTime(200)
    })
    await flushNetwork()
    expect(requests).toHaveLength(0)

    act(() => {
      jest.advanceTimersByTime(100)
    })
    await waitFor(() => expect(result.current.status).toBe('ready'))
    await flushNetwork()
    expect(requests).toHaveLength(1)
    expect(requests[0]).toEqual(
      expect.objectContaining({jid: '11', jpassword: 'pwd11'}),
    )
  })

  test('ignores the response of a job that is no longer current', async () => {
    let releaseFirst
    const firstGate = new Promise((resolve) => {
      releaseFirst = resolve
    })
    mockQuote(async ({jid}) => {
      if (jid === '10') {
        await firstGate
        return HttpResponse.json(successQuotePayload({id: 1}))
      }
      return HttpResponse.json(successQuotePayload({id: 2}))
    })
    const {result, rerender} = renderQuoteHook()
    await waitFor(() => expect(requests).toHaveLength(1))

    rerender(defaultOptions({jobId: 11, jobPassword: 'pwd11'}))
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(result.current.chunkQuote.get('id')).toBe(2)

    releaseFirst()
    await new Promise((resolve) => setTimeout(resolve, 50))
    expect(result.current.chunkQuote.get('id')).toBe(2)
    expect(result.current.dataKeyRef.current).toBe(2)
  })

  test('does not cache by default: a second mount refetches', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook()
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    unmountFirst()

    const {result: secondResult} = renderQuoteHook()
    expect(secondResult.current.status).toBe('loading')
    await waitFor(() => expect(secondResult.current.status).toBe('ready'))
    expect(requests).toHaveLength(2)
  })

  test('with cache, never applies a cached quote to an already ordered job', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    unmountFirst()

    const {result: secondResult} = renderQuoteHook({
      cache: true,
      jobOutsource: {delivery_date: '2026-08-05T00:00:00.000Z'},
    })
    expect(secondResult.current.status).toBe('loading')
    await waitFor(() => expect(secondResult.current.status).toBe('ready'))
    expect(requests).toHaveLength(2)
  })

  test('with cache, evicting the job drops all of its cached quotes', async () => {
    mockQuote(({typeOfService}) =>
      HttpResponse.json(successQuotePayload({typeOfService})),
    )
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    act(() => {
      firstResult.current.fetchQuote(undefined, 'premium')
    })
    await waitFor(() => expect(requests).toHaveLength(2))
    await waitFor(() => expect(firstResult.current.revision).toBe(true))
    unmountFirst()

    // Another job keeps its entry
    const {result: otherResult, unmount: unmountOther} = renderQuoteHook({
      cache: true,
      jobId: 11,
      jobPassword: 'pwd11',
    })
    await waitFor(() => expect(otherResult.current.status).toBe('ready'))
    unmountOther()
    expect(requests).toHaveLength(3)

    evictOutsourceQuoteCache(10, 'jobpwd')

    const {result: secondResult} = renderQuoteHook({cache: true})
    expect(secondResult.current.status).toBe('loading')
    await waitFor(() => expect(secondResult.current.status).toBe('ready'))
    expect(requests).toHaveLength(4)

    const {result: otherAgain} = renderQuoteHook({
      cache: true,
      jobId: 11,
      jobPassword: 'pwd11',
    })
    expect(otherAgain.current.status).toBe('ready')
    expect(requests).toHaveLength(4)
  })

  test('with cache, a caller mutating its quote does not change the cached one', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    // What OutsourceVendor's sendOutsource does before submitting the order
    firstResult.current.quoteResponseRef.current[0] = {id: 'mutated'}
    firstResult.current.quoteResponseRef.current.push({id: 'extra'})
    unmountFirst()

    const {result: secondResult} = renderQuoteHook({cache: true})
    expect(secondResult.current.status).toBe('ready')
    expect(secondResult.current.quoteResponseRef.current).toHaveLength(1)
    expect(secondResult.current.quoteResponseRef.current[0].id).toBe(99)
    expect(requests).toHaveLength(1)
  })

  test('with cache, serves a second mount of the same job from the cache', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    unmountFirst()

    const {result: secondResult} = renderQuoteHook({cache: true})
    expect(secondResult.current.status).toBe('ready')
    expect(secondResult.current.chunkQuote.get('id')).toBe(99)
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(requests).toHaveLength(1)
  })

  test('with cache, does not cache failed quotes', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload({quote_result: '0'})))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('error'))
    unmountFirst()

    const {result: secondResult} = renderQuoteHook({cache: true})
    expect(secondResult.current.status).toBe('loading')
    await waitFor(() => expect(requests).toHaveLength(2))
  })

  test('with cache, refetches once the cached quote is older than 10 minutes', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result: firstResult, unmount: unmountFirst} = renderQuoteHook({
      cache: true,
    })
    await waitFor(() => expect(firstResult.current.status).toBe('ready'))
    unmountFirst()

    act(() => {
      jest.advanceTimersByTime(10 * 60 * 1000 - 1000)
    })
    const {result: secondResult, unmount: unmountSecond} = renderQuoteHook({
      cache: true,
    })
    expect(secondResult.current.status).toBe('ready')
    unmountSecond()
    expect(requests).toHaveLength(1)

    act(() => {
      jest.advanceTimersByTime(1001)
    })
    const {result: thirdResult} = renderQuoteHook({cache: true})
    expect(thirdResult.current.status).toBe('loading')
    await waitFor(() => expect(thirdResult.current.status).toBe('ready'))
    expect(requests).toHaveLength(2)
  })

  test('toggleRevision flips revision and re-fetches with the matching service', async () => {
    mockQuote(({typeOfService}) =>
      HttpResponse.json(successQuotePayload({typeOfService})),
    )
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

    act(() => {
      result.current.toggleRevision()
    })
    expect(result.current.revision).toBe(true)
    await waitFor(() => expect(requests).toHaveLength(2))
    expect(requests[1].typeOfService).toBe('premium')
  })

  test('getDeliveryDateFromQuote prefers jobOutsource over the fetched chunk', () => {
    CommonUtils.getGMTDate.mockReturnValue({day: '5', month: 'Aug'})
    const {result} = renderQuoteHook({
      enabled: false,
      jobOutsource: {delivery_date: '2026-08-05T00:00:00.000Z'},
    })

    expect(result.current.outsourceConfirmed).toBe(true)
    expect(result.current.jobOutsourced).toBe(true)
    const date = result.current.getDeliveryDateFromQuote(false)
    expect(CommonUtils.getGMTDate).toHaveBeenCalledWith(
      '2026-08-05T00:00:00.000Z',
    )
    expect(date).toEqual({day: '5', month: 'Aug'})
  })

  test('getDeliveryDateFromQuote falls back to chunkQuote delivery/r_delivery', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

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
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

    // no selectedDateRef set yet -> false
    expect(result.current.checkChosenDateIsAfter()).toBe(false)

    result.current.selectedDateRef.current = new Date(
      '2026-09-01T00:00:00.000Z',
    ).getTime()
    expect(result.current.checkChosenDateIsAfter()).toBe(true)

    result.current.selectedDateRef.current = new Date(
      '2026-07-01T00:00:00.000Z',
    ).getTime()
    expect(result.current.checkChosenDateIsAfter()).toBe(false)
  })

  test('updateTimezoneRef stores the latest timezone for subsequent fetches', async () => {
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

    act(() => {
      result.current.updateTimezoneRef('5')
    })
    act(() => {
      result.current.fetchQuote('2026-08-10', 'professional')
    })
    await waitFor(() => expect(requests).toHaveLength(2))
    expect(requests[1]).toEqual(
      expect.objectContaining({
        fixedDelivery: '2026-08-10',
        typeOfService: 'professional',
        timezone: '5',
      }),
    )
  })

  test('a pending toggleRevision does not fetch after the job changes', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(({typeOfService}) =>
      HttpResponse.json(successQuotePayload({typeOfService})),
    )
    const {result, rerender} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))
    expect(requests).toHaveLength(1)

    act(() => {
      result.current.toggleRevision()
    })
    rerender(defaultOptions({enabled: false, jobId: 11, jobPassword: 'pwd11'}))
    act(() => {
      jest.runOnlyPendingTimers()
    })
    await flushNetwork()
    expect(requests).toHaveLength(1)
  })

  test('a pending toggleRevision does not fetch after unmount', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result, unmount} = renderQuoteHook()
    await waitFor(() => expect(result.current.status).toBe('ready'))

    act(() => {
      result.current.toggleRevision()
    })
    unmount()
    act(() => {
      jest.runOnlyPendingTimers()
    })
    await flushNetwork()
    expect(requests).toHaveLength(1)
  })

  test('resets status to idle when disabled while loading', async () => {
    jest.useFakeTimers(FAKE_TIMERS)
    mockQuote(() => HttpResponse.json(successQuotePayload()))
    const {result, rerender} = renderQuoteHook({debounceMs: 300})
    expect(result.current.status).toBe('loading')

    rerender(defaultOptions({debounceMs: 300, enabled: false}))
    expect(result.current.status).toBe('idle')
    act(() => {
      jest.advanceTimersByTime(300)
    })
    await flushNetwork()
    expect(requests).toHaveLength(0)
    expect(result.current.status).toBe('idle')
  })
})
