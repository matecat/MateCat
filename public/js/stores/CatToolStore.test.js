jest.mock('./AppDispatcher', () => ({
  dispatch: jest.fn(),
  register: jest.fn((cb) => {
    module.exports.__dispatchHandler = cb
  }),
}))

const localStorageMock = (() => {
  let store = {}
  return {
    getItem: jest.fn((key) => store[key] ?? null),
    setItem: jest.fn((key, value) => {
      store[key] = String(value)
    }),
    removeItem: jest.fn((key) => {
      delete store[key]
    }),
    clear: jest.fn(() => {
      store = {}
    }),
  }
})()

Object.defineProperty(window, 'localStorage', {value: localStorageMock})

global.config = {userMail: 'test@example.com'}

let CatToolStore
let CatToolConstants
let AppDispatcher

beforeEach(() => {
  jest.resetModules()
  localStorageMock.clear()
  localStorageMock.getItem.mockReset()
  localStorageMock.setItem.mockClear()
  document.body.classList.remove('ph-tags-compressed')
  global.config = {userMail: 'test@example.com'}
})

describe('CatToolStore - phTagsCompressed', () => {
  test('initializes phTagsCompressed to true when localStorage is empty (collapsed by default)', () => {
    CatToolStore = require('./CatToolStore').default
    expect(CatToolStore.isPhTagsCompressed()).toBe(true)
    expect(document.body.classList.contains('ph-tags-compressed')).toBe(true)
  })

  test('initializes phTagsCompressed to true from localStorage', () => {
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-test@example.com') return 'true'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    expect(CatToolStore.isPhTagsCompressed()).toBe(true)
    expect(document.body.classList.contains('ph-tags-compressed')).toBe(true)
  })

  test('initializes phTagsCompressed to false when localStorage is "false"', () => {
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-test@example.com') return 'false'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    expect(CatToolStore.isPhTagsCompressed()).toBe(false)
  })

  test('TOGGLE_PH_TAGS_COMPRESSED toggles state from false to true', () => {
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-test@example.com') return 'false'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    CatToolConstants = require('../constants/CatToolConstants').default
    AppDispatcher = require('./AppDispatcher')

    const handler = AppDispatcher.register.mock.calls[0][0]
    handler({actionType: CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED})

    expect(CatToolStore.isPhTagsCompressed()).toBe(true)
    expect(localStorageMock.setItem).toHaveBeenCalledWith(
      'phTagsCompressed-test@example.com',
      true,
    )
    expect(document.body.classList.contains('ph-tags-compressed')).toBe(true)
  })

  test('TOGGLE_PH_TAGS_COMPRESSED toggles state from true to false', () => {
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-test@example.com') return 'true'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    CatToolConstants = require('../constants/CatToolConstants').default
    AppDispatcher = require('./AppDispatcher')

    const handler = AppDispatcher.register.mock.calls[0][0]
    handler({actionType: CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED})

    expect(CatToolStore.isPhTagsCompressed()).toBe(false)
    expect(localStorageMock.setItem).toHaveBeenCalledWith(
      'phTagsCompressed-test@example.com',
      false,
    )
    expect(document.body.classList.contains('ph-tags-compressed')).toBe(false)
  })

  test('TOGGLE_PH_TAGS_COMPRESSED emits change event', () => {
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-test@example.com') return 'false'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    CatToolConstants = require('../constants/CatToolConstants').default
    AppDispatcher = require('./AppDispatcher')

    const listener = jest.fn()
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      listener,
    )

    const handler = AppDispatcher.register.mock.calls[0][0]
    handler({actionType: CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED})

    expect(listener).toHaveBeenCalledWith(true)
  })

  test('localStorage key includes userMail', () => {
    global.config = {userMail: 'other@user.com'}
    localStorageMock.getItem.mockImplementation((key) => {
      if (key === 'phTagsCompressed-other@user.com') return 'true'
      return null
    })
    CatToolStore = require('./CatToolStore').default
    expect(CatToolStore.isPhTagsCompressed()).toBe(true)
  })
})
