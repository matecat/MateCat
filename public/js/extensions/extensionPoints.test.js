import {
  defineExtensionPoint,
  extend,
  getExtensionDefault,
  listExtensionPoints,
  logExtensionPointStatus,
  registerExtension,
  resetExtensionOverrides,
} from './extensionPoints'
import {
  defineCapability,
  resetCapabilities,
  setCapability,
} from './capabilities'

// The registry keeps module-level state and each test file gets its own module
// registry, so these names are private to this file and never collide with the
// real manifest.
const A = 'test.alpha'
const B = 'test.beta'
const LOGGED = 'test.logged'

const defaultA = jest.fn(() => 'default A')
const defaultB = jest.fn((x) => `default B ${x}`)

defineExtensionPoint(A, defaultA)
defineExtensionPoint(B, defaultB)

afterEach(() => {
  resetExtensionOverrides()
  jest.clearAllMocks()
})

describe('defineExtensionPoint', () => {
  test('refuses to define the same point twice', () => {
    expect(() => defineExtensionPoint(A, () => {})).toThrow(
      'Extension point already defined: test.alpha',
    )
  })

  test('refuses a default that is not callable', () => {
    expect(() => defineExtensionPoint('test.notAFunction', {})).toThrow(
      'Extension point test.notAFunction needs a default implementation',
    )
  })
})

describe('registerExtension', () => {
  // The whole reason the registry exists: registering against a name core does
  // not define is the failure that used to be silent.
  test('throws on a name core never defined', () => {
    expect(() => registerExtension('test.doesNotExist', () => {})).toThrow(
      'Unknown extension point: test.doesNotExist',
    )
  })

  test('throws when the extension is not callable', () => {
    expect(() => registerExtension(A, 'nope')).toThrow(
      'Extension for test.alpha must be a function',
    )
  })

  test('the last registration for a name wins', () => {
    registerExtension(A, () => 'first')
    registerExtension(A, () => 'second')
    expect(extend(A)()).toBe('second')
  })
})

describe('extend', () => {
  test('calls core default when nothing is registered', () => {
    expect(extend(A)()).toBe('default A')
    expect(defaultA).toHaveBeenCalledTimes(1)
  })

  test('forwards every argument to the implementation', () => {
    expect(extend(B)('x')).toBe('default B x')
    expect(defaultB).toHaveBeenCalledWith('x')
  })

  test('calls the registered extension instead of the default', () => {
    registerExtension(A, () => 'extended')
    expect(extend(A)()).toBe('extended')
    expect(defaultA).not.toHaveBeenCalled()
  })

  // Core captures accessors at import time; extensions register at boot, which
  // is later. Resolving when the accessor is built would silently pin the
  // default.
  test('resolves on call, not when the accessor is created', () => {
    const callPoint = extend(A)
    expect(callPoint()).toBe('default A')
    registerExtension(A, () => 'extended later')
    expect(callPoint()).toBe('extended later')
  })

  test('throws when the point was never defined', () => {
    expect(() => extend('test.undefinedPoint')()).toThrow(
      'Unknown extension point: test.undefinedPoint',
    )
  })
})

describe('getExtensionDefault', () => {
  test('returns core behaviour even while an extension is registered', () => {
    registerExtension(A, () => 'extended')
    expect(getExtensionDefault(A)()).toBe('default A')
  })

  test('throws on an unknown point', () => {
    expect(() => getExtensionDefault('test.nope')).toThrow(
      'Unknown extension point: test.nope',
    )
  })
})

describe('listExtensionPoints', () => {
  test('lists every defined point, sorted, flagging the extended ones', () => {
    registerExtension(B, () => 'extended')
    expect(listExtensionPoints()).toEqual([
      {name: A, overridden: false},
      {name: B, overridden: true},
    ])
  })
})

describe('resetExtensionOverrides', () => {
  test('drops registrations but keeps the defined points', () => {
    registerExtension(A, () => 'extended')
    resetExtensionOverrides()
    expect(extend(A)()).toBe('default A')
    expect(listExtensionPoints()).toHaveLength(2)
  })
})

describe('logExtensionPointStatus', () => {
  const withNodeEnv = (value, fn) => {
    const previous = process.env.NODE_ENV
    process.env.NODE_ENV = value
    try {
      fn()
    } finally {
      process.env.NODE_ENV = previous
    }
  }

  test('says nothing outside a development build', () => {
    const info = jest.spyOn(console, 'info').mockImplementation(() => {})
    withNodeEnv('production', () => logExtensionPointStatus())
    withNodeEnv('test', () => logExtensionPointStatus())
    expect(info).not.toHaveBeenCalled()
    info.mockRestore()
  })

  test('reports which points are extended and which run core defaults', () => {
    const info = jest.spyOn(console, 'info').mockImplementation(() => {})
    registerExtension(A, () => 'extended')
    withNodeEnv('development', () => logExtensionPointStatus())

    const message = info.mock.calls[0][0]
    expect(message).toContain('2 points defined')
    expect(message).toContain(`extended: ${A}`)
    expect(message).toContain(`core default: ${B}`)
    info.mockRestore()
  })

  test('reports which capabilities have been withdrawn', () => {
    const info = jest.spyOn(console, 'info').mockImplementation(() => {})
    defineCapability(LOGGED)
    setCapability(LOGGED, false)

    withNodeEnv('development', () => logExtensionPointStatus())

    expect(
      info.mock.calls.some(([m]) =>
        m.includes(`withdrawn capabilities: ${LOGGED}`),
      ),
    ).toBe(true)
    info.mockRestore()
    resetCapabilities()
  })
})
