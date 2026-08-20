import React from 'react'
import {render} from '@testing-library/react'
import '@testing-library/jest-dom'
import {EditorState} from 'draft-js'

import SegmentPlaceholderLite from './SegmentPlaceholderLite'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import SegmentUtils from '../../utils/segmentUtils'

jest.mock('./utils/DraftMatecatUtils', () => {
  const {
    ContentState,
    Modifier,
    EditorState: RealEditorState,
    SelectionState,
  } = require('draft-js')
  return {
    getEntityStrategy: jest.fn(
      (mutability) =>
        (contentBlock, callback, contentState) => {
          contentBlock.findEntityRanges((character) => {
            const entityKey = character.getEntity()
            if (entityKey === null) return false
            return (
              contentState.getEntity(entityKey).getMutability() === mutability
            )
          }, callback)
        },
    ),
    removeTagsFromText: jest.fn((text) => `cleaned-${text}`),
    // Builds a real entity-decorated EditorState so the `TagEntity`
    // draft-js decorator component actually renders for non-empty text.
    encodeContent: jest.fn((editorState, plainText = '') => {
      if (!plainText) {
        return {editorState}
      }
      let contentState = ContentState.createFromText(plainText)
      contentState = contentState.createEntity('TAG', 'IMMUTABLE', {})
      const entityKey = contentState.getLastCreatedEntityKey()
      const blockKey = contentState.getFirstBlock().getKey()
      const selection = new SelectionState({
        anchorKey: blockKey,
        anchorOffset: 0,
        focusKey: blockKey,
        focusOffset: plainText.length,
        hasFocus: false,
        isBackward: false,
      })
      contentState = Modifier.applyEntity(contentState, selection, entityKey)
      const newEditorState = RealEditorState.createWithContent(
        contentState,
        editorState.getDecorator(),
      )
      return {editorState: newEditorState}
    }),
  }
})

jest.mock('../../utils/segmentUtils', () => ({
  checkCurrentSegmentTPEnabled: jest.fn(() => false),
}))

const renderPlaceholder = (props = {}) => {
  const defaultProps = {
    segment: {segment: 'source text', translation: 'target text'},
    sideOpen: false,
    computeHeight: jest.fn(),
  }
  return render(<SegmentPlaceholderLite {...defaultProps} {...props} />)
}

describe('SegmentPlaceholderLite', () => {
  test('renders the placeholder skeleton structure', () => {
    const {container} = renderPlaceholder()
    expect(
      container.querySelector('.segment-container.segment-placeholder'),
    ).toBeInTheDocument()
    expect(container.querySelector('.source.item')).toBeInTheDocument()
    expect(container.querySelector('.target.item')).toBeInTheDocument()
    expect(container.querySelectorAll('.DraftEditor-root')).toHaveLength(2)
  })

  test('applies slide-right class when sideOpen is true', () => {
    const {container} = renderPlaceholder({sideOpen: true})
    expect(container.querySelector('.status-draft')).toHaveClass(
      'slide-right',
    )
  })

  test('does not apply slide-right class when sideOpen is false', () => {
    const {container} = renderPlaceholder({sideOpen: false})
    expect(container.querySelector('.status-draft')).not.toHaveClass(
      'slide-right',
    )
  })

  test('calls computeHeight with a computed minimum height on mount', () => {
    const computeHeight = jest.fn()
    renderPlaceholder({computeHeight})
    expect(computeHeight).toHaveBeenCalledTimes(1)
    expect(computeHeight).toHaveBeenCalledWith(expect.any(Number))
    expect(computeHeight.mock.calls[0][0]).toBeGreaterThanOrEqual(90)
  })

  test('removes tags from source/translation when TP is enabled for the segment', () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
    renderPlaceholder({
      segment: {segment: 'src-with-tags', translation: 'trg-with-tags'},
    })
    expect(DraftMatecatUtils.removeTagsFromText).toHaveBeenCalledWith(
      'src-with-tags',
    )
    expect(DraftMatecatUtils.removeTagsFromText).toHaveBeenCalledWith(
      'trg-with-tags',
    )
  })

  test('does not remove tags when TP is disabled for the segment', () => {
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
    renderPlaceholder({
      segment: {segment: 'plain-source', translation: 'plain-target'},
    })
    expect(DraftMatecatUtils.removeTagsFromText).not.toHaveBeenCalled()
    expect(DraftMatecatUtils.encodeContent).toHaveBeenCalled()
  })

  test('builds editor states using EditorState.createEmpty', () => {
    renderPlaceholder()
    const calledWithEditorState = DraftMatecatUtils.encodeContent.mock.calls.every(
      ([editorState]) => EditorState.createEmpty().getCurrentContent
        ? typeof editorState.getCurrentContent === 'function'
        : false,
    )
    expect(calledWithEditorState).toBe(true)
  })

  test('renders the split action button', () => {
    const {container} = renderPlaceholder()
    expect(
      container.querySelector('button.split[title="Click to split segment"]'),
    ).toBeInTheDocument()
  })
})
