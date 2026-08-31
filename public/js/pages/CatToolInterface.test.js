import {CatToolInterface} from './CatToolInterface'

describe('CatToolInterface', () => {
  test('instantiates and calls getCharacterCounterMode without error', () => {
    const instance = new CatToolInterface()
    expect(instance.getCharacterCounterMode()).toBeUndefined()
  })

  test('inherits props behavior from ComponentExtendInterface', () => {
    const instance = new CatToolInterface()
    instance.props = {foo: 'bar'}
    expect(instance.foo).toBe('bar')
  })
})
