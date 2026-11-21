import buildPastedEditorState from './buildPastedEditorState'
import DraftMatecatUtils from './index'

jest.mock('./index', () => ({
  __esModule: true,
  default: {
    buildFragmentFromText: jest.fn(() => 'text-fragment'),
    duplicateFragment: jest.fn(() => 'pasted'),
    removeTagsFromText: jest.fn((t) => t),
  },
}))

jest.mock('./tagModel', () => ({
  __esModule: true,
  tagSignatures: {
    nbsp: {encodedPlaceholder: '<NBSP>'},
    tab: {encodedPlaceholder: '<TAB>'},
  },
}))

const EDITOR_STATE = {marker: 'editorState'}

beforeEach(() => jest.clearAllMocks())

describe('a copy from outside the editor', () => {
  test('has its tags stripped', () => {
    buildPastedEditorState({
      text: 'ciao <g id="1">mondo</g>',
      editorState: EDITOR_STATE,
    })

    expect(DraftMatecatUtils.removeTagsFromText).toHaveBeenCalledWith(
      'ciao <g id="1">mondo</g>',
    )
  })

  test('turns non-breaking spaces and tabs back into tag placeholders', () => {
    buildPastedEditorState({
      text: 'a°b\tc',
      editorState: EDITOR_STATE,
    })

    expect(DraftMatecatUtils.buildFragmentFromText).toHaveBeenCalledWith(
      'a<NBSP>b<TAB>c',
    )
  })

  test('is duplicated into the editor without an entities map', () => {
    const result = buildPastedEditorState({
      text: 'ciao',
      editorState: EDITOR_STATE,
    })

    expect(result).toBe('pasted')
    expect(DraftMatecatUtils.duplicateFragment).toHaveBeenCalledWith(
      'text-fragment',
      EDITOR_STATE,
    )
  })
})

test('an empty paste is left to Draft', () => {
  const result = buildPastedEditorState({
    text: '',
    editorState: EDITOR_STATE,
  })

  expect(result).toBeNull()
  expect(DraftMatecatUtils.duplicateFragment).not.toHaveBeenCalled()
})

test('an external paste made only of tags inserts nothing', () => {
  DraftMatecatUtils.buildFragmentFromText.mockReturnValueOnce(null)

  const result = buildPastedEditorState({
    text: '<g id="1"></g>',
    editorState: EDITOR_STATE,
  })

  expect(result).toBe(EDITOR_STATE)
  expect(DraftMatecatUtils.duplicateFragment).not.toHaveBeenCalled()
})
