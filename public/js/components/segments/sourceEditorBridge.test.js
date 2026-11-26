import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'
import {publishSource, tagIdentities} from './sourceEditorBridge'
import SegmentActions from '../../actions/SegmentActions'

jest.mock('../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {updateSource: jest.fn()},
}))

setTagSignatureMiddleware('space', () => false)

beforeEach(() => jest.clearAllMocks())

describe('publishSource (what SegmentSource pushes into the store)', () => {
  test('pushes the decoded source, its plain text, the tag map and the Lexiqa text', () => {
    publishSource('10', 'Hello <g id="1">world</g> &amp; more')
    expect(SegmentActions.updateSource).toHaveBeenCalledTimes(1)
    const [sid, decoded, plain, tagMap, lexiqaText] =
      SegmentActions.updateSource.mock.calls[0]
    expect(sid).toBe('10')
    expect(decoded).toBe('Hello <g id="1">world</g> &amp; more')
    expect(plain).toContain('world')
    expect(plain).not.toContain('<g')
    expect(tagMap.map(({data}) => [data.name, data.id])).toEqual([
      ['g', '1'],
      ['gCl', '1'],
    ])
    expect(lexiqaText).toBe('Hello <1>world<1> & more')
  })

  test('an empty source is not pushed', () => {
    publishSource('10', '')
    expect(SegmentActions.updateSource).not.toHaveBeenCalled()
  })
})

describe('tagIdentities (what a click highlights in the Draft target)', () => {
  test('an opening g and its closing g share id and placeholder, so a click highlights both', () => {
    const [open, close] = tagIdentities('a <g id="7">b</g> c')
    expect(open).toEqual({raw: '<g id="7">', id: '7', placeholder: '7'})
    expect(close).toEqual({raw: '</g>', id: '7', placeholder: '7'})
  })

  test('tags come in document order with their own ids, whitespace tags left out', () => {
    expect(
      tagIdentities('<x id="2"/>a##$_A0$##b<x id="1"/>').map(({raw, id}) => [
        raw,
        id,
      ]),
    ).toEqual([
      ['<x id="2"/>', '2'],
      ['<x id="1"/>', '1'],
    ])
  })
})
