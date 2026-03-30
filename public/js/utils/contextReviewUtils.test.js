import {
  getSidsFromElement,
  tagSegments,
  buildSegmentNodeMap,
  getSegmentNodeMap,
  findSegmentSidsByClick,
  findSegmentSidByClick,
  updateNodeTranslation,
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
    expect(p.getAttribute('data-context-sid')).toBe('1')
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
    expect(p.getAttribute('data-context-sid')).toBe(String(sids[0]))
  })

  it('does not duplicate a SID when tagSegments is called twice', () => {
    document.body.innerHTML = '<p>Hello world</p>'
    const p = document.body.querySelector('p')
    const segs = [{sid: 1, source: 'Hello world', target: ''}]
    tagSegments(document.body, segs)
    tagSegments(document.body, segs)
    expect(getSidsFromElement(p)).toEqual([1])
  })

  it('assigns different SIDs to different elements with the same text (positional pairing)', () => {
    document.body.innerHTML = '<p>Equipment</p><p>Equipment</p>'
    const ps = document.body.querySelectorAll('p')
    tagSegments(document.body, [
      {sid: 1, source: 'Equipment', target: ''},
      {sid: 2, source: 'Equipment', target: ''},
    ])
    expect(getSidsFromElement(ps[0])).toEqual([1])
    expect(getSidsFromElement(ps[1])).toEqual([2])
  })

  it('tags the outer block when nested blocks have the same text (document order)', () => {
    document.body.innerHTML = '<div><p>Equipment</p></div>'
    const div = document.body.querySelector('div')
    const p = document.body.querySelector('p')
    tagSegments(document.body, [{sid: 1, source: 'Equipment', target: ''}])
    // In document order, <div> is encountered first and matches —
    // the <p> cannot match because the segment is already consumed.
    expect(getSidsFromElement(div)).toEqual([1])
    expect(getSidsFromElement(p)).toEqual([])
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

  it('positional pairing assigns each segment to a distinct node', () => {
    document.body.innerHTML = '<p>Equipment</p><p>Equipment</p>'
    tagSegments(document.body, [
      {sid: 1, source: 'Equipment', target: ''},
      {sid: 2, source: 'Equipment', target: ''},
    ])
    const map = buildSegmentNodeMap(document.body)
    expect(map.sidToNodeIndices.get(1)).toHaveLength(1)
    expect(map.sidToNodeIndices.get(2)).toHaveLength(1)
    expect(map.sidToNodeIndices.get(1)[0]).not.toBe(
      map.sidToNodeIndices.get(2)[0],
    )
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

describe('findSegmentSidByClick — backward compat wrapper', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('returns {sid, occurrenceIndex} wrapping the first SID and nodeIndex', () => {
    const segments = [
      {sid: 1, source: 'Hello world', target: ''},
      {sid: 2, source: 'Hello world', target: ''},
    ]
    document.body.innerHTML = '<p>Hello world</p>'
    tagSegments(document.body, segments)
    buildSegmentNodeMap(document.body)

    const p = document.body.querySelector('p')
    const result = findSegmentSidByClick(p, document.body, segments, 'source')
    expect(result).not.toBeNull()
    expect(result.sid).toBe(1)
    expect(result.occurrenceIndex).toBe(0)
  })

  it('returns null when findSegmentSidsByClick returns null', () => {
    document.body.innerHTML = '<p>Unrelated</p>'
    const p = document.body.querySelector('p')
    const result = findSegmentSidByClick(
      p,
      document.body,
      [{sid: 1, source: 'Something else', target: ''}],
      'source',
    )
    expect(result).toBeNull()
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
