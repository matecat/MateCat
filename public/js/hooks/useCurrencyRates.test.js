import {renderHook, act, waitFor} from '@testing-library/react'
import Cookies from 'js-cookie'
import useCurrencyRates from './useCurrencyRates'
import {getChangeRates} from '../api/getChangeRates'

jest.mock('../api/getChangeRates', () => ({
  getChangeRates: jest.fn(),
}))

describe('useCurrencyRates', () => {
  let cookiesSetSpy

  beforeEach(() => {
    Cookies.remove('matecat_changeRates')
    Cookies.remove('matecat_currency')
    getChangeRates.mockResolvedValue({data: JSON.stringify({EUR: 1})})
    // The hook always writes cookies with {secure: true}; jsdom's default
    // test origin is http://localhost, so Secure cookies never round-trip
    // through the real document.cookie jar. Spy on Cookies.set to observe
    // what the hook persisted instead of reading it back.
    cookiesSetSpy = jest.spyOn(Cookies, 'set')
  })

  afterEach(() => {
    Cookies.remove('matecat_changeRates')
    Cookies.remove('matecat_currency')
    cookiesSetSpy.mockRestore()
  })

  test('fetches and stores exchange rates when no cookie is cached', async () => {
    getChangeRates.mockResolvedValueOnce({
      data: JSON.stringify({EUR: 1, USD: 1.1}),
    })
    const {result} = renderHook(() => useCurrencyRates())

    expect(result.current.changeRates).toEqual({})

    await waitFor(() =>
      expect(result.current.changeRates).toEqual({EUR: 1, USD: 1.1}),
    )
    expect(cookiesSetSpy).toHaveBeenCalledWith(
      'matecat_changeRates',
      JSON.stringify({EUR: 1, USD: 1.1}),
      expect.objectContaining({secure: true}),
    )
  })

  test('does not refetch when rates are already cached in cookies', async () => {
    Cookies.set('matecat_changeRates', JSON.stringify({EUR: 1, GBP: 0.9}))
    const {result} = renderHook(() => useCurrencyRates())

    expect(result.current.changeRates).toEqual({EUR: 1, GBP: 0.9})
    await waitFor(() => expect(getChangeRates).not.toHaveBeenCalled())
  })

  test('treats the literal string "null" cookie as absent and refetches', async () => {
    Cookies.set('matecat_changeRates', 'null')
    getChangeRates.mockResolvedValueOnce({data: JSON.stringify({EUR: 1})})
    const {result} = renderHook(() => useCurrencyRates())

    // initial state parses to null (falsy) because JSON.parse('null') === null
    expect(result.current.changeRates).toBe(null)
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })

  test('getCurrentCurrency returns the cookie value when present and valid', async () => {
    Cookies.set('matecat_currency', 'USD')
    const {result} = renderHook(() => useCurrencyRates())
    expect(result.current.getCurrentCurrency()).toBe('USD')
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })

  test('getCurrentCurrency defaults to EUR and persists it when missing or "null"', async () => {
    const {result} = renderHook(() => useCurrencyRates())
    expect(result.current.getCurrentCurrency()).toBe('EUR')
    expect(cookiesSetSpy).toHaveBeenCalledWith(
      'matecat_currency',
      'EUR',
      expect.objectContaining({secure: true}),
    )

    Cookies.set('matecat_currency', 'null')
    expect(result.current.getCurrentCurrency()).toBe('EUR')
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })

  test('getCurrencyPrice converts using the current change rates', async () => {
    Cookies.set('matecat_changeRates', JSON.stringify({EUR: 1, USD: 2}))
    Cookies.set('matecat_currency', 'USD')
    const {result} = renderHook(() => useCurrencyRates())

    expect(result.current.getCurrencyPrice(10)).toBe('20.00')
  })

  test('getCurrencyPrice falls back to price.toString() when there are no change rates', async () => {
    Cookies.set('matecat_changeRates', 'null')
    getChangeRates.mockResolvedValueOnce({data: JSON.stringify({EUR: 1})})
    const {result} = renderHook(() => useCurrencyRates())

    // changeRates is null right after mount, before the effect resolves
    expect(result.current.getCurrencyPrice(42)).toBe('42')
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })

  test('getPriceCurrencySymbol reads the symbol from currencies, defaulting to empty string', async () => {
    const {result} = renderHook(() => useCurrencyRates())

    expect(result.current.getPriceCurrencySymbol(null)).toBe('')

    const chunkQuoteKnown = {
      get: (key) => (key === 'currency' ? 'EUR' : undefined),
    }
    expect(result.current.getPriceCurrencySymbol(chunkQuoteKnown)).not.toBe('')

    const chunkQuoteUnknown = {
      get: (key) => (key === 'currency' ? 'XYZ_UNKNOWN' : undefined),
    }
    expect(result.current.getPriceCurrencySymbol(chunkQuoteUnknown)).toBe('')
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })

  test('onCurrencyChange persists the cookie and updates chunkQuote', async () => {
    const {result} = renderHook(() => useCurrencyRates())
    const setChunkQuote = jest.fn()
    const chunkQuote = {set: jest.fn(() => 'updated-chunk')}

    act(() => {
      result.current.onCurrencyChange('USD', chunkQuote, setChunkQuote)
    })

    expect(cookiesSetSpy).toHaveBeenCalledWith(
      'matecat_currency',
      'USD',
      expect.objectContaining({secure: true}),
    )
    expect(chunkQuote.set).toHaveBeenCalledWith('currency', 'USD')
    expect(setChunkQuote).toHaveBeenCalledWith('updated-chunk')
    await waitFor(() => expect(result.current.changeRates).toEqual({EUR: 1}))
  })
})
