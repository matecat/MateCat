import resolveEditorCommand from './resolveEditorCommand'
import {
  checkCaretIsNearEntity,
  checkCaretIsNearZwsp,
} from './manageCaretPositionNearEntity'
import {isMacOS} from '../../../../utils/Utils'

jest.mock('./manageCaretPositionNearEntity', () => ({
  __esModule: true,
  checkCaretIsNearEntity: jest.fn(() => null),
  checkCaretIsNearZwsp: jest.fn(() => null),
  isSelectedEntity: jest.fn(() => false),
}))

jest.mock('../../../../utils/Utils', () => ({
  __esModule: true,
  isMacOS: jest.fn(() => false),
}))

// The caret arms are why this function exists as its own module. They used to
// live inside the component's keyBindingFn, where the only way to reach them
// was to place a caret jsdom cannot place, so they went uncovered. Here the
// caret helpers are injected and the routing is testable on its own.
const EDITOR_STATE = {marker: 'editorState'}
const ADJUSTED = {marker: 'adjusted'}

const ctx = (overrides = {}) => ({
  displayPopover: false,
  editorState: EDITOR_STATE,
  isRTL: false,
  isChromeBook: false,
  hasSpaceTag: true,
  selectionIsCaret: () => true,
  typingWordJoiner: {get: () => false, reset: () => {}},
  ...overrides,
})

const key = (overrides = {}) => ({
  key: '',
  keyCode: 0,
  altKey: false,
  ctrlKey: false,
  metaKey: false,
  shiftKey: false,
  code: '',
  ...overrides,
})

beforeEach(() => {
  jest.clearAllMocks()
  checkCaretIsNearEntity.mockReturnValue(null)
  checkCaretIsNearZwsp.mockReturnValue(null)
  isMacOS.mockReturnValue(false)
})

describe('tag menu', () => {
  test('alt + t opens it and clears the trigger text', () => {
    const r = resolveEditorCommand(
      key({key: 't', keyCode: 84, altKey: true}),
      ctx(),
    )

    expect(r.command).toBe('toggle-tag-menu')
    expect(r.clearTriggerText).toBe(true)
    expect(r.typeText).toBeUndefined()
  })

  test('< opens it and types the character', () => {
    const r = resolveEditorCommand(key({key: '<'}), ctx())

    expect(r.command).toBe('toggle-tag-menu')
    expect(r.typeText).toBe('<')
    expect(r.clearTriggerText).toBeUndefined()
  })

  test('escape closes it', () => {
    expect(resolveEditorCommand(key({key: 'Escape'}), ctx()).command).toBe(
      'close-tag-menu',
    )
  })

  test('the arrows only move the selection while it is open', () => {
    const closed = ctx({displayPopover: false})
    const open = ctx({displayPopover: true})

    expect(
      resolveEditorCommand(key({key: 'ArrowUp'}), closed).command,
    ).toBeNull()
    expect(resolveEditorCommand(key({key: 'ArrowUp'}), open).command).toBe(
      'up-arrow-press',
    )
    expect(resolveEditorCommand(key({key: 'ArrowDown'}), open).command).toBe(
      'down-arrow-press',
    )
  })

  test('enter accepts a suggestion only while it is open', () => {
    expect(
      resolveEditorCommand(key({key: 'Enter'}), ctx({displayPopover: true}))
        .command,
    ).toBe('enter-press')
    expect(
      resolveEditorCommand(key({key: 'Enter'}), ctx({displayPopover: false}))
        .command,
    ).toBeNull()
  })
})

describe('tag insertion', () => {
  test('tab inserts a tab tag, shift + tab does not', () => {
    expect(resolveEditorCommand(key({key: 'Tab'}), ctx()).command).toBe(
      'insert-tab-tag',
    )
    expect(
      resolveEditorCommand(key({key: 'Tab', shiftKey: true}), ctx()).command,
    ).toBeNull()
  })

  test('space inserts a space tag only when the segment has one', () => {
    const ev = key({key: ' ', code: 'Space'})

    expect(resolveEditorCommand(ev, ctx({hasSpaceTag: true})).command).toBe(
      'insert-space-tag',
    )
    expect(
      resolveEditorCommand(ev, ctx({hasSpaceTag: false})).command,
    ).not.toBe('insert-space-tag')
  })
})

describe('caret navigation', () => {
  test('an arrow next to an entity returns the adjusted state', () => {
    checkCaretIsNearEntity.mockReturnValue(ADJUSTED)

    const r = resolveEditorCommand(key({key: 'ArrowLeft'}), ctx())

    expect(r.command).toBe('left-nav')
    expect(r.editorState).toBe(ADJUSTED)
    expect(r.applyVia).toBeUndefined()
  })

  test('a zero-width space adjustment is used when there is no entity', () => {
    checkCaretIsNearZwsp.mockReturnValue(ADJUSTED)

    const r = resolveEditorCommand(key({key: 'ArrowRight'}), ctx())

    expect(r.command).toBe('right-nav')
    expect(r.editorState).toBe(ADJUSTED)
  })

  test('an arrow in open text falls through to the default binding', () => {
    const r = resolveEditorCommand(key({key: 'ArrowLeft'}), ctx())

    expect(r.command).toBeNull()
    expect(r.editorState).toBeUndefined()
  })

  test('the shift state is recorded even when the caret does not move', () => {
    // adjustCaretPosition reads this after the fact, so it has to be reported
    // on the pass that decided nothing needed adjusting.
    const r = resolveEditorCommand(
      key({key: 'ArrowLeft', shiftKey: true}),
      ctx(),
    )

    expect(r.command).toBeNull()
    expect(r.shiftOnNavigation).toBe(true)
  })

  test('direction follows the key, not the text direction', () => {
    checkCaretIsNearEntity.mockReturnValue(ADJUSTED)

    expect(
      resolveEditorCommand(key({key: 'ArrowLeft'}), ctx({isRTL: true})).command,
    ).toBe('left-nav')
    expect(
      resolveEditorCommand(key({key: 'ArrowRight'}), ctx({isRTL: true}))
        .command,
    ).toBe('right-nav')
  })
})

describe('shortcuts that do not touch the editor', () => {
  test('ctrl + k opens the TM search', () => {
    expect(
      resolveEditorCommand(key({key: 'k', ctrlKey: true}), ctx()).command,
    ).toBe('tm-search')
  })

  test('ctrl + enter translates, adding shift moves to the next segment', () => {
    expect(
      resolveEditorCommand(key({key: 'Enter', ctrlKey: true}), ctx()).command,
    ).toBe('translate')
    expect(
      resolveEditorCommand(
        key({key: 'Enter', ctrlKey: true, shiftKey: true}),
        ctx(),
      ).command,
    ).toBe('next-translate')
  })

  test('an unhandled key falls through to the default binding', () => {
    const r = resolveEditorCommand(key({key: 'a'}), ctx())

    expect(r.command).toBeNull()
    expect(r.editorState).toBeUndefined()
    expect(r.typeText).toBeUndefined()
  })
})
