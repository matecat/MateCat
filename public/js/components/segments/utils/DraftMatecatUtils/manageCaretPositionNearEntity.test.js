import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'
import {
  checkCaretIsNearZwsp,
  checkCaretIsNearEntity,
  moveCaretOutsideEntity,
  isSelectedEntity,
  isCaretInsideEntity,
  getEntitiesSelected,
  adjustCaretPosition,
} from './manageCaretPositionNearEntity'

const ZWSP = String.fromCharCode(parseInt('200B', 16))

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

const forceSelectionOffsets = (editorState, {anchorOffset, focusOffset}) => {
  const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset,
    focusOffset,
    isBackward: focusOffset < anchorOffset,
  })
  return EditorState.forceSelection(editorState, selection)
}

const editorStateWithEntity = (text, start, end, name = 'g') => {
  let contentState = ContentState.createFromText(text)
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  const selection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: start,
    focusOffset: end,
  })
  contentState = Modifier.applyEntity(contentState, selection, entityKey)
  return EditorState.createWithContent(contentState)
}

afterEach(() => {
  global.config.isTargetRTL = undefined
  jest.restoreAllMocks()
  window.getSelection().removeAllRanges()
  document.body.innerHTML = ''
})

describe('checkCaretIsNearZwsp', () => {
  test('moves caret forward past a zero-width space to the right', () => {
    let editorState = editorStateFromText(`a${ZWSP}b`)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 1,
      focusOffset: 1,
    })

    const result = checkCaretIsNearZwsp({editorState, direction: 'right'})

    expect(result.getSelection().getFocusOffset()).toBe(2)
    expect(result.getSelection().getAnchorOffset()).toBe(2)
  })

  test('moves caret backward past a zero-width space to the left', () => {
    let editorState = editorStateFromText(`a${ZWSP}b`)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 2,
      focusOffset: 2,
    })

    const result = checkCaretIsNearZwsp({editorState, direction: 'left'})

    expect(result.getSelection().getFocusOffset()).toBe(1)
    expect(result.getSelection().isBackward).toBe(true)
  })

  test('keeps the anchor fixed when shift is pressed', () => {
    let editorState = editorStateFromText(`a${ZWSP}b`)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 0,
      focusOffset: 1,
    })

    const result = checkCaretIsNearZwsp({
      editorState,
      direction: 'right',
      isShiftPressed: true,
    })

    expect(result.getSelection().getAnchorOffset()).toBe(0)
    expect(result.getSelection().getFocusOffset()).toBe(2)
  })

  test('returns undefined when caret is not adjacent to a zero-width space', () => {
    let editorState = editorStateFromText('abc')
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 1,
      focusOffset: 1,
    })

    expect(checkCaretIsNearZwsp({editorState, direction: 'right'})).toBeUndefined()
  })

  test('applies RTL direction inversion', () => {
    global.config.isTargetRTL = true
    let editorState = editorStateFromText(`a${ZWSP}b`)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 2,
      focusOffset: 2,
    })

    // direction 'right' with RTL becomes a leftward step
    const result = checkCaretIsNearZwsp({editorState, direction: 'right'})

    expect(result.getSelection().getFocusOffset()).toBe(1)
  })
})

describe('checkCaretIsNearEntity / moveCaretOutsideEntity', () => {
  test('moves caret to the end of the entity when approaching from the right', () => {
    let editorState = editorStateWithEntity('hello world', 2, 7)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 5,
      focusOffset: 5,
    })

    const result = checkCaretIsNearEntity({editorState, direction: 'right'})

    expect(result).toBeDefined()
    expect(result.getSelection().getFocusOffset()).toBe(7)
  })

  test('moves caret to the start of the entity when approaching from the left', () => {
    let editorState = editorStateWithEntity('hello world', 2, 7)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 5,
      focusOffset: 5,
    })

    const result = checkCaretIsNearEntity({editorState, direction: 'left'})

    expect(result).toBeDefined()
    expect(result.getSelection().getFocusOffset()).toBe(2)
  })

  test('returns undefined when the caret does not touch any entity', () => {
    let editorState = editorStateWithEntity('hello world', 2, 7)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 9,
      focusOffset: 9,
    })

    expect(checkCaretIsNearEntity({editorState, direction: 'right'})).toBeUndefined()
  })

  test('detects an adjacent zwsp-separated entity on backspace and still lands on the boundary', () => {
    // "AB" entity [0,2) + ZWSP at 2 + "X" filler at 3 + "CDE" entity [4,7)
    let contentState = ContentState.createFromText(`AB${ZWSP}XCDE`)
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const firstKey = contentState.getLastCreatedEntityKey()
    const blockKey = contentState.getFirstBlock().getKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 0,
        focusOffset: 2,
      }),
      firstKey,
    )
    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const secondKey = contentState.getLastCreatedEntityKey()
    contentState = Modifier.applyEntity(
      contentState,
      SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 4,
        focusOffset: 7,
      }),
      secondKey,
    )

    let editorState = EditorState.createWithContent(contentState)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 5,
      focusOffset: 5,
    })

    const result = checkCaretIsNearEntity({
      editorState,
      direction: 'left',
      isBackspacePressed: true,
    })

    expect(result.getSelection().getFocusOffset()).toBe(4)
  })

  test('preserves the anchor offset when shift is pressed', () => {
    let editorState = editorStateWithEntity('hello world', 2, 7)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 0,
      focusOffset: 5,
    })

    const result = moveCaretOutsideEntity({
      editorState,
      entity: {start: 2, end: 7},
      direction: 'right',
      isShiftPressed: true,
    })

    expect(result.getSelection().getAnchorOffset()).toBe(0)
    expect(result.getSelection().getFocusOffset()).toBe(7)
  })
})

describe('isSelectedEntity', () => {
  test('returns true when the selection wraps exactly around an entity', () => {
    let editorState = editorStateWithEntity('hello world', 3, 8)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 2,
      focusOffset: 9,
    })

    expect(isSelectedEntity(editorState)).toBe(true)
  })

  test('returns false when the selection does not match entity bounds', () => {
    let editorState = editorStateWithEntity('hello world', 3, 8)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 0,
      focusOffset: 4,
    })

    expect(isSelectedEntity(editorState)).toBe(false)
  })
})

describe('getEntitiesSelected', () => {
  test('returns an empty array for a collapsed selection', () => {
    let editorState = editorStateWithEntity('hello world', 3, 8)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 4,
      focusOffset: 4,
    })

    expect(getEntitiesSelected(editorState)).toEqual([])
  })

  test('returns the entities fully contained within the selection', () => {
    let editorState = editorStateWithEntity('hello world', 3, 8)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 0,
      focusOffset: 11,
    })

    const result = getEntitiesSelected(editorState)

    expect(result).toHaveLength(1)
    expect(result[0].start).toBe(3)
    expect(result[0].end).toBe(8)
  })

  test('excludes entities that are only partially selected', () => {
    let editorState = editorStateWithEntity('hello world', 3, 8)
    editorState = forceSelectionOffsets(editorState, {
      anchorOffset: 0,
      focusOffset: 5,
    })

    expect(getEntitiesSelected(editorState)).toEqual([])
  })

  test('resolves offsets across several blocks (leading, anchor, middle and focus)', () => {
    // 4 blocks: b0 excluded (before anchor), b1 is the anchor block,
    // b2 is a fully-included middle block, b3 is the focus block.
    let contentState = ContentState.createFromText('aaa\nbbb\nccc\nddd')
    const [b0, b1, b2, b3] = contentState.getBlocksAsArray()

    const applyEntity = (cs, blockKey, start, end) => {
      cs = cs.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
      const key = cs.getLastCreatedEntityKey()
      const selection = SelectionState.createEmpty(blockKey).merge({
        anchorOffset: start,
        focusOffset: end,
      })
      return Modifier.applyEntity(cs, selection, key)
    }

    contentState = applyEntity(contentState, b0.getKey(), 0, 3) // excluded: before anchor block
    contentState = applyEntity(contentState, b1.getKey(), 1, 3) // included: within anchor's remaining range
    contentState = applyEntity(contentState, b2.getKey(), 0, 3) // included: whole middle block
    contentState = applyEntity(contentState, b3.getKey(), 2, 3) // excluded: past the focus offset

    const forwardSelection = new SelectionState({
      anchorKey: b1.getKey(),
      anchorOffset: 1,
      focusKey: b3.getKey(),
      focusOffset: 2,
      isBackward: false,
    })

    const editorState = EditorState.forceSelection(
      EditorState.createWithContent(contentState),
      forwardSelection,
    )

    const result = getEntitiesSelected(editorState)

    expect(result).toHaveLength(2)
    expect(result.map((entity) => entity.blockKey).sort()).toEqual(
      [b1.getKey(), b2.getKey()].sort(),
    )
  })

  test('resolves offsets for a backward selection', () => {
    let contentState = ContentState.createFromText('foo\nbar')
    const [firstBlock, secondBlock] = contentState.getBlocksAsArray()

    contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
    const entityKey = contentState.getLastCreatedEntityKey()
    const entitySelection = SelectionState.createEmpty(
      secondBlock.getKey(),
    ).merge({anchorOffset: 0, focusOffset: 2})
    contentState = Modifier.applyEntity(contentState, entitySelection, entityKey)

    const backwardSelection = new SelectionState({
      anchorKey: secondBlock.getKey(),
      anchorOffset: 2,
      focusKey: firstBlock.getKey(),
      focusOffset: 1,
      isBackward: true,
    })

    const editorState = EditorState.forceSelection(
      EditorState.createWithContent(contentState),
      backwardSelection,
    )

    const result = getEntitiesSelected(editorState)

    expect(result).toHaveLength(1)
    expect(result[0].blockKey).toBe(secondBlock.getKey())
  })
})

describe('isCaretInsideEntity', () => {
  const setCaret = (node, offset) => {
    const range = document.createRange()
    range.setStart(node, offset)
    range.collapse(true)
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
  }

  test('returns true when the caret sits inside a tag-container element', () => {
    document.body.innerHTML =
      '<div contenteditable="true"><span class="tag-container">AB</span></div>'
    const textNode = document.querySelector('.tag-container').firstChild
    setCaret(textNode, 1)

    expect(isCaretInsideEntity()).toBe(true)
  })

  test('returns false when the caret is outside any tag-container element', () => {
    document.body.innerHTML = '<div contenteditable="true">plain text</div>'
    const textNode = document.querySelector('div').firstChild
    setCaret(textNode, 2)

    expect(isCaretInsideEntity()).toBe(false)
  })

  test('returns false when there is no active selection', () => {
    jest.spyOn(window, 'getSelection').mockReturnValue(null)

    expect(isCaretInsideEntity()).toBe(false)
  })
})

describe('adjustCaretPosition', () => {
  const setCaret = (node, offset) => {
    const range = document.createRange()
    range.setStart(node, offset)
    range.collapse(true)
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
  }

  test('does nothing when there is no active selection', () => {
    jest.spyOn(window, 'getSelection').mockReturnValue(null)

    expect(() => adjustCaretPosition({direction: 'right'})).not.toThrow()
  })

  test('does nothing when the caret is not inside an entity container', () => {
    document.body.innerHTML = '<div contenteditable="true">plain text</div>'
    const textNode = document.querySelector('div').firstChild
    setCaret(textNode, 2)

    expect(() => adjustCaretPosition({direction: 'right'})).not.toThrow()
  })

  test('skips adjustment moving right into an adjacent entity container', () => {
    document.body.innerHTML =
      '<div contenteditable="true">' +
      '<span class="tag-container">AB</span>' +
      '<span class="tag-container">CD</span>' +
      '</div>'
    const secondTag = document.querySelectorAll('.tag-container')[1]
    setCaret(secondTag.firstChild, 1)

    expect(() => adjustCaretPosition({direction: 'right'})).not.toThrow()
  })

  test('moves the caret onto the next sibling element when moving right', () => {
    document.body.innerHTML =
      '<div contenteditable="true">' +
      '<span class="tag-container">AB</span>' +
      '<span>after</span>' +
      '</div>'
    const tag = document.querySelector('.tag-container')
    setCaret(tag.firstChild, 1)

    adjustCaretPosition({direction: 'right'})

    const range = window.getSelection().getRangeAt(0)
    expect(range.startContainer.textContent).toBe('after')
    expect(range.startOffset).toBe(0)
  })

  test('moves the caret onto the previous sibling element when moving left', () => {
    document.body.innerHTML =
      '<div contenteditable="true">' +
      '<span>before</span>' +
      '<span class="tag-container">AB</span>' +
      '</div>'
    const tag = document.querySelector('.tag-container')
    setCaret(tag.firstChild, 1)

    adjustCaretPosition({direction: 'left'})

    const range = window.getSelection().getRangeAt(0)
    expect(range.startContainer.textContent).toBe('before')
    expect(range.startOffset).toBe('before'.length)
  })

  test('falls back to the entity container itself when there is no sibling', () => {
    document.body.innerHTML =
      '<div contenteditable="true"><span class="tag-container">AB</span></div>'
    const tag = document.querySelector('.tag-container')
    setCaret(tag.firstChild, 1)

    adjustCaretPosition({direction: 'right'})

    const range = window.getSelection().getRangeAt(0)
    expect(range.startContainer.textContent).toBe('AB')
    expect(range.startOffset).toBe(2)
  })

  test('extends the selection back to the previous element tag when requested', () => {
    document.body.innerHTML =
      '<div contenteditable="true">' +
      '<span>before</span>' +
      '<span class="tag-container">AB</span>' +
      '</div>'
    const tag = document.querySelector('.tag-container')
    const beforeText = document.querySelector('span').firstChild
    const range = document.createRange()
    range.setStart(tag.firstChild, 0)
    range.setEnd(tag.firstChild, 1)
    const selection = window.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)

    expect(() =>
      adjustCaretPosition({
        direction: 'left',
        shouldMoveCursorPreviousElementTag: true,
      }),
    ).not.toThrow()

    const finalRange = window.getSelection().getRangeAt(0)
    expect(finalRange.startContainer).toBe(beforeText)
    expect(finalRange.startOffset).toBe(beforeText.length - 1)
  })

  test('extends the selection when shift is pressed', () => {
    document.body.innerHTML =
      '<div contenteditable="true">' +
      '<span class="tag-container">AB</span>' +
      '<span>after</span>' +
      '</div>'
    const tag = document.querySelector('.tag-container')
    setCaret(tag.firstChild, 1)

    expect(() =>
      adjustCaretPosition({direction: 'right', isShiftPressed: true}),
    ).not.toThrow()
  })
})
