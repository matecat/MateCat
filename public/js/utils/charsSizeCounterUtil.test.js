import {
  CHARS_SIZE_COUNTER_TYPES,
  charsSizeCounter,
  getDefaultCharsSize,
} from './charsSizeCounterUtil'

afterEach(() => {
  charsSizeCounter.map = undefined
})

test('exposes the supported counter type keys', () => {
  expect(CHARS_SIZE_COUNTER_TYPES).toEqual({
    GOOGLE_ADS: 'google_ads',
    EXCLUDE_CJK: 'exclude_cjk',
    ALL_ONE: 'all_one',
  })
})

test('getDefaultCharsSize returns the plain character length', () => {
  expect(getDefaultCharsSize('hello')).toBe(5)
  expect(getDefaultCharsSize('')).toBe(0)
})

test('charsSizeCounter.map defaults to the google_ads counter type', () => {
  expect(charsSizeCounter.map.default('hello')).toBe(5)
  expect(charsSizeCounter.map.custom).toHaveLength(6)
})

test('charsSizeCounter.map switches to the requested counter type', () => {
  charsSizeCounter.map = CHARS_SIZE_COUNTER_TYPES.ALL_ONE

  expect(charsSizeCounter.map.default('hello')).toBe(5)
  expect(charsSizeCounter.map.custom).toBeUndefined()
})

test('google_ads custom matchers detect CJK, Armenian, Georgian, Sinhala, emoji, and fullwidth characters', () => {
  charsSizeCounter.map = CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS
  const [cjk, armenian, georgian, sinhala, emoji, fullwidth] =
    charsSizeCounter.map.custom

  expect(cjk('a中b')).toEqual([
    expect.objectContaining({match: '中', index: 1, size: 2}),
  ])
  expect(armenian('aԱb')).toEqual([
    expect.objectContaining({match: 'Ա', index: 1, size: 2}),
  ])
  expect(georgian('aႠb')).toEqual([
    expect.objectContaining({match: 'Ⴀ', index: 1, size: 2}),
  ])
  expect(sinhala('a඀b')).toEqual([
    expect.objectContaining({match: '඀', index: 1, size: 2}),
  ])
  expect(emoji('a✅b')).toEqual([
    expect.objectContaining({match: '✅', index: 1, size: 2}),
  ])
  expect(fullwidth('aＡb')).toEqual([
    expect.objectContaining({match: 'Ａ', index: 1, size: 2}),
  ])

  expect(cjk('plain text')).toEqual([])
})

test('exclude_cjk custom matchers detect CJK and fullwidth characters', () => {
  charsSizeCounter.map = CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK
  const [cjk, fullwidth] = charsSizeCounter.map.custom

  expect(cjk('a中b')).toEqual([
    expect.objectContaining({match: '中', index: 1}),
  ])
  expect(fullwidth('aＡb')).toEqual([
    expect.objectContaining({match: 'Ａ', index: 1}),
  ])
})
