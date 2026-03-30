import {getSidsFromElement, tagSegments} from './contextReviewUtils'

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
})
