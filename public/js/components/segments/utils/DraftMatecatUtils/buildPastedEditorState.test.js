import buildPastedEditorState from './buildPastedEditorState'
import DraftMatecatUtils from './index'

jest.mock('./index', () => ({
  __esModule: true,
  default: {
    buildFragmentFromJson: jest.fn(() => 'fragment'),
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

const stored = (plainText, orderedMap = {}, entitiesMap = {}) => ({
  clipboardFragment: JSON.stringify({orderedMap, entitiesMap}),
  clipboardPlainText: plainText,
})

beforeEach(() => jest.clearAllMocks())

describe('a copy made inside the editor', () => {
  test('is rebuilt from the stored fragment, entities and all', () => {
    const result = buildPastedEditorState({
      text: 'ciao mondo',
      ...stored('ciao mondo', {blocks: 1}, {0: 'tag'}),
      editorState: EDITOR_STATE,
    })

    expect(result).toBe('pasted')
    expect(DraftMatecatUtils.buildFragmentFromJson).toHaveBeenCalledWith({
      blocks: 1,
    })
    expect(DraftMatecatUtils.duplicateFragment).toHaveBeenCalledWith(
      'fragment',
      EDITOR_STATE,
      {0: 'tag'},
    )
  })

  test('is recognised even when newlines differ from the system clipboard', () => {
    const result = buildPastedEditorState({
      text: 'ciao\nmondo',
      ...stored('ciaomondo'),
      editorState: EDITOR_STATE,
    })

    expect(result).toBe('pasted')
  })

  test('falls back to a plain paste when the stored fragment is unparseable', () => {
    const result = buildPastedEditorState({
      text: 'ciao',
      clipboardFragment: '{not json',
      clipboardPlainText: 'ciao',
      editorState: EDITOR_STATE,
    })

    expect(result).toBeNull()
    expect(DraftMatecatUtils.duplicateFragment).not.toHaveBeenCalled()
  })
})

describe('a copy from outside the editor', () => {
  test('is treated as external when the text does not match the stored copy', () => {
    buildPastedEditorState({
      text: 'something else',
      ...stored('ciao mondo'),
      editorState: EDITOR_STATE,
    })

    // the external path builds from text, not from the stored fragment
    expect(DraftMatecatUtils.buildFragmentFromText).toHaveBeenCalled()
    expect(DraftMatecatUtils.buildFragmentFromJson).not.toHaveBeenCalled()
  })

  test('has its tags stripped', () => {
    buildPastedEditorState({
      text: 'ciao <g id="1">mondo</g>',
      clipboardFragment: null,
      clipboardPlainText: '',
      editorState: EDITOR_STATE,
    })

    expect(DraftMatecatUtils.removeTagsFromText).toHaveBeenCalledWith(
      'ciao <g id="1">mondo</g>',
    )
  })

  test('turns non-breaking spaces and tabs back into tag placeholders', () => {
    buildPastedEditorState({
      text: 'a°b\tc',
      clipboardFragment: null,
      clipboardPlainText: '',
      editorState: EDITOR_STATE,
    })

    expect(DraftMatecatUtils.buildFragmentFromText).toHaveBeenCalledWith(
      'a<NBSP>b<TAB>c',
    )
  })

  test('is duplicated into the editor without an entities map', () => {
    const result = buildPastedEditorState({
      text: 'ciao',
      clipboardFragment: null,
      clipboardPlainText: '',
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
    clipboardFragment: null,
    clipboardPlainText: '',
    editorState: EDITOR_STATE,
  })

  expect(result).toBeNull()
  expect(DraftMatecatUtils.duplicateFragment).not.toHaveBeenCalled()
})
