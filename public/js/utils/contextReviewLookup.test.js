import {findElementByMetadata} from './contextReviewLookup'

describe('findElementByMetadata', () => {
  let container

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.removeChild(container)
  })

  describe('x-tag-id', () => {
    it('finds an element by id', () => {
      container.innerHTML = '<p id="hero-title">Hello</p>'
      const el = findElementByMetadata(container, 'hero-title', 'x-tag-id')
      expect(el).toBe(container.querySelector('#hero-title'))
    })

    it('returns null when id does not exist', () => {
      container.innerHTML = '<p id="other">Hello</p>'
      expect(
        findElementByMetadata(container, 'missing-id', 'x-tag-id'),
      ).toBeNull()
    })
  })

  describe('x-css_class', () => {
    it('finds an element by class', () => {
      container.innerHTML = '<p class="hero-text">Hello</p>'
      const el = findElementByMetadata(container, 'hero-text', 'x-css_class')
      expect(el).toBe(container.querySelector('.hero-text'))
    })

    it('returns null when class does not exist', () => {
      container.innerHTML = '<p class="other">Hello</p>'
      expect(
        findElementByMetadata(container, 'missing', 'x-css_class'),
      ).toBeNull()
    })
  })

  describe('x-path', () => {
    it('finds an element via relative XPath', () => {
      container.innerHTML = '<div><p>Hello</p></div>'
      const el = findElementByMetadata(container, 'div/p', 'x-path')
      expect(el).toBe(container.querySelector('div p'))
    })

    it('returns null for an XPath that matches nothing', () => {
      container.innerHTML = '<p>Hello</p>'
      expect(findElementByMetadata(container, 'div/span', 'x-path')).toBeNull()
    })

    it('finds an element via absolute /html/body/... XPath relative to container', () => {
      // The container holds the loaded context HTML — its content is the <body>.
      // /html/body/div[2]/p[1] should resolve to the <p> inside the second <div>.
      container.innerHTML =
        '<h1>Title</h1>' +
        '<div><p>First div paragraph.</p></div>' +
        '<div><p>Second div paragraph.</p></div>'
      const el = findElementByMetadata(
        container,
        '/html/body/div[2]/p[1]',
        'x-path',
      )
      expect(el).not.toBeNull()
      expect(el.textContent).toBe('Second div paragraph.')
    })

    it('finds an element via absolute /html/body/h1 XPath', () => {
      container.innerHTML = '<h1>Title</h1><div><p>Para.</p></div>'
      const el = findElementByMetadata(container, '/html/body/h1', 'x-path')
      expect(el).not.toBeNull()
      expect(el.tagName).toBe('H1')
    })

    it('finds an element via //element[...] XPath (document-wide search)', () => {
      container.innerHTML =
        '<img id="logo" alt="Company Logo" src="logo.png" />'
      const el = findElementByMetadata(container, "//img[@id='logo']", 'x-path')
      expect(el).not.toBeNull()
      expect(el.getAttribute('id')).toBe('logo')
    })

    it('returns the owning element when XPath targets an attribute node', () => {
      // XPaths like //img/@alt resolve to an Attr node, but the function must
      // return the owner element so callers can call setAttribute/getAttribute on it.
      container.innerHTML =
        '<img id="logo" alt="Company Logo" src="logo.png" />'
      const result = findElementByMetadata(
        container,
        "//img[@id='logo']/@alt",
        'x-path',
      )
      expect(result).not.toBeNull()
      expect(result.tagName).toBe('IMG')
      expect(result.getAttribute('alt')).toBe('Company Logo')
    })
  })

  describe('x-attribute_name_value', () => {
    it('finds an element by attribute name=value', () => {
      container.innerHTML = '<p data-id="section-1">Hello</p>'
      const el = findElementByMetadata(
        container,
        'data-id=section-1',
        'x-attribute_name_value',
      )
      expect(el).toBe(container.querySelector('[data-id="section-1"]'))
    })

    it('returns null when attribute does not match', () => {
      container.innerHTML = '<p data-id="other">Hello</p>'
      expect(
        findElementByMetadata(
          container,
          'data-id=section-1',
          'x-attribute_name_value',
        ),
      ).toBeNull()
    })

    it('returns null when resname cannot be parsed as name=value', () => {
      expect(
        findElementByMetadata(
          container,
          'no-equals-sign',
          'x-attribute_name_value',
        ),
      ).toBeNull()
    })
  })

  describe('x-client_nodepath (stub)', () => {
    it('returns null (documented stub)', () => {
      container.innerHTML = '<p>Hello</p>'
      expect(
        findElementByMetadata(container, 'some/path', 'x-client_nodepath'),
      ).toBeNull()
    })
  })

  describe('null / unknown restype', () => {
    it('returns null for null restype', () => {
      container.innerHTML = '<p>Hello</p>'
      expect(findElementByMetadata(container, 'hero-title', null)).toBeNull()
    })

    it('returns null for unknown restype', () => {
      container.innerHTML = '<p>Hello</p>'
      expect(
        findElementByMetadata(container, 'hero-title', 'x-unknown'),
      ).toBeNull()
    })
  })

  describe('exception safety', () => {
    it('returns null when CSS.escape would throw (simulated bad selector)', () => {
      // Pass null resname — querySelector will receive null, should not throw
      expect(findElementByMetadata(container, null, 'x-tag-id')).toBeNull()
    })
  })
})
