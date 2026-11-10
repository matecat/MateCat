// The CodeMirror editor and the segment view must keep working once Draft.js is
// removed. If anything they import pulls in draft-js, these tests fail at load.
jest.mock('draft-js', () => {
  throw new Error('draft-js was loaded by code that must not depend on it')
})

const SEGMENT =
  'Click <g id="1">Save</g> &amp; keep##$_A0$##it<x id="2"/>##$_0A$##line 2'

describe('code independent of Draft.js', () => {
  test('the editor core loads and round-trips a segment without draft-js', () => {
    const {createSegmentState} = require('./createSegmentEditor')
    const {serialize, tagList} = require('./docModel')

    const state = createSegmentState(SEGMENT, {mode: 'target'})

    expect(serialize(state)).toBe(SEGMENT)
    expect(tagList(state).length).toBe(3)
  })

  test('the segment view model loads and reads tags without draft-js', () => {
    const {tokenize} = require('../../segmentView/segmentModel')
    // Visible spaces off, as for a user with the default editor settings
    require('../../../utils/segmentText/tagModel').initTagSignature({
      show_whitespace: 0,
    })

    const tags = tokenize(SEGMENT).filter((token) => token.type === 'tag')

    expect(tags.map((tag) => tag.name)).toEqual([
      'g',
      'gCl',
      'nbsp',
      'x',
      'lineFeed',
    ])
  })

  test('the CodeMirror source editor and its layers load without draft-js', () => {
    expect(() => require('../matecat/SourceEditor')).not.toThrow()
    expect(() => require('../matecat/editorEngine')).not.toThrow()
  })

  test('the segment view component loads without draft-js', () => {
    expect(() => require('../../segmentView/SegmentView')).not.toThrow()
  })
})
