import catToolInterface, {CatToolInterface} from './CatToolInterface'

describe('catToolInterface', () => {
  afterEach(() => {
    delete catToolInterface.getCharacterCounterMode
    CatToolInterface.prototype.getCharacterCounterMode = function () {}
  })

  test('core reports no character counter mode', () => {
    expect(catToolInterface.getCharacterCounterMode()).toBeUndefined()
  })

  test('a plugin assigning on the object is seen', () => {
    catToolInterface.getCharacterCounterMode = () => 'ALL_ONE'

    expect(catToolInterface.getCharacterCounterMode()).toBe('ALL_ONE')
  })

  // Plugins pinned to an older commit still patch the prototype. Both have to
  // work, or core and the plugin submodules would have to merge in the same
  // instant to avoid a broken deploy.
  test('a plugin patching the prototype is still seen', () => {
    CatToolInterface.prototype.getCharacterCounterMode = () => 'EXCLUDE_CJK'

    expect(catToolInterface.getCharacterCounterMode()).toBe('EXCLUDE_CJK')
  })
})
