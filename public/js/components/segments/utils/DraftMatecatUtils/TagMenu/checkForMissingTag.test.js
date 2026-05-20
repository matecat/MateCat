import checkForMissingTags from './checkForMissingTag'

jest.mock('../tagModel', () => ({
  getErrorCheckTag: () => ['ph', 'g', 'x', 'bx', 'ex', 'bpt', 'ept'],
}))

const makeTag = (name, id, offset = 0, decodedText = '') => ({
  offset,
  data: {name, id, decodedText},
})

describe('checkForMissingTags', () => {
  test('returns empty arrays when sourceTagMap is null', () => {
    const result = checkForMissingTags(null, [])
    expect(result).toEqual({missingTags: [], sourceTags: []})
  })

  test('returns empty arrays when sourceTagMap is undefined', () => {
    const result = checkForMissingTags(undefined, [])
    expect(result).toEqual({missingTags: [], sourceTags: []})
  })

  test('returns all source tags as missing when target is empty', () => {
    const source = [makeTag('g', '1', 0), makeTag('g', '2', 5)]
    const result = checkForMissingTags(source, [])
    expect(result.missingTags).toHaveLength(2)
    expect(result.sourceTags).toHaveLength(2)
  })

  test('returns no missing tags when target has all source tags', () => {
    const source = [makeTag('g', '1', 0), makeTag('g', '2', 5)]
    const target = [makeTag('g', '1', 0), makeTag('g', '2', 5)]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(0)
  })

  test('ph tags match by decodedText, not by id', () => {
    const source = [
      makeTag('ph', 'mtc_1', 0, '<p>'),
      makeTag('ph', 'mtc_2', 5, '<strong>'),
    ]
    const target = [
      makeTag('ph', 'mtc_99', 0, '<p>'),
      makeTag('ph', 'mtc_100', 5, '<strong>'),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(0)
  })

  test('ph tags with different decodedText are reported as missing', () => {
    const source = [
      makeTag('ph', 'mtc_1', 0, '<p>'),
      makeTag('ph', 'mtc_2', 5, '<strong>'),
    ]
    const target = [makeTag('ph', 'mtc_1', 0, '<p>')]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.decodedText).toBe('<strong>')
  })

  test('duplicate ph tags consume matches one-by-one (splice pattern)', () => {
    const source = [
      makeTag('ph', '', 0, '<br>'),
      makeTag('ph', '', 5, '<br>'),
      makeTag('ph', '', 10, '<br>'),
    ]
    const target = [
      makeTag('ph', '', 0, '<br>'),
      makeTag('ph', '', 5, '<br>'),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.decodedText).toBe('<br>')
  })

  test('non-ph tags match by id and name', () => {
    const source = [makeTag('g', '1', 0), makeTag('bx', '2', 5)]
    const target = [makeTag('g', '1', 0)]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.name).toBe('bx')
  })

  test('filters out non-error-check tags (nbsp, tab, etc.)', () => {
    const source = [
      makeTag('nbsp', '', 0),
      makeTag('ph', 'mtc_1', 5, '<p>'),
    ]
    const target = []
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.name).toBe('ph')
  })

  test('handles null targetTagMap gracefully', () => {
    const source = [makeTag('ph', 'mtc_1', 0, '<p>')]
    const result = checkForMissingTags(source, null)
    expect(result.missingTags).toHaveLength(1)
  })

  test('results are sorted by offset', () => {
    const source = [
      makeTag('g', '2', 10),
      makeTag('g', '1', 0),
      makeTag('g', '3', 5),
    ]
    const result = checkForMissingTags(source, [])
    expect(result.missingTags[0].offset).toBe(0)
    expect(result.missingTags[1].offset).toBe(5)
    expect(result.missingTags[2].offset).toBe(10)
  })
})
