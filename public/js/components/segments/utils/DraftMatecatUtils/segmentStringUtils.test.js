// segmentStringUtils exists so that code rendering or checking a segment
// string without an editor (SegmentView, the measuring placeholder, the
// footer tabs) does not pull in Draft.js. Any import of draft-js along the way
// fails the module load here.
jest.mock('draft-js', () => {
  throw new Error('draft-js loaded by segmentStringUtils')
})

describe('segmentStringUtils', () => {
  test('loads without Draft.js', () => {
    expect(() => require('./segmentStringUtils')).not.toThrow()
  })

  test('works on a segment string with no editor around', () => {
    const SegmentStringUtils = require('./segmentStringUtils').default

    expect(
      SegmentStringUtils.removeTagsFromText('Ciao <g id="1">mondo</g>!'),
    ).toBe('Ciao mondo!')
    expect(
      SegmentStringUtils.autoFillTagsInTarget({
        segment: 'Hello <x id="1"/>world',
        translation: 'Ciao mondo',
      }),
    ).toContain('<x id="1"/>')
  })
})
