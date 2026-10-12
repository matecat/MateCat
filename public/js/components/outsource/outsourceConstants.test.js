import {currencies, timeOptions, formatWithCommas} from './outsourceConstants'

describe('outsourceConstants', () => {
  test('exports a currencies map with symbol and name entries', () => {
    expect(currencies.EUR).toEqual({symbol: '€', name: 'Euro (EUR)'})
    expect(currencies.USD).toEqual({symbol: 'US$', name: 'US dollar (USD)'})
    expect(Object.keys(currencies).length).toBeGreaterThan(0)
  })

  test('exports a list of time options', () => {
    expect(timeOptions.length).toBeGreaterThan(0)
    expect(timeOptions[0]).toEqual({name: '7:00 AM', id: '7'})
  })

  test('formatWithCommas formats large numbers with thousands separators', () => {
    expect(formatWithCommas(1000)).toBe('1,000')
    expect(formatWithCommas(1234567)).toBe('1,234,567')
  })

  test('formatWithCommas leaves small numbers unchanged', () => {
    expect(formatWithCommas(42)).toBe('42')
  })

  test('formatWithCommas handles decimal values', () => {
    expect(formatWithCommas('1234.56')).toBe('1,234.56')
  })
})
