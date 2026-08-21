import headerInterface from './headerInterface'

describe('headerInterface', () => {
  let original

  beforeEach(() => {
    original = headerInterface.getMoreLinks
  })

  afterEach(() => {
    headerInterface.getMoreLinks = original
  })

  test('core adds no links, so the list renders as it stands', () => {
    expect(headerInterface.getMoreLinks()).toBeNull()
  })

  test('a replaced member is what the header renders', () => {
    headerInterface.getMoreLinks = () => 'extra'

    expect(headerInterface.getMoreLinks()).toBe('extra')
  })
})
