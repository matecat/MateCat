import {EditorState, Modifier} from 'draft-js'
import resolveDrop from './resolveDrop'
import DraftMatecatUtils from './index'

jest.mock('draft-js', () => ({
  __esModule: true,
  EditorState: {
    forceSelection: jest.fn((state) => state),
    push: jest.fn((state) => state),
  },
  Modifier: {
    removeRange: jest.fn(() => 'content-without-drag'),
    replaceWithFragment: jest.fn(() => 'content-with-fragment'),
  },
}))

jest.mock('./index', () => ({
  __esModule: true,
  default: {
    buildFragmentFromJson: jest.fn(() => 'fragment'),
    duplicateFragment: jest.fn(() => 'duplicated'),
    selectionIsEntity: jest.fn(() => ({entityKey: null})),
  },
}))

jest.mock(
  './DraftSource/src/component/handlers/edit/getFragmentFromSelection',
  () => ({__esModule: true, default: jest.fn(() => 'raw-fragment')}),
)

/** A selection that records what it was merged into. */
const sel = (anchor, focus, blockKey = 'a') => ({
  anchorOffset: anchor,
  focusOffset: focus,
  isBackward: false,
  getAnchorKey: () => blockKey,
  merge: jest.fn((patch) => ({...sel(patch.anchorOffset, patch.focusOffset, blockKey), merged: patch})),
})

const editorWith = (dragSelection) => ({
  getSelection: () => dragSelection,
  getCurrentContent: () => 'content',
})

const run = (over = {}) =>
  resolveDrop({
    editorState: editorWith(over.drag || sel(0, 5)),
    selection: over.drop || sel(20, 20),
    text: 'text' in over ? over.text : null,
    draggingFromEditArea: over.draggingFromEditArea !== false,
  })

beforeEach(() => {
  jest.clearAllMocks()
  DraftMatecatUtils.selectionIsEntity.mockReturnValue({entityKey: null})
})

test('nothing may be dropped on an entity', () => {
  DraftMatecatUtils.selectionIsEntity.mockReturnValue({entityKey: 'tag-1'})

  const result = run()

  expect(result).toEqual({outcome: 'handled'})
  expect(Modifier.replaceWithFragment).not.toHaveBeenCalled()
})

describe('a drag from outside the editor', () => {
  test('is duplicated in with its entities', () => {
    const payload = JSON.stringify({orderedMap: {b: 1}, entitiesMap: {0: 'e'}})

    const result = run({text: payload, draggingFromEditArea: false})

    expect(result.outcome).toBe('handled')
    expect(result.editorState).toBe('duplicated')
    expect(DraftMatecatUtils.duplicateFragment).toHaveBeenCalledWith(
      'fragment',
      expect.anything(),
      {0: 'e'},
    )
  })

  test('is declined when the payload is not JSON', () => {
    const result = run({text: '{not json', draggingFromEditArea: false})

    expect(result).toEqual({outcome: 'not-handled'})
  })
})

describe('a drag within the editor', () => {
  test('cuts the dragged range out and puts the fragment at the drop point', () => {
    const result = run({drag: sel(0, 5), drop: sel(20, 20)})

    expect(Modifier.removeRange).toHaveBeenCalledWith(
      'content',
      expect.anything(),
      'forward',
    )
    expect(Modifier.replaceWithFragment).toHaveBeenCalledWith(
      'content-without-drag',
      expect.anything(),
      'fragment',
    )
    expect(result.outcome).toBe('handled')
    expect(result.highlightTags).toBe(true)
  })

  test('removes backwards when the drag selection is backwards', () => {
    const backwards = {...sel(5, 0), isBackward: true}

    run({drag: backwards, drop: sel(20, 20)})

    expect(Modifier.removeRange).toHaveBeenCalledWith(
      'content',
      expect.anything(),
      'backward',
    )
  })

  // The interesting case: cutting the range out shifts later text left, so a
  // drop further along the same block must come back by the length removed.
  test('pulls the drop offsets back when moving forward in the same block', () => {
    const drop = sel(20, 20, 'same-block')

    run({drag: sel(0, 5, 'same-block'), drop})

    expect(drop.merge).toHaveBeenCalledWith({
      anchorOffset: 15,
      focusOffset: 15,
    })
  })

  test('leaves the offsets alone when moving backward', () => {
    const drop = sel(2, 2, 'same-block')

    run({drag: sel(10, 15, 'same-block'), drop})

    expect(drop.merge).not.toHaveBeenCalled()
  })

  test('leaves the offsets alone when the drop is in another block', () => {
    const drop = sel(20, 20, 'other-block')

    run({drag: sel(0, 5, 'same-block'), drop})

    expect(drop.merge).not.toHaveBeenCalled()
  })

  test('is declined when the content operations throw', () => {
    Modifier.removeRange.mockImplementation(() => {
      throw new Error('bad range')
    })
    jest.spyOn(console, 'log').mockImplementation(() => {})

    expect(run()).toEqual({outcome: 'not-handled'})

    console.log.mockRestore()
    Modifier.removeRange.mockImplementation(() => 'content-without-drag')
  })

  test('selects the fragment it just dropped', () => {
    run({drag: sel(0, 5), drop: sel(20, 20)})

    // last forceSelection call places the caret at the adjusted drop point
    expect(EditorState.forceSelection).toHaveBeenCalledTimes(2)
  })
})
