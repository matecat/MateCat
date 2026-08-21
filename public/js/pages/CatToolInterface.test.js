import catToolInterface from './CatToolInterface'

describe('catToolInterface', () => {
  let original

  beforeEach(() => {
    original = catToolInterface.getCharacterCounterMode
  })

  afterEach(() => {
    catToolInterface.getCharacterCounterMode = original
  })

  test('core ships no preset, so the job or the template decides', () => {
    expect(catToolInterface.getCharacterCounterMode()).toBeUndefined()
  })

  test('a replaced member is what callers get', () => {
    catToolInterface.getCharacterCounterMode = () => 'exclude_cjk'

    expect(catToolInterface.getCharacterCounterMode()).toBe('exclude_cjk')
  })

  test('a member can wrap the one already installed', () => {
    const previous = catToolInterface.getCharacterCounterMode
    catToolInterface.getCharacterCounterMode = () => previous() ?? 'all_one'

    expect(catToolInterface.getCharacterCounterMode()).toBe('all_one')
  })
})
