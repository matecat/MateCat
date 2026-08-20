import {
  can,
  defineCapability,
  listCapabilities,
  resetCapabilities,
  setCapability,
} from './capabilities'

// Names unique to this file: the registry is module state shared with the real
// manifest, and a name defined twice throws.
const PERMITTED = 'test.permitted'
const DENIED = 'test.denied'

defineCapability(PERMITTED)
defineCapability(DENIED, false)

describe('capabilities', () => {
  afterEach(() => {
    resetCapabilities()
  })

  test('a capability defaults to permitted', () => {
    expect(can(PERMITTED)).toBe(true)
  })

  test('a capability may be declared withdrawn', () => {
    expect(can(DENIED)).toBe(false)
  })

  test('defining the same capability twice throws', () => {
    expect(() => defineCapability(PERMITTED)).toThrow(
      'Capability already defined: test.permitted',
    )
  })

  test('a non-boolean default is refused', () => {
    expect(() => defineCapability('test.bogus', 'yes')).toThrow(
      'Capability test.bogus needs a boolean default',
    )
  })

  test('withdrawing a capability makes can() false', () => {
    setCapability(PERMITTED, false)
    expect(can(PERMITTED)).toBe(false)
  })

  test('granting a withdrawn capability makes can() true', () => {
    setCapability(DENIED, true)
    expect(can(DENIED)).toBe(true)
  })

  test('setting an unknown capability throws', () => {
    expect(() => setCapability('test.unknown', false)).toThrow(
      'Unknown capability: test.unknown',
    )
  })

  test('setting a capability to a non-boolean throws', () => {
    expect(() => setCapability(PERMITTED, 0)).toThrow(
      'Capability test.permitted must be set to a boolean',
    )
  })

  test('asking about an unknown capability throws', () => {
    expect(() => can('test.unknown')).toThrow(
      'Unknown capability: test.unknown',
    )
  })

  test('reset restores what core declared, not blanket permission', () => {
    setCapability(PERMITTED, false)
    setCapability(DENIED, true)

    resetCapabilities()

    expect(can(PERMITTED)).toBe(true)
    expect(can(DENIED)).toBe(false)
  })

  test('listing reports every capability and whether it is allowed', () => {
    setCapability(PERMITTED, false)

    const listed = listCapabilities()

    expect(listed).toEqual(
      expect.arrayContaining([{name: DENIED, allowed: false}]),
    )
    expect(listed).toEqual(
      expect.arrayContaining([{name: PERMITTED, allowed: false}]),
    )
  })
})
