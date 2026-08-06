import TagEntity from './TagEntity.component'

jest.mock('../../../stores/CatToolStore', () => ({
  isPhTagsCompressed: () => true,
  addListener: () => {},
  removeListener: () => {},
}))

const dataRefOpenD1 =
  '<ph id="d1_1" ctype="x-pc_open_data_ref" equiv-text="base64:AAA" x-orig="BBB"/>'
const nonPcTag = '<ph id="mtc_9" equiv-text="base64:LLL"/>'

const makeContentState = (data) => ({
  getEntity: () => ({data}),
})

const makeInstance = (props) => {
  const contentState = makeContentState(props.entityData)
  return new TagEntity({
    entityKey: '1',
    contentState,
    getSearchParams: () => ({active: false}),
    getUpdatedSegmentInfo: () => ({
      tagMismatch: props.tagMismatch,
      segmentOpened: true,
      missingTagsInTarget: props.missingTagsInTarget,
    }),
    isTarget: props.isTarget ?? false,
    isRTL: false,
    sid: 1,
    start: 0,
    end: 1,
    ...props.extraProps,
  })
}

describe('TagEntity.highlightOnWarnings — pc (compressible) tags', () => {
  test('flags a source pc tag present in missingTagsInTarget', () => {
    const instance = makeInstance({
      entityData: {name: 'ph', encodedText: dataRefOpenD1},
      isTarget: false,
      missingTagsInTarget: [{data: {encodedText: dataRefOpenD1}}],
    })
    expect(instance.highlightOnWarnings()).toBe('tag-mismatch-error')
  })

  test('does not flag a source pc tag absent from missingTagsInTarget', () => {
    const instance = makeInstance({
      entityData: {name: 'ph', encodedText: dataRefOpenD1},
      isTarget: false,
      missingTagsInTarget: [],
    })
    expect(instance.highlightOnWarnings()).toBe('')
  })

  test('does not use tagMismatch (QA endpoint) content matching for pc tags', () => {
    // tagMismatch reports something that would never equal this tag's own
    // encodedText (the QA endpoint reports each mismatch in isolation, in a
    // different, lossy format) -- pc tags must ignore it entirely.
    const instance = makeInstance({
      entityData: {name: 'ph', encodedText: dataRefOpenD1},
      isTarget: false,
      tagMismatch: {source: ['</pc>'], target: [], order: []},
      missingTagsInTarget: [],
    })
    expect(instance.highlightOnWarnings()).toBe('')
  })

  test('target-side pc tags are never flagged by this path', () => {
    const instance = makeInstance({
      entityData: {name: 'ph', encodedText: dataRefOpenD1},
      isTarget: true,
      missingTagsInTarget: [{data: {encodedText: dataRefOpenD1}}],
    })
    expect(instance.highlightOnWarnings()).toBe('')
  })

  test('non-pc tags keep using tagMismatch as before', () => {
    const instance = makeInstance({
      entityData: {name: 'ph', encodedText: nonPcTag},
      isTarget: false,
      tagMismatch: {source: [nonPcTag], target: [], order: []},
      missingTagsInTarget: [],
    })
    expect(instance.highlightOnWarnings()).toBe('tag-mismatch-error')
  })
})
