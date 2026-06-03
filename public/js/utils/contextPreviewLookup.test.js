import {
  findElementByMetadata,
  walkNodePath,
  findContainerByXpath,
  ClientNodepathRegistry,
  AemContainerTextMatchStrategy,
  clientNodepathRegistry,
} from './contextPreviewLookup'

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

    it('finds an element when the attribute value contains a colon (jcr:content)', () => {
      container.innerHTML =
        '<div data-node-path="/content/we-retail/language-masters/sq-al/women/jcr:content"><strong>Women</strong></div>'
      const el = findElementByMetadata(
        container,
        'data-node-path=/content/we-retail/language-masters/sq-al/women/jcr:content',
        'x-attribute_name_value',
      )
      expect(el).toBe(
        container.querySelector(
          '[data-node-path="/content/we-retail/language-masters/sq-al/women/jcr:content"]',
        ),
      )
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

  describe('x-client_nodepath', () => {
    it('returns null when clientName and normSource are absent', () => {
      container.innerHTML = '<p>Hello</p>'
      expect(
        findElementByMetadata(container, 'some/path', 'x-client_nodepath'),
      ).toBeNull()
    })

    it('returns null when clientName is absent', () => {
      container.innerHTML =
        '<div data-node-path="/content/jcr:content"><p>Hello</p></div>'
      expect(
        findElementByMetadata(
          container,
          'data-node-path=/content/jcr:content',
          'x-client_nodepath',
          null,
          'Hello',
        ),
      ).toBeNull()
    })

    it('returns null for an unregistered clientName', () => {
      container.innerHTML =
        '<div data-node-path="/content/jcr:content"><p>Hello</p></div>'
      expect(
        findElementByMetadata(
          container,
          'data-node-path=/content/jcr:content',
          'x-client_nodepath',
          'unknown-client',
          'Hello',
        ),
      ).toBeNull()
    })

    it('resolves via AEM strategy when clientName is "AEM"', () => {
      container.innerHTML =
        '<div data-node-path="/content/jcr:content"><p>Hello</p></div>'
      const result = findElementByMetadata(
        container,
        'data-node-path=/content/jcr:content',
        'x-client_nodepath',
        'AEM',
        'Hello',
      )
      expect(result).toBe(container.querySelector('p'))
    })

    it('is case-insensitive on clientName', () => {
      container.innerHTML =
        '<div data-node-path="/content/jcr:content"><p>Hello</p></div>'
      const result = findElementByMetadata(
        container,
        'data-node-path=/content/jcr:content',
        'x-client_nodepath',
        'aem',
        'Hello',
      )
      expect(result).toBe(container.querySelector('p'))
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

describe('walkNodePath', () => {
  let container

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.removeChild(container)
  })

  it('resolves a simple direct-child path', () => {
    container.innerHTML = '<div><p>Hello</p></div>'
    const el = walkNodePath(container, 'div[0]/p[0]')
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Hello')
  })

  it('strips html[N]/body[N] prefix', () => {
    container.innerHTML = '<div><p>Target</p></div>'
    const el = walkNodePath(container, 'html[0]/body[0]/div[0]/p[0]')
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Target')
  })

  it('uses 0-based indexing to pick the correct sibling', () => {
    container.innerHTML = '<div>A</div><div>B</div><div>C</div>'
    expect(walkNodePath(container, 'div[0]').textContent).toBe('A')
    expect(walkNodePath(container, 'div[1]').textContent).toBe('B')
    expect(walkNodePath(container, 'div[2]').textContent).toBe('C')
  })

  it('handles skipped intermediate elements (descendant fallback)', () => {
    container.innerHTML =
      '<ul>' +
      '<li><custom-el><div><h3>Target</h3></div></custom-el></li>' +
      '</ul>'
    const el = walkNodePath(container, 'ul[0]/custom-el[0]/div[0]/h3[0]')
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Target')
  })

  it('resolves the Equipment.html product path structure', () => {
    container.innerHTML =
      '<div class="container">' +
      '<div class="grid">' +
      '<div class="col">' +
      '<div class="productgrid">' +
      '<div class="product-grid-container">' +
      '<ul>' +
      '<li><we-product-item><div><h3>Marin Mountain Bike Shoes</h3></div></we-product-item></li>' +
      '<li><we-product-item><div><h3>Fleet Cross-Training Shoe</h3></div></we-product-item></li>' +
      '</ul>' +
      '</div></div></div></div></div>'
    const el = walkNodePath(
      container,
      'div[0]/div[0]/div[0]/div[0]/div[0]/ul[0]/we-product-item[0]/div[0]/h3[0]',
    )
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Marin Mountain Bike Shoes')
  })

  it('picks the correct 0-based index with skipped wrappers', () => {
    container.innerHTML =
      '<ul>' +
      '<li><we-product-item><div><h3>First</h3></div></we-product-item></li>' +
      '<li><we-product-item><div><h3>Second</h3></div></we-product-item></li>' +
      '</ul>'
    const el = walkNodePath(container, 'ul[0]/we-product-item[1]/div[0]/h3[0]')
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Second')
  })

  it('returns null when index is out of bounds', () => {
    container.innerHTML = '<div>Only</div>'
    expect(walkNodePath(container, 'div[5]')).toBeNull()
  })

  it('returns null when tag name does not exist', () => {
    container.innerHTML = '<div>Hello</div>'
    expect(walkNodePath(container, 'span[0]')).toBeNull()
  })

  it('returns null for malformed step (missing index)', () => {
    container.innerHTML = '<div>Hello</div>'
    expect(walkNodePath(container, 'div')).toBeNull()
  })

  it('returns null for empty path', () => {
    expect(walkNodePath(container, '')).toBeNull()
  })

  it('returns null for null inputs', () => {
    expect(walkNodePath(null, 'div[0]')).toBeNull()
    expect(walkNodePath(container, null)).toBeNull()
  })

  it('integrates via findElementByMetadata with x-path restype', () => {
    container.innerHTML =
      '<ul><li><custom-el><h3>Found</h3></custom-el></li></ul>'
    const el = findElementByMetadata(
      container,
      'html[0]/body[0]/ul[0]/custom-el[0]/h3[0]',
      'x-path',
    )
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Found')
  })

  it('does not break standard XPath in findElementByMetadata', () => {
    container.innerHTML = '<div><p>First</p></div><div><p>Second</p></div>'
    const el = findElementByMetadata(
      container,
      '/html/body/div[2]/p[1]',
      'x-path',
    )
    expect(el).not.toBeNull()
    expect(el.textContent).toBe('Second')
  })
})

describe('findContainerByXpath', () => {
  let container

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.removeChild(container)
  })

  it('resolves /html/body/... as container-relative', () => {
    container.innerHTML = '<section id="s"><p>Text</p></section>'
    const el = findContainerByXpath(container, '/html/body/section[@id="s"]')
    expect(el).toBe(container.querySelector('section'))
  })

  it('resolves // prefix', () => {
    container.innerHTML = '<section><p id="x">X</p></section>'
    const el = findContainerByXpath(container, '//p[@id="x"]')
    expect(el).toBe(container.querySelector('#x'))
  })

  it('returns null when path matches nothing', () => {
    container.innerHTML = '<p>Hello</p>'
    expect(findContainerByXpath(container, '//missing')).toBeNull()
  })

  it('returns null on malformed XPath', () => {
    container.innerHTML = '<p>Hello</p>'
    expect(findContainerByXpath(container, '[[invalid')).toBeNull()
  })

  it('returns null when container is null', () => {
    expect(findContainerByXpath(null, '//p')).toBeNull()
  })

  it('returns null when xpath is empty', () => {
    container.innerHTML = '<p>Hello</p>'
    expect(findContainerByXpath(container, '')).toBeNull()
  })
})

describe('ClientNodepathRegistry', () => {
  let registry

  beforeEach(() => {
    registry = new ClientNodepathRegistry()
  })

  it('registers and resolves a strategy by client name', () => {
    const strategy = {execute: jest.fn()}
    registry.register('acme', strategy)
    expect(registry.resolve('acme')).toBe(strategy)
  })

  it('returns null for an unregistered client name', () => {
    expect(registry.resolve('unknown')).toBeNull()
  })

  it('overwrites a previous registration for the same name', () => {
    const s1 = {execute: jest.fn()}
    const s2 = {execute: jest.fn()}
    registry.register('acme', s1)
    registry.register('acme', s2)
    expect(registry.resolve('acme')).toBe(s2)
  })
})

describe('AemContainerTextMatchStrategy', () => {
  let container
  const strategy = new AemContainerTextMatchStrategy()

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.removeChild(container)
  })

  it('finds element whose text matches within the AEM attribute-located container', () => {
    container.innerHTML = `
      <div data-node-path="/content/we-retail/sq-al/women/jcr:content">
        <p>Target Text</p>
        <p>Other Text</p>
      </div>
      <p>Target Text</p>
    `
    const result = strategy.execute(
      container,
      'data-node-path=/content/we-retail/sq-al/women/jcr:content',
      'Target Text',
    )
    expect(result).toBe(
      container.querySelector('[data-node-path] p'),
    )
  })

  it('returns null when the attribute-located container is not found', () => {
    container.innerHTML = '<p>Target Text</p>'
    expect(
      strategy.execute(container, 'data-node-path=/missing', 'Target Text'),
    ).toBeNull()
  })

  it('returns null when no text match exists within the container', () => {
    container.innerHTML =
      '<div data-node-path="/content/jcr:content"><p>Other Text</p></div>'
    expect(
      strategy.execute(
        container,
        'data-node-path=/content/jcr:content',
        'Target Text',
      ),
    ).toBeNull()
  })

  it('returns null when path is empty', () => {
    container.innerHTML = '<p>Target Text</p>'
    expect(strategy.execute(container, '', 'Target Text')).toBeNull()
  })

  it('returns null when normSource is empty', () => {
    container.innerHTML =
      '<div data-node-path="/content/jcr:content"><p>Text</p></div>'
    expect(
      strategy.execute(container, 'data-node-path=/content/jcr:content', ''),
    ).toBeNull()
  })
})

describe('clientNodepathRegistry (module singleton)', () => {
  it('has "aem" registered by default', () => {
    expect(clientNodepathRegistry.resolve('aem')).toBeInstanceOf(
      AemContainerTextMatchStrategy,
    )
  })

  it('does not have "jcr" registered', () => {
    expect(clientNodepathRegistry.resolve('jcr')).toBeNull()
  })
})
