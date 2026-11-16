import SegmentStringUtils from './segmentStringUtils'

describe('segmentStringUtils', () => {
  test('works on a segment string with no editor around', () => {
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
