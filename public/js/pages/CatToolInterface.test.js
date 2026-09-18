import catToolInterface from './CatToolInterface'

describe('catToolInterface', () => {
  test('core reports no character counter mode', () => {
    expect(catToolInterface.getCharacterCounterMode()).toBeUndefined()
  })

  test('a plugin can replace the character counter mode', () => {
    const core = catToolInterface.getCharacterCounterMode

    catToolInterface.getCharacterCounterMode = () => 'ALL_ONE'
    expect(catToolInterface.getCharacterCounterMode()).toBe('ALL_ONE')

    catToolInterface.getCharacterCounterMode = core
  })
})
