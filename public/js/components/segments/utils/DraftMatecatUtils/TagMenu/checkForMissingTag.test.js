import checkForMissingTags from './checkForMissingTag'

jest.mock('../tagModel', () => ({
  getErrorCheckTag: () => ['ph', 'g', 'x', 'bx', 'ex', 'bpt', 'ept'],
}))

const makeTag = (name, id, offset = 0, decodedText = '') => ({
  offset,
  data: {name, id, decodedText},
})

// dataRef pc tags carry the pair's base id in their own `id` (e.g. "d1_1"/"d1_2");
// non-dataRef ones get a per-side, per-editor "mtc_N" id that means nothing across
// source/target, so their pairing/numbering must come from document order instead.
const makeDataRefOpen = (baseId, offset) => ({
  offset,
  data: {
    name: 'ph',
    id: `${baseId}_1`,
    encodedText: `<ph id="${baseId}_1" ctype="x-pc_open_data_ref" equiv-text="base64:AAA" x-orig="BBB"/>`,
  },
})
const makeDataRefClose = (baseId, offset) => ({
  offset,
  data: {
    name: 'ph',
    id: `${baseId}_2`,
    encodedText: `<ph id="${baseId}_2" ctype="x-pc_close_data_ref" equiv-text="base64:AAA" x-orig="CCC"/>`,
  },
})
const makeNonDataRefOpen = (id, offset) => ({
  offset,
  data: {
    name: 'ph',
    id,
    encodedText: `<ph id="${id}" ctype="x-original_pc_open" equiv-text="base64:AAA"/>`,
  },
})
const makeNonDataRefClose = (id, offset) => ({
  offset,
  data: {
    name: 'ph',
    id,
    encodedText: `<ph id="${id}" ctype="x-original_pc_close" equiv-text="base64:PC9wYz4="/>`,
  },
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
    const target = [makeTag('ph', '', 0, '<br>'), makeTag('ph', '', 5, '<br>')]
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
    const source = [makeTag('nbsp', '', 0), makeTag('ph', 'mtc_1', 5, '<p>')]
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

describe('pc (compressible) tags', () => {
  test('dataRef: flags only the pair whose closing tag is missing, not every closing tag', () => {
    const source = [
      makeDataRefOpen('d1', 0),
      makeDataRefClose('d1', 10),
      makeDataRefOpen('d2', 20),
      makeDataRefClose('d2', 30),
    ]
    // target keeps d2's pair intact, drops d1's closing tag only
    const target = [
      makeDataRefOpen('d1', 0),
      makeDataRefOpen('d2', 20),
      makeDataRefClose('d2', 30),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.id).toBe('d1_2')
  })

  test('dataRef: flags only the pair whose opening tag is missing', () => {
    const source = [
      makeDataRefOpen('d1', 0),
      makeDataRefClose('d1', 10),
      makeDataRefOpen('d2', 20),
      makeDataRefClose('d2', 30),
    ]
    const target = [
      makeDataRefClose('d1', 10),
      makeDataRefOpen('d2', 20),
      makeDataRefClose('d2', 30),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.id).toBe('d1_1')
  })

  test('non-dataRef: flags only the pair whose closing tag is missing, by document-order pairing', () => {
    const source = [
      makeNonDataRefOpen('mtc_1', 0),
      makeNonDataRefClose('mtc_2', 10),
      makeNonDataRefOpen('mtc_3', 20),
      makeNonDataRefClose('mtc_4', 30),
    ]
    // target's own ids are independently generated and irrelevant for matching;
    // only pairing order matters. Second pair's close is missing here.
    const target = [
      makeNonDataRefOpen('mtc_1', 0),
      makeNonDataRefClose('mtc_2', 10),
      makeNonDataRefOpen('mtc_3', 20),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.id).toBe('mtc_4')
  })

  test('non-dataRef: flags only the pair whose opening tag is missing', () => {
    const source = [
      makeNonDataRefOpen('mtc_1', 0),
      makeNonDataRefClose('mtc_2', 10),
      makeNonDataRefOpen('mtc_3', 20),
      makeNonDataRefClose('mtc_4', 30),
    ]
    const target = [
      makeNonDataRefClose('mtc_2', 10),
      makeNonDataRefOpen('mtc_3', 20),
      makeNonDataRefClose('mtc_4', 30),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(1)
    expect(result.missingTags[0].data.id).toBe('mtc_1')
  })

  test('pc tags intact are not flagged, and pc tags stay out of the generic id-based comparison', () => {
    const source = [
      makeDataRefOpen('d1', 0),
      makeDataRefClose('d1', 10),
      makeTag('g', 'reg1', 20),
    ]
    const target = [
      makeDataRefOpen('d1', 0),
      makeDataRefClose('d1', 10),
      makeTag('g', 'reg1', 20),
    ]
    const result = checkForMissingTags(source, target)
    expect(result.missingTags).toHaveLength(0)
    expect(result.sourceTags).toHaveLength(3)
  })
})
