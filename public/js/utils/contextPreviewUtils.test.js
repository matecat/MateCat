import {
  getSidsFromElement,
  tagSegments,
  buildSegmentNodeMap,
  getSegmentNodeMap,
  findSegmentSidsByClick,
  updateNodeTranslation,
  extractSegmentContextFields,
  isNodeHidden,
  findElementByTextMatch,
} from './contextPreviewUtils'

describe('getSidsFromElement', () => {
  it('returns [] for an element with no attribute', () => {
    const el = document.createElement('p')
    expect(getSidsFromElement(el)).toEqual([])
  })

  it('returns a single SID', () => {
    const el = document.createElement('p')
    el.setAttribute('data-context-sids', '42')
    expect(getSidsFromElement(el)).toEqual([42])
  })

  it('returns multiple SIDs', () => {
    const el = document.createElement('p')
    el.setAttribute('data-context-sids', '42,87,103')
    expect(getSidsFromElement(el)).toEqual([42, 87, 103])
  })
})

describe('tagSegments — multi-SID attribute', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('writes data-context-sids when a single segment matches a node', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('appends a second SID when two segments have the same normalised source matching the same element', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
    ])
    const sids = getSidsFromElement(p)
    expect(sids).toContain(1)
    expect(sids).toContain(2)
  })

  it('does not duplicate a SID when tagSegments is called twice', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    const segs = [{sid: 1, source: 'Hello world', target: ''}]
    tagSegments(document.body, segs)
    tagSegments(document.body, segs)
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('does not duplicate SIDs on incremental segment loading (superset call)', () => {
    document.body.innerHTML =
      '<p>Hello world</p><p>Goodbye world</p><p>Hello world</p>'
    const ps = document.body.querySelectorAll('p')
    // First call — only first batch of segments
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Goodbye world', target: ''},
    ])
    // N:N: SID 1 maps to ALL "Hello world" nodes (ps[0] and ps[2])
    expect(getSidsFromElement(ps[0])).toEqual([1])
    expect(getSidsFromElement(ps[1])).toEqual([2])
    expect(getSidsFromElement(ps[2])).toEqual([1])

    // Second call — superset with new segment added
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Goodbye world', target: ''},
      {sid: 3, source: 'Hello world', target: ''},
    ])
    // SID 3 is new and also matches both "Hello world" nodes
    expect(getSidsFromElement(ps[0])).toEqual([1, 3])
    expect(getSidsFromElement(ps[1])).toEqual([2])
    expect(getSidsFromElement(ps[2])).toEqual([1, 3])
  })

  it('does not duplicate SIDs when N:N segments arrive incrementally', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    // First call — single segment
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    expect(getSidsFromElement(p)).toEqual([1])

    // Second call — superset with second segment matching same text
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
    ])
    // SID 2 should be appended via N:N, but SID 1 must not be duplicated
    const sids = getSidsFromElement(p)
    expect(sids).toEqual([1, 2])
  })

  it('does not duplicate SIDs after three incremental calls', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
    ])
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
      {sid: 3, source: 'Hello world', target: ''},
    ])
    expect(getSidsFromElement(p)).toEqual([1, 2, 3])
  })

  it('does not duplicate SIDs with replaceWithTarget on incremental calls', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    // First call with replaceWithTarget — text becomes target
    tagSegments(
      document.body,
      [{sid: 1, source: 'Hello world', target: 'Hallo Welt'}],
      {replaceWithTarget: true},
    )
    expect(getSidsFromElement(p)).toEqual([1])
    expect(p.textContent.trim()).toBe('Hallo Welt')

    // Second call — same segments, text is now "Hallo Welt" not "Hello world"
    tagSegments(
      document.body,
      [{sid: 1, source: 'Hello world', target: 'Hallo Welt'}],
      {replaceWithTarget: true},
    )
    // SID 1 should NOT be duplicated
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('assigns different SIDs to different elements with the same text (positional pairing)', () => {
    document.body.innerHTML = '<p>Equipment</p><p>Equipment</p>'
    const ps = document.body.querySelectorAll('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Equipment', target: ''},
      {sid: 2, source: 'Equipment', target: ''},
    ])
    // Both segments match both nodes — N:N gives every node every matching SID.
    // SIDs are always sorted numerically regardless of positional pairing order.
    expect(getSidsFromElement(ps[0])).toEqual([1, 2])
    expect(getSidsFromElement(ps[1])).toEqual([1, 2])
  })

  it('maps a single segment to multiple non-nested nodes with the same text', () => {
    document.body.innerHTML =
      '<h1>Equipment</h1><p>Equipment</p><p>Equipment</p>'
    const h1 = document.body.querySelector('h1')
    const ps = document.body.querySelectorAll('p')
    tagSegments(document.body, [{sid: 1, source: 'Equipment', target: ''}])
    // The single segment should appear on ALL nodes whose text matches
    expect(getSidsFromElement(h1)).toEqual([1])
    expect(getSidsFromElement(ps[0])).toEqual([1])
    expect(getSidsFromElement(ps[1])).toEqual([1])
  })

  it('tags both outer and inner block when nested blocks have the same text (N:N)', () => {
    document.body.innerHTML = '<div><p>Equipment</p></div>'
    const div = document.body.querySelector('div')
    const p = document.body.querySelector('p')
    tagSegments(document.body, [{sid: 1, source: 'Equipment', target: ''}])
    // N:N: the segment maps to every matching node, including nested ones
    expect(getSidsFromElement(div)).toEqual([1])
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('replaces text when replaceWithTarget is true (single segment)', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(
      document.body,
      [{sid: 1, source: 'Hello world', target: 'Hallo Welt'}],
      {replaceWithTarget: true},
    )
    expect(p.textContent.trim()).toBe('Hallo Welt')
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('appends second SID in N:N even with replaceWithTarget (text replaced after tagging)', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
        {sid: 2, source: 'Hello world', target: 'Bonjour le monde'},
      ],
      {replaceWithTarget: true},
    )
    // Text replacement is deferred until after both passes, so Pass 2
    // can still match "Hello world" and append SID 2. Since the targets
    // differ, updateNodeTranslation returns 'mismatch' and text stays.
    expect(p.textContent.trim()).toBe('Hello world')
    expect(getSidsFromElement(p)).toContain(1)
    expect(getSidsFromElement(p)).toContain(2)
  })

  it('appends second SID in N:N without replaceWithTarget', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Bonjour le monde'},
    ])
    // Without replaceWithTarget, text stays "Hello world" and Pass 2
    // can match SID 2 as a second association.
    expect(p.textContent.trim()).toBe('Hello world')
    expect(getSidsFromElement(p)).toContain(1)
    expect(getSidsFromElement(p)).toContain(2)
  })
})

describe('buildSegmentNodeMap', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('maps a single segment to a single node', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    const map = buildSegmentNodeMap(document.body)
    expect(map.sidToNodeIndices.get(1)).toEqual([0])
    expect(map.nodeIndexToSids.get(0)).toEqual([1])
    expect(map.nodes).toHaveLength(1)
  })

  it('maps multiple segments to the same node', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
    ])
    const map = buildSegmentNodeMap(document.body)
    expect(map.nodeIndexToSids.get(0)).toContain(1)
    expect(map.nodeIndexToSids.get(0)).toContain(2)
    expect(map.sidToNodeIndices.get(1)).toContain(0)
    expect(map.sidToNodeIndices.get(2)).toContain(0)
  })

  it('positional pairing assigns each segment to both nodes when source matches', () => {
    document.body.innerHTML = '<p>Equipment</p><p>Equipment</p>'
    tagSegments(document.body, [
      {sid: 1, source: 'Equipment', target: ''},
      {sid: 2, source: 'Equipment', target: ''},
    ])
    const map = buildSegmentNodeMap(document.body)
    // N:N: both SIDs map to both nodes
    expect(map.sidToNodeIndices.get(1)).toEqual([0, 1])
    expect(map.sidToNodeIndices.get(2)).toEqual([0, 1])
    // SIDs are sorted numerically on every node
    expect(map.nodeIndexToSids.get(0)).toEqual([1, 2])
    expect(map.nodeIndexToSids.get(1)).toEqual([1, 2])
  })

  it('returns empty maps for a container with no tagged nodes', () => {
    document.body.innerHTML = '<p>Untagged</p>'
    const map = buildSegmentNodeMap(document.body)
    expect(map.nodes).toHaveLength(0)
    expect(map.sidToNodeIndices.size).toBe(0)
    expect(map.nodeIndexToSids.size).toBe(0)
  })
})

describe('getSegmentNodeMap', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('returns the map cached by buildSegmentNodeMap', () => {
    document.body.innerHTML = '<p>Hello</p>'
    tagSegments(document.body, [{sid: 1, source: 'Hello', target: ''}])
    const map1 = buildSegmentNodeMap(document.body)
    const map2 = getSegmentNodeMap(document.body)
    expect(map1).toBe(map2)
  })

  it('returns a non-null map after tagSegments (auto-populated cache)', () => {
    document.body.innerHTML = '<p>Hello</p>'
    tagSegments(document.body, [{sid: 1, source: 'Hello', target: ''}])
    const map = getSegmentNodeMap(document.body)
    expect(map).not.toBeNull()
    expect(map.sidToNodeIndices.get(1)).toEqual([0])
    expect(map.nodes).toHaveLength(1)
  })

  it('returns null for an untagged container', () => {
    const div = document.createElement('div')
    expect(getSegmentNodeMap(div)).toBeNull()
  })
})

describe('findSegmentSidsByClick', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  describe('Strategy 1: data-context-sids attribute', () => {
    it('returns all SIDs when clicking a multi-SID node', () => {
      const segments = [
        {sid: 1, source: 'Hello world', target: ''},
        {sid: 2, source: 'Hello world', target: ''},
      ]
      document.body.innerHTML = '<p>Hello world</p>'
      tagSegments(document.body, segments)
      buildSegmentNodeMap(document.body)

      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toContain(1)
      expect(result.sids).toContain(2)
      expect(typeof result.nodeIndex).toBe('number')
    })

    it('returns a single SID when clicking a single-SID node', () => {
      const segments = [{sid: 5, source: 'Only one', target: ''}]
      document.body.innerHTML = '<p>Only one</p>'
      tagSegments(document.body, segments)
      buildSegmentNodeMap(document.body)

      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([5])
      expect(result.nodeIndex).toBe(0)
    })

    it('returns the correct nodeIndex for the second tagged element', () => {
      const segments = [
        {sid: 1, source: 'First', target: ''},
        {sid: 2, source: 'Second', target: ''},
      ]
      document.body.innerHTML = '<p>First</p><p>Second</p>'
      tagSegments(document.body, segments)
      buildSegmentNodeMap(document.body)

      const ps = document.body.querySelectorAll('p')
      const result = findSegmentSidsByClick(
        ps[1],
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([2])
      expect(result.nodeIndex).toBe(1)
    })

    it('reads SIDs from an ancestor with data-context-sids when clicking an inline child', () => {
      const segments = [{sid: 3, source: 'Click me', target: ''}]
      document.body.innerHTML = '<p>Click me</p>'
      tagSegments(document.body, segments)
      buildSegmentNodeMap(document.body)

      // Simulate clicking the text node's parent — the <p> itself is tagged.
      // Create an inline child to click on.
      const p = document.body.querySelector('p')
      p.innerHTML = '<span>Click me</span>'
      const span = p.querySelector('span')

      const result = findSegmentSidsByClick(
        span,
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([3])
    })
  })

  describe('Strategy 2: fuzzy text match fallback', () => {
    it('returns null when clicking an element with no matching segment', () => {
      const segments = [{sid: 1, source: 'Hello world', target: ''}]
      document.body.innerHTML = '<p>Unrelated content</p>'
      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'source',
      )
      expect(result).toBeNull()
    })

    it('matches via fuzzy regex when element has no data-context-sids attribute', () => {
      const segments = [{sid: 10, source: 'Fuzzy match', target: ''}]
      // Do NOT tag segments — no attribute on DOM
      document.body.innerHTML = '<p>Fuzzy match</p>'
      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([10])
      expect(result.nodeIndex).toBe(0)
    })

    it('collects multiple SIDs via fuzzy match when several segments match', () => {
      const segments = [
        {sid: 1, source: 'Hello world', target: ''},
        {sid: 2, source: 'Hello', target: ''},
      ]
      // No tagging — both segment regexes match "Hello world"
      document.body.innerHTML = '<p>Hello world</p>'
      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toContain(1)
      expect(result.sids).toContain(2)
      expect(result.nodeIndex).toBe(0)
    })

    it('matches via fuzzy regex using the target field', () => {
      const segments = [{sid: 1, source: 'Hello world', target: 'Hallo Welt'}]
      document.body.innerHTML = '<p>Hallo Welt</p>'
      const p = document.body.querySelector('p')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        segments,
        'target',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([1])
    })
  })

  describe('edge cases', () => {
    it('returns null when segments is null', () => {
      document.body.innerHTML = '<p>Text</p>'
      const p = document.body.querySelector('p')
      expect(
        findSegmentSidsByClick(p, document.body, null, 'source'),
      ).toBeNull()
    })

    it('returns null when segments is empty', () => {
      document.body.innerHTML = '<p>Text</p>'
      const p = document.body.querySelector('p')
      expect(findSegmentSidsByClick(p, document.body, [], 'source')).toBeNull()
    })

    it('defaults nodeIndex to 0 when the node map is not built', () => {
      // Manually set the attribute without calling tagSegments/buildSegmentNodeMap
      document.body.innerHTML = '<p>Hello</p>'
      const p = document.body.querySelector('p')
      p.setAttribute('data-context-sids', '99')
      const result = findSegmentSidsByClick(
        p,
        document.body,
        [{sid: 99, source: 'Hello', target: ''}],
        'source',
      )
      expect(result).not.toBeNull()
      expect(result.sids).toEqual([99])
      expect(result.nodeIndex).toBe(0)
    })
  })
})

describe('updateNodeTranslation', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('replaces text when a single segment has a target', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })

  it('replaces text when all segment targets are identical', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Hallo Welt'},
    ])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })

  it('returns "mismatch" when targets differ', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Ciao mondo'},
    ])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Ciao mondo'},
    ])
    expect(result).toBe('mismatch')
    expect(p.textContent.trim()).toBe('Hello world')
  })

  it('returns "no-target" when a segment has no translation yet', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: ''},
    ])
    expect(result).toBe('no-target')
    expect(p.textContent.trim()).toBe('Hello world')
  })

  it('returns "no-target" for an untagged element', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('no-target')
    expect(p.textContent.trim()).toBe('Hello world')
  })

  it('returns "no-target" when segments array does not cover all SIDs on the element', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
      {sid: 2, source: 'Hello world', target: 'Hallo Welt'},
    ])
    // Only pass one of the two segments — partial coverage
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('no-target')
    expect(p.textContent.trim()).toBe('Hello world')
  })
})

describe('tagSegments with replaceWithTarget', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('replaces text when all targets agree', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
        {sid: 2, source: 'Hello world', target: 'Hallo Welt'},
      ],
      {replaceWithTarget: true},
    )
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })

  it('does NOT replace text when targets differ', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
        {sid: 2, source: 'Hello world', target: 'Ciao mondo'},
      ],
      {replaceWithTarget: true},
    )
    expect(p.textContent.trim()).toBe('Hello world')
  })
})

describe('string SID type coercion', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('tagSegments handles string SIDs from BroadcastChannel without duplicates', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    // SIDs arrive as strings from BroadcastChannel / Flux store
    tagSegments(document.body, [{sid: '1', source: 'Hello world', target: ''}])
    tagSegments(document.body, [
      {sid: '1', source: 'Hello world', target: ''},
      {sid: '2', source: 'Hello world', target: ''},
    ])
    // Must not duplicate — coercion ensures '1' matches 1 in the DOM
    expect(getSidsFromElement(p)).toEqual([1, 2])
  })

  it('updateNodeTranslation matches string SIDs against numeric DOM SIDs', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    // Segments with string SIDs (as they arrive from BroadcastChannel)
    const result = updateNodeTranslation(p, [
      {sid: '1', source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })

  it('findSegmentSidsByClick Strategy 2 returns number SIDs even when segments have string SIDs', () => {
    document.body.innerHTML = '<p>Fuzzy match</p>'
    const p = document.body.querySelector('p')
    // No tagging — triggers Strategy 2 (fuzzy match)
    const result = findSegmentSidsByClick(
      p,
      document.body,
      [{sid: '10', source: 'Fuzzy match', target: ''}],
      'source',
    )
    expect(result).not.toBeNull()
    expect(result.sids).toEqual([10])
    expect(typeof result.sids[0]).toBe('number')
  })
})

describe('extractSegmentContextFields', () => {
  it('extracts context_url, resname, restype from a plain JS segment', () => {
    const seg = {
      context_url: 'https://example.com/page.html',
      metadata: [
        {meta_key: 'resname', meta_value: 'hero-title'},
        {meta_key: 'restype', meta_value: 'x-tag-id'},
      ],
    }
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: 'https://example.com/page.html',
      resname: 'hero-title',
      restype: 'x-tag-id',
      client_name: null,
      screenshot: null,
    })
  })

  it('returns nulls when metadata array is missing', () => {
    const seg = {context_url: null}
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: null,
      resname: null,
      restype: null,
      client_name: null,
      screenshot: null,
    })
  })

  it('returns nulls when metadata array is empty', () => {
    const seg = {metadata: [], context_url: null}
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: null,
      resname: null,
      restype: null,
      client_name: null,
      screenshot: null,
    })
  })

  it('returns null for a missing meta_key', () => {
    const seg = {
      context_url: null,
      metadata: [{meta_key: 'resname', meta_value: 'my-id'}],
    }
    const result = extractSegmentContextFields(seg)
    expect(result.resname).toBe('my-id')
    expect(result.restype).toBeNull()
  })

  it('extracts screenshot URL from metadata', () => {
    const seg = {
      context_url: 'https://example.com/page.html',
      metadata: [
        {meta_key: 'resname', meta_value: 'hero-title'},
        {meta_key: 'restype', meta_value: 'x-tag-id'},
        {
          meta_key: 'screenshot',
          meta_value: 'https://example.com/screenshot.png',
        },
      ],
    }
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: 'https://example.com/page.html',
      resname: 'hero-title',
      restype: 'x-tag-id',
      client_name: null,
      screenshot: 'https://example.com/screenshot.png',
    })
  })

  it('returns null for screenshot when not in metadata', () => {
    const seg = {
      context_url: null,
      metadata: [{meta_key: 'resname', meta_value: 'my-id'}],
    }
    const result = extractSegmentContextFields(seg)
    expect(result.screenshot).toBeNull()
  })

  it('extracts x-client-name from metadata as client_name', () => {
    const seg = {
      context_url: null,
      metadata: [
        {meta_key: 'resname', meta_value: 'data-node-path=/content/jcr:content'},
        {meta_key: 'restype', meta_value: 'x-client_nodepath'},
        {meta_key: 'x-client-name', meta_value: 'AEM'},
      ],
    }
    const result = extractSegmentContextFields(seg)
    expect(result.client_name).toBe('AEM')
    expect(result.restype).toBe('x-client_nodepath')
  })
})

describe('tagSegments — strategy pass (metadataMap)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('tags an element by id when metadataMap has x-tag-id for that segment', () => {
    document.body.innerHTML = '<p id="hero">Some text</p><p>Other text</p>'
    const heroEl = document.body.querySelector('#hero')
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Some text', target: ''},
        {sid: 2, source: 'Other text', target: ''},
      ],
      {metadataMap: {1: {resname: 'hero', restype: 'x-tag-id'}}},
    )
    // Strategy resolved SID 1 to #hero
    expect(getSidsFromElement(heroEl)).toContain(1)
  })

  it('tags by id even when element text does not match segment source', () => {
    // Text-match alone would never tag #hero because "Different content" !== "segment source"
    document.body.innerHTML = '<p id="hero">Different content</p>'
    const heroEl = document.body.querySelector('#hero')
    tagSegments(
      document.body,
      [{sid: 1, source: 'segment source', target: ''}],
      {metadataMap: {1: {resname: 'hero', restype: 'x-tag-id'}}},
    )
    expect(getSidsFromElement(heroEl)).toContain(1)
  })

  it('strategy-resolved node is not re-assigned by text-match to a different SID exclusively', () => {
    // SID 1 is strategy-resolved to #hero.
    // SID 2 has matching text but no strategy — it goes through text-match.
    document.body.innerHTML = '<p id="hero">Same text</p><p>Same text</p>'
    const heroEl = document.body.querySelector('#hero')
    const otherEl = document.body.querySelectorAll('p')[1]
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Same text', target: ''},
        {sid: 2, source: 'Same text', target: ''},
      ],
      {metadataMap: {1: {resname: 'hero', restype: 'x-tag-id'}}},
    )
    // Both nodes should have SID 1 (strategy + N:N); otherEl should also have SID 2
    expect(getSidsFromElement(heroEl)).toContain(1)
    expect(getSidsFromElement(otherEl)).toContain(2)
  })

  it('falls through to text-match when strategy lookup returns null (fallback queue)', () => {
    document.body.innerHTML = '<p>Fallback text</p>'
    const p = document.body.querySelector('p')
    tagSegments(
      document.body,
      [{sid: 1, source: 'Fallback text', target: ''}],
      {metadataMap: {1: {resname: 'nonexistent-id', restype: 'x-tag-id'}}},
    )
    // Strategy missed; text-match should have tagged it
    expect(getSidsFromElement(p)).toContain(1)
  })

  it('ignores metadataMap entries where resname or restype is missing', () => {
    document.body.innerHTML = '<p id="hero">Hello</p>'
    tagSegments(document.body, [{sid: 1, source: 'Hello', target: ''}], {
      metadataMap: {1: {resname: null, restype: 'x-tag-id'}},
    })
    // Should fall through to text-match, still tag the node
    const heroEl = document.body.querySelector('#hero')
    expect(getSidsFromElement(heroEl)).toContain(1)
  })

  it('tier1 element is not re-tagged by text-match on incremental calls', () => {
    // SID 1 is strategy-resolved to #hero on the first call.
    // On the second call, SID 2 has the same text as #hero.
    // #hero must NOT get SID 2 appended to it.
    document.body.innerHTML =
      '<p id="hero">Same text</p><p>Same text</p><p>Other</p>'
    const heroEl = document.body.querySelector('#hero')
    const dupEl = document.body.querySelectorAll('p')[1]

    // First call: strategy resolves SID 1 to #hero
    tagSegments(document.body, [{sid: 1, source: 'Same text', target: ''}], {
      metadataMap: {1: {resname: 'hero', restype: 'x-tag-id'}},
    })
    expect(getSidsFromElement(heroEl)).toEqual([1])

    // Second incremental call: SID 2 has same text as #hero but no strategy.
    // #hero is already tagged (alreadyTagged); the tier1Nodes fix must protect it.
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Same text', target: ''},
        {sid: 2, source: 'Same text', target: ''},
      ],
      {metadataMap: {1: {resname: 'hero', restype: 'x-tag-id'}}},
    )
    // #hero must still only have SID 1 — SID 2 must NOT be appended
    expect(getSidsFromElement(heroEl)).toEqual([1])
    // SID 2 should be tagged on the duplicate-text element via text-match
    expect(getSidsFromElement(dupEl)).toContain(2)
  })

  it('evicts text-matched SIDs when a point-mapped segment claims the node on an incremental call', () => {
    // Simulates the race condition: first call has no metadata, so SID 1
    // is text-matched to #hero. Second call brings SID 2 with point
    // mapping to #hero — SID 1 must be evicted.
    document.body.innerHTML =
      '<p id="hero">Hello world</p><p>Hello world</p>'
    const heroEl = document.body.querySelector('#hero')
    const otherEl = document.body.querySelectorAll('p')[1]

    // First call: no metadataMap — SID 1 text-matches #hero
    tagSegments(document.body, [{sid: 1, source: 'Hello world', target: ''}])
    expect(getSidsFromElement(heroEl)).toContain(1)

    // Second call: SID 2 has point mapping to #hero via x-tag-id
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Hello world', target: ''},
        {sid: 2, source: 'Different source', target: ''},
      ],
      {metadataMap: {2: {resname: 'hero', restype: 'x-tag-id'}}},
    )
    // SID 1 must be evicted from #hero; only SID 2 remains
    expect(getSidsFromElement(heroEl)).toEqual([2])
    // SID 1 should re-match to the other <p> via text-match
    expect(getSidsFromElement(otherEl)).toContain(1)
  })

  it('keeps point-mapped SIDs and only evicts text-matched ones when multiple point SIDs target same node', () => {
    document.body.innerHTML = '<p id="hero">Hello</p><p>Hello</p>'
    const heroEl = document.body.querySelector('#hero')

    // First call: SID 1 text-matches #hero
    tagSegments(document.body, [{sid: 1, source: 'Hello', target: ''}])
    expect(getSidsFromElement(heroEl)).toContain(1)

    // Second call: SID 2 and SID 3 both point-map to #hero
    tagSegments(
      document.body,
      [
        {sid: 1, source: 'Hello', target: ''},
        {sid: 2, source: 'X', target: ''},
        {sid: 3, source: 'Y', target: ''},
      ],
      {
        metadataMap: {
          2: {resname: 'hero', restype: 'x-tag-id'},
          3: {resname: 'hero', restype: 'x-tag-id'},
        },
      },
    )
    // Only the point-mapped SIDs survive on #hero
    expect(getSidsFromElement(heroEl)).toEqual([2, 3])
    expect(getSidsFromElement(heroEl)).not.toContain(1)
  })
})

describe('updateNodeTranslation — internal_id grouping (split trans-units)', () => {
  const SEGMENT_SIDS_ATTR = 'data-context-sids'

  beforeEach(() => {
    document.body.innerHTML = ''
  })

  const tagWithSids = (el, sids) => {
    el.setAttribute(SEGMENT_SIDS_ATTR, sids.join(','))
  }

  it('replaces text with concatenated translations when two segments share internal_id', () => {
    document.body.innerHTML = '<p>First part second part</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1, 2])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'First part', target: 'Erster Teil', internal_id: 'tu1'},
      {sid: 2, source: 'second part', target: 'zweiter Teil', internal_id: 'tu1'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Erster Teil zweiter Teil')
  })

  it('returns no-target when one segment in a split group has no translation', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1, 2])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello', target: 'Hallo', internal_id: 'tu1'},
      {sid: 2, source: 'world', target: '', internal_id: 'tu1'},
    ])
    expect(result).toBe('no-target')
    expect(p.textContent.trim()).toBe('Hello world')
  })

  it('returns no-target when one segment in a split group has null translation', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1, 2])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello', target: 'Hallo', internal_id: 'tu1'},
      {sid: 2, source: 'world', target: null, internal_id: 'tu1'},
    ])
    expect(result).toBe('no-target')
  })

  it('sorts split segments by SID before concatenating', () => {
    document.body.innerHTML = '<p>A B C</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [10, 20, 30])
    const result = updateNodeTranslation(p, [
      {sid: 30, source: 'C', target: 'Cee', internal_id: 'tu1'},
      {sid: 10, source: 'A', target: 'Ay', internal_id: 'tu1'},
      {sid: 20, source: 'B', target: 'Bee', internal_id: 'tu1'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Ay Bee Cee')
  })

  it('preserves mismatch detection for duplicate text nodes (different internal_id)', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1, 2])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt', internal_id: 'tu1'},
      {sid: 2, source: 'Hello world', target: 'Ciao mondo', internal_id: 'tu2'},
    ])
    expect(result).toBe('mismatch')
    expect(p.textContent.trim()).toBe('Hello world')
  })

  it('replaces text when duplicate text nodes have identical translations (different internal_id)', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1, 2])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt', internal_id: 'tu1'},
      {sid: 2, source: 'Hello world', target: 'Hallo Welt', internal_id: 'tu2'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })

  it('segments without internal_id are treated as independent groups', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    tagWithSids(p, [1])
    const result = updateNodeTranslation(p, [
      {sid: 1, source: 'Hello world', target: 'Hallo Welt'},
    ])
    expect(result).toBe('ok')
    expect(p.textContent.trim()).toBe('Hallo Welt')
  })
})

// ---------------------------------------------------------------------------
// isNodeHidden
// ---------------------------------------------------------------------------

describe('isNodeHidden', () => {
  it('returns true for null element', () => {
    expect(isNodeHidden(null)).toBe(true)
  })

  it('returns false for a visible element', () => {
    document.body.innerHTML = '<p>Visible</p>'
    const p = document.body.querySelector('p')
    expect(isNodeHidden(p)).toBe(false)
  })

  it('returns true when display is none', () => {
    document.body.innerHTML = '<p style="display:none">Hidden</p>'
    const p = document.body.querySelector('p')
    expect(isNodeHidden(p)).toBe(true)
  })

  it('returns true when visibility is hidden', () => {
    document.body.innerHTML = '<p style="visibility:hidden">Hidden</p>'
    const p = document.body.querySelector('p')
    expect(isNodeHidden(p)).toBe(true)
  })

  it('returns true when opacity is 0', () => {
    document.body.innerHTML = '<p style="opacity:0">Hidden</p>'
    const p = document.body.querySelector('p')
    expect(isNodeHidden(p)).toBe(true)
  })

  it('detects hidden ancestor via checkVisibility', () => {
    document.body.innerHTML = '<div style="display:none"><p>Nested</p></div>'
    const p = document.body.querySelector('p')
    p.checkVisibility = jest.fn(() => false)
    expect(isNodeHidden(p)).toBe(true)
  })

  it('uses checkVisibility when available', () => {
    document.body.innerHTML = '<p>Test</p>'
    const p = document.body.querySelector('p')
    p.checkVisibility = jest.fn(() => false)
    expect(isNodeHidden(p)).toBe(true)
    expect(p.checkVisibility).toHaveBeenCalledWith({
      checkOpacity: true,
      checkVisibilityCSS: true,
    })
  })

  it('uses checkVisibility returning true means visible', () => {
    document.body.innerHTML = '<p>Test</p>'
    const p = document.body.querySelector('p')
    p.checkVisibility = jest.fn(() => true)
    expect(isNodeHidden(p)).toBe(false)
  })
})

// ─── findElementByTextMatch ──────────────────────────────────────────────────

describe('findElementByTextMatch', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('finds the first block element matching text exactly (case-insensitive)', () => {
    document.body.innerHTML = '<p>Hello World</p><p>Other</p>'
    expect(findElementByTextMatch(document.body, 'hello world')).toBe(
      document.body.querySelector('p'),
    )
  })

  it('normalises whitespace before comparing', () => {
    document.body.innerHTML = '<p>Hello   World</p>'
    expect(findElementByTextMatch(document.body, 'Hello World')).toBe(
      document.body.querySelector('p'),
    )
  })

  it('returns null when no element matches', () => {
    document.body.innerHTML = '<p>Hello</p>'
    expect(findElementByTextMatch(document.body, 'Goodbye')).toBeNull()
  })

  it('skips elements that already carry a data-context-sids attribute', () => {
    document.body.innerHTML = `
      <p data-context-sids="1">Match</p>
      <p>Match</p>
    `
    const result = findElementByTextMatch(document.body, 'Match')
    expect(result).toBe(document.body.querySelectorAll('p')[1])
  })

  it('skips elements whose descendant is already tagged', () => {
    document.body.innerHTML = `
      <div>Match <span data-context-sids="1">x</span></div>
      <p>Match</p>
    `
    expect(findElementByTextMatch(document.body, 'Match')).toBe(
      document.body.querySelector('p'),
    )
  })

  it('returns null when container is null', () => {
    expect(findElementByTextMatch(null, 'text')).toBeNull()
  })

  it('returns null when searchText is empty', () => {
    document.body.innerHTML = '<p>text</p>'
    expect(findElementByTextMatch(document.body, '')).toBeNull()
  })

  it('returns null when searchText is whitespace only', () => {
    document.body.innerHTML = '<p>text</p>'
    expect(findElementByTextMatch(document.body, '   ')).toBeNull()
  })
})

describe('tagSegments — AEM x-client_nodepath dispatch', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('tags child block element matching source text within the attribute-located container', () => {
    document.body.innerHTML = `
      <div data-node-path="/content/we-retail/jcr:content">
        <p>Segment Source</p>
        <p>Other</p>
      </div>
    `
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/we-retail/jcr:content',
            restype: 'x-client_nodepath',
            client_name: 'AEM',
          },
        },
      },
    )
    expect(getSidsFromElement(document.body.querySelector('p'))).toContain(1)
    expect(getSidsFromElement(document.body.querySelectorAll('p')[1])).not.toContain(1)
    expect(
      getSidsFromElement(document.body.querySelector('[data-node-path]')),
    ).not.toContain(1)
  })

  it('falls back to standard text-match (pass 2) when text not found in container', () => {
    document.body.innerHTML = `
      <div data-node-path="/content/we-retail/jcr:content">
        <p>Wrong Text</p>
      </div>
      <p>Segment Source</p>
    `
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/we-retail/jcr:content',
            restype: 'x-client_nodepath',
            client_name: 'AEM',
          },
        },
      },
    )
    expect(getSidsFromElement(document.body.querySelectorAll('p')[1])).toContain(1)
  })

  it('falls back when container element not found', () => {
    document.body.innerHTML = '<p>Segment Source</p>'
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/missing',
            restype: 'x-client_nodepath',
            client_name: 'AEM',
          },
        },
      },
    )
    expect(getSidsFromElement(document.body.querySelector('p'))).toContain(1)
  })

  it('falls back to pass 2 text-match when client_name is absent', () => {
    document.body.innerHTML = `
      <div data-node-path="/content/we-retail/jcr:content">
        <p>Segment Source</p>
      </div>
    `
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/we-retail/jcr:content',
            restype: 'x-client_nodepath',
          },
        },
      },
    )
    const tagged = document.body.querySelector('[data-context-sids]')
    expect(tagged).not.toBeNull()
    expect(getSidsFromElement(tagged)).toContain(1)
  })

  it('is case-insensitive on client_name ("aem" == "AEM")', () => {
    document.body.innerHTML = `
      <div data-node-path="/content/jcr:content">
        <p>Hello</p>
      </div>
    `
    tagSegments(
      document.body,
      [{sid: 1, source: 'Hello', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/jcr:content',
            restype: 'x-client_nodepath',
            client_name: 'aem',
          },
        },
      },
    )
    expect(getSidsFromElement(document.body.querySelector('p'))).toContain(1)
  })
})

describe('tagSegments — x-attribute_name_value without client_name (plain selector)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('tags the matched element directly (no text-match)', () => {
    document.body.innerHTML = `
      <div data-node-path="/content/jcr:content">
        <p>Segment Source</p>
        <p>Other</p>
      </div>
    `
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/jcr:content',
            restype: 'x-attribute_name_value',
          },
        },
      },
    )
    expect(
      getSidsFromElement(document.body.querySelector('[data-node-path]')),
    ).toContain(1)
    expect(getSidsFromElement(document.body.querySelector('p'))).not.toContain(1)
  })

  it('falls back when container element not found', () => {
    document.body.innerHTML = '<p>Segment Source</p>'
    tagSegments(
      document.body,
      [{sid: 1, source: 'Segment Source', target: ''}],
      {
        metadataMap: {
          1: {
            resname: 'data-node-path=/content/missing',
            restype: 'x-attribute_name_value',
          },
        },
      },
    )
    expect(getSidsFromElement(document.body.querySelector('p'))).toContain(1)
  })
})
