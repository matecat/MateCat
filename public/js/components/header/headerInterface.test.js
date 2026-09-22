import headerInterface, {HeaderInterface} from './headerInterface'

describe('headerInterface', () => {
  afterEach(() => {
    delete headerInterface.getMoreLinks
    HeaderInterface.prototype.getMoreLinks = function () {}
  })

  test('core adds no extra links', () => {
    expect(headerInterface.getMoreLinks()).toBeUndefined()
  })

  test('a plugin assigning on the object is seen', () => {
    headerInterface.getMoreLinks = () => 'aligner link'

    expect(headerInterface.getMoreLinks()).toBe('aligner link')
  })

  // The aligner plugin still patches the prototype until its own PR merges.
  test('a plugin patching the prototype is still seen', () => {
    HeaderInterface.prototype.getMoreLinks = () => 'legacy link'

    expect(headerInterface.getMoreLinks()).toBe('legacy link')
  })
})
