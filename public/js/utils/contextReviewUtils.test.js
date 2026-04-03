import {
  getSidsFromElement,
  tagSegments,
  buildSegmentNodeMap,
  getSegmentNodeMap,
  findSegmentSidsByClick,
  updateNodeTranslation,
  extractSegmentContextFields,
} from './contextReviewUtils'

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
    })
  })

  it('returns nulls when metadata array is missing', () => {
    const seg = {context_url: null}
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: null,
      resname: null,
      restype: null,
    })
  })

  it('returns nulls when metadata array is empty', () => {
    const seg = {metadata: [], context_url: null}
    expect(extractSegmentContextFields(seg)).toEqual({
      context_url: null,
      resname: null,
      restype: null,
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
})
