import React from 'react'
import {render, act, screen} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentTarget from './SegmentTarget'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import OfflineUtils from '../../utils/offlineUtils'
import SegmentUtils from '../../utils/segmentUtils'
import CursorUtils from '../../utils/cursorUtils'
import {Base64} from 'js-base64'
import {setTagSignatureMiddleware} from './utils/DraftMatecatUtils/tagModel'

jest.mock('./Editarea', () => (props) => (
  <div
    data-testid="edit-area"
    data-translation={props.translation}
    data-sid={props.segment?.sid}
  />
))

let mockNewEditor = false
jest.mock('../editor/matecat/editorEngine', () => ({
  isNewEditorActive: () => mockNewEditor,
}))
jest.mock('../editor/matecat/TargetEditor', () => {
  const React = require('react')
  return React.forwardRef((props, ref) => {
    React.useImperativeHandle(ref, () => mockTargetHandle)
    return (
      <div
        data-testid="target-editor"
        data-value={props.value}
        data-translation={props.translation}
        data-bridge={props.bridge?.name}
      />
    )
  })
})
const mockTargetHandle = {addMissingSourceTagsToTarget: jest.fn()}
jest.mock('./targetEditorBridge', () => ({
  targetEditorBridge: {name: 'target-bridge'},
}))

jest.mock('./SegmentButtons', () => (props) => (
  <div data-testid="segment-buttons" data-disabled={String(props.disabled)} />
))

jest.mock('./SegmentWarnings', () => (props) => (
  <div data-testid="segment-warnings" />
))

const mockSegmentTargetToolbar = jest.fn((props) => (
  <div data-testid="segment-target-toolbar" />
))
jest.mock('./SegmentTargetToolbar', () => ({
  SegmentTargetToolbar: (props) => mockSegmentTargetToolbar(props),
}))

jest.mock('../../stores/SegmentStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../stores/CatToolStore', () => ({
  getCurrentProjectTemplate: jest.fn(() => ({
    characterCounterCountTags: false,
  })),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  FILL_TAGS_IN_TARGET: 'FILL_TAGS_IN_TARGET',
}))

jest.mock('../../actions/SegmentActions', () => ({
  openIssuesPanel: jest.fn(),
  showIssuesMessage: jest.fn(),
  lockEditArea: jest.fn(),
  replaceEditAreaTextContent: jest.fn(),
  getSegmentsQa: jest.fn(),
  characterCounter: jest.fn(),
}))

jest.mock('./utils/DraftMatecatUtils/segmentStringUtils', () => ({
  autoFillTagsInTarget: jest.fn(() => 'auto-filled-translation'),
}))

jest.mock('./utils/DraftMatecatUtils/tagUtils', () => ({
  removeTagsFromText: jest.fn((text) => `clean-${text}`),
  textHasTags: jest.fn(() => false),
}))

jest.mock('../../utils/offlineUtils', () => ({
  offlineCacheRemaining: 20,
}))

jest.mock('../../utils/cursorUtils', () => ({
  getSelectionData: jest.fn(() => ({selectionData: true})),
}))

jest.mock('../../utils/segmentUtils', () => ({
  checkCurrentSegmentTPEnabled: jest.fn(() => false),
  getRelativeTransUnitCharactersCounter: jest.fn(({charactersCounter}) => ({
    segmentCharacters: charactersCounter?.segmentCharacters ?? 0,
    unitCharacters: charactersCounter?.counter ?? 0,
  })),
}))

const ph = (html) =>
  `<ph id="m1" ctype="x-html" equiv-text="base64:${Base64.encode(html)}"/>`

const buildSegment = (overrides = {}) => ({
  sid: '1-1',
  fid: 5,
  translation: 'some translation',
  edit_area_locked: false,
  warnings: null,
  metadata: [],
  ...overrides,
})

const renderTarget = (props = {}, contextValue = {}) => {
  const segment = props.segment || buildSegment()
  const defaultContext = {
    segment,
    removeSelection: jest.fn(),
    speech2textEnabledFn: jest.fn(() => false),
  }
  const utils = render(
    <SegmentContext.Provider value={{...defaultContext, ...contextValue}}>
      <SegmentTarget {...{segment}} {...props} />
    </SegmentContext.Provider>,
  )
  return {...utils, segment}
}

describe('SegmentTarget', () => {
  beforeEach(() => {
    window.config = {
      target_code: 'en',
      isTargetRTL: false,
      isReview: false,
      id_job: 10,
      password: 'pwd',
      revisionNumber: 1,
    }
    OfflineUtils.offlineCacheRemaining = 20
  })

  test('renders the target container with the correct id and class', () => {
    const {container, segment} = renderTarget()
    expect(
      container.querySelector(`#segment-${segment.sid}-target`),
    ).toBeInTheDocument()
    expect(container.querySelector('.target.item')).toHaveClass('target-en')
  })

  test('renders SegmentWarnings only when the segment has warnings', () => {
    const {container, rerender} = renderTarget({
      segment: buildSegment({warnings: null}),
    })
    expect(
      container.querySelector('[data-testid="segment-warnings"]'),
    ).not.toBeInTheDocument()
  })

  test('renders SegmentWarnings when the segment has warnings', () => {
    const {container} = renderTarget({
      segment: buildSegment({warnings: {ERROR: {}}}),
    })
    expect(
      container.querySelector('[data-testid="segment-warnings"]'),
    ).toBeInTheDocument()
  })

  describe('locked edit area', () => {
    test('renders the read-only highlighted view instead of EditArea', () => {
      const {container} = renderTarget({
        segment: buildSegment({edit_area_locked: true}),
      })
      expect(
        container.querySelector('.issuesHighlightArea'),
      ).toBeInTheDocument()
      expect(
        container.querySelector('[data-testid="edit-area"]'),
      ).not.toBeInTheDocument()
    })

    test('does not show the revise-lock button when not in review mode', () => {
      window.config.isReview = false
      const {container} = renderTarget({
        segment: buildSegment({edit_area_locked: true}),
      })
      expect(
        container.querySelector('.revise-lock-editArea-active'),
      ).not.toBeInTheDocument()
    })

    test('shows the revise-lock button in review mode and locks on click', () => {
      window.config.isReview = true
      const {container, segment} = renderTarget({
        segment: buildSegment({edit_area_locked: true}),
      })
      const lockButton = container.querySelector('.revise-lock-editArea-active')
      expect(lockButton).toBeInTheDocument()

      act(() => {
        lockButton.click()
      })

      expect(SegmentActions.lockEditArea).toHaveBeenCalledWith(
        segment.sid,
        segment.fid,
      )
      expect(SegmentActions.showIssuesMessage).not.toHaveBeenCalled()
    })

    test('uses the latest version translation when available', () => {
      renderTarget({
        segment: buildSegment({
          edit_area_locked: true,
          translation: 'current',
          versions: [{translation: 'versioned&amp;text'}],
        }),
      })
      // The issue selection offsets count the area's direct children, so the
      // text must sit straight in the area, not in a wrapper.
      expect(screen.getByText('versioned&text')).toHaveClass(
        'targetarea',
        'issuesHighlightArea',
        'errorTaggingArea',
      )
      expect(screen.queryByText('current')).not.toBeInTheDocument()
    })

    test('renders SegmentButtons with disabled computed from translation/offline state', () => {
      const {container} = renderTarget({
        segment: buildSegment({edit_area_locked: true, translation: ''}),
      })
      expect(
        container.querySelector('[data-testid="segment-buttons"]'),
      ).toHaveAttribute('data-disabled', 'true')
    })
  })

  describe('editable area', () => {
    test('renders EditArea and the target toolbar', () => {
      const {container} = renderTarget({
        segment: buildSegment({edit_area_locked: false}),
      })
      expect(
        container.querySelector('[data-testid="edit-area"]'),
      ).toBeInTheDocument()
      expect(
        container.querySelector('[data-testid="segment-target-toolbar"]'),
      ).toBeInTheDocument()
    })

    test('passes a well-formed qrLink and issue count to the toolbar', () => {
      renderTarget({
        segment: buildSegment({
          sid: '2-1',
          versions: [{issues: [{id: 1}, {id: 2}]}],
        }),
      })
      const lastCallProps =
        mockSegmentTargetToolbar.mock.calls[
          mockSegmentTargetToolbar.mock.calls.length - 1
        ][0]
      expect(lastCallProps.qrLink).toBe(
        '/revise-summary/10-pwd?revision_type=1&id_segment=2-1',
      )
      expect(lastCallProps.issuesLength).toBe(2)
    })

    test('shows the speech-to-text mic when enabled via context', () => {
      const {container} = renderTarget(
        {segment: buildSegment()},
        {speech2textEnabledFn: jest.fn(() => true)},
      )
      expect(container.querySelector('.micSpeech')).toBeInTheDocument()
    })

    test('does not show the speech-to-text mic when disabled via context', () => {
      const {container} = renderTarget(
        {segment: buildSegment()},
        {speech2textEnabledFn: jest.fn(() => false)},
      )
      expect(container.querySelector('.micSpeech')).not.toBeInTheDocument()
    })

    test('marks buttons disabled when offline cache is depleted', () => {
      OfflineUtils.offlineCacheRemaining = 0
      const {container} = renderTarget({segment: buildSegment()})
      expect(
        container.querySelector('[data-testid="segment-buttons"]'),
      ).toHaveAttribute('data-disabled', 'true')
    })
  })

  describe('lifecycle listeners', () => {
    test('registers and unregisters the FILL_TAGS_IN_TARGET listener', () => {
      const {unmount} = renderTarget()
      expect(SegmentStore.addListener).toHaveBeenCalledWith(
        'FILL_TAGS_IN_TARGET',
        expect.any(Function),
      )
      unmount()
      expect(SegmentStore.removeListener).toHaveBeenCalledWith(
        'FILL_TAGS_IN_TARGET',
        expect.any(Function),
      )
    })
  })
  describe('locked translation area', () => {
    beforeAll(() => setTagSignatureMiddleware('space', () => false))
    afterAll(() => setTagSignatureMiddleware('space', () => undefined))

    const lockedWith = (translation) =>
      renderTarget({
        segment: buildSegment({edit_area_locked: true, translation}),
      })

    test('shows the translation text and its tags as chips, with entities decoded', () => {
      lockedWith(`Tom &amp; ${ph('&lt;br/&gt;')}Jerry`)
      expect(
        screen.getByText('<br/>', {selector: '.tag.tag-ph > .tag-text'}),
      ).toBeInTheDocument()
      expect(screen.getByText(/Tom &/)).toHaveClass('errorTaggingArea')
      expect(screen.queryByText(/equiv-text/)).not.toBeInTheDocument()
    })

    // CursorUtils.getSelectionData counts the area's direct children to turn a
    // selection into issue offsets: a wrapper in between would break them.
    test('puts text and chips directly inside the area, with no wrapper', () => {
      lockedWith(`Tom ${ph('&lt;br/&gt;')}Jerry`)
      const area = screen.getByText(/Tom/)
      expect(area).toHaveClass('errorTaggingArea')
      // eslint-disable-next-line testing-library/no-node-access
      expect(screen.getByText('<br/>').parentElement.parentElement).toBe(area)
      // eslint-disable-next-line testing-library/no-node-access
      expect(Array.from(area.childNodes).map((n) => n.textContent)).toEqual([
        'Tom ',
        '<br/>',
        'Jerry',
      ])
    })

    test('shows the latest version translation instead of the current one', () => {
      renderTarget({
        segment: buildSegment({
          edit_area_locked: true,
          translation: 'current',
          versions: [{translation: 'revised', issues: []}],
        }),
      })
      expect(screen.getByText('revised')).toHaveClass('errorTaggingArea')
      expect(screen.queryByText('current')).not.toBeInTheDocument()
    })
  })

  describe('filling tags in the target', () => {
    const fillTagsListener = () =>
      SegmentStore.addListener.mock.calls.find(
        ([event]) => event === 'FILL_TAGS_IN_TARGET',
      )[1]

    beforeEach(() => {
      jest.clearAllMocks()
      jest.useFakeTimers()
    })
    afterEach(() => jest.useRealTimers())

    test('replaces the edit area content with the auto-filled translation', () => {
      const {segment} = renderTarget()
      fillTagsListener()(segment.sid)
      jest.advanceTimersByTime(100)
      expect(SegmentActions.replaceEditAreaTextContent).toHaveBeenCalledWith(
        segment.sid,
        'auto-filled-translation',
      )
      expect(SegmentActions.getSegmentsQa).toHaveBeenCalled()
    })

    test('ignores the event for another segment', () => {
      renderTarget()
      fillTagsListener()('other-sid')
      jest.advanceTimersByTime(100)
      expect(SegmentActions.replaceEditAreaTextContent).not.toHaveBeenCalled()
    })
  })
})

describe('SegmentTarget editor engine', () => {
  beforeEach(() => {
    window.config = {target_code: 'en', isTargetRTL: false, isReview: false}
  })
  afterEach(() => {
    mockNewEditor = false
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(false)
  })

  test('with the new editor active the target opens in the CodeMirror editor, wired to the Draft bridge', () => {
    mockNewEditor = true
    renderTarget()
    const editor = screen.getByTestId('target-editor')
    expect(editor).toHaveAttribute('data-value', 'some translation')
    expect(editor).toHaveAttribute('data-bridge', 'target-bridge')
    expect(screen.queryByTestId('edit-area')).not.toBeInTheDocument()
  })

  test('with tag projection on, the CodeMirror target starts from the translation without tags', () => {
    mockNewEditor = true
    SegmentUtils.checkCurrentSegmentTPEnabled.mockReturnValue(true)
    renderTarget()
    const editor = screen.getByTestId('target-editor')
    expect(editor).toHaveAttribute('data-value', 'clean-some translation')
    expect(editor).toHaveAttribute('data-translation', 'some translation')
  })

  test('the toolbar reaches the CodeMirror target through the same handle as the Draft one', () => {
    mockNewEditor = true
    const {rerender, segment} = renderTarget()
    rerender(
      <SegmentContext.Provider
        value={{
          segment,
          removeSelection: jest.fn(),
          speech2textEnabledFn: jest.fn(() => false),
        }}
      >
        <SegmentTarget segment={segment} />
      </SegmentContext.Provider>,
    )
    const props = mockSegmentTargetToolbar.mock.calls.at(-1)[0]
    expect(props.editArea).toBe(mockTargetHandle)
    expect(props.addMissingSourceTagsToTarget).toBe(
      mockTargetHandle.addMissingSourceTagsToTarget,
    )
  })

  test('with the new editor off the target stays the Draft editor', () => {
    renderTarget()
    expect(screen.getByTestId('edit-area')).toBeInTheDocument()
    expect(screen.queryByTestId('target-editor')).not.toBeInTheDocument()
  })
})

describe('SegmentTarget issue selection', () => {
  beforeEach(() => {
    window.config = {target_code: 'en', isTargetRTL: false, isReview: true}
    jest.clearAllMocks()
  })

  const selectIn = (node, from, to) => {
    const range = document.createRange()
    range.setStart(node, from)
    range.setEnd(node, to)
    const selection = document.getSelection()
    selection.removeAllRanges()
    selection.addRange(range)
  }

  test('selecting text of the locked translation opens the issues panel with that selection', () => {
    jest.useFakeTimers()
    const removeSelection = jest.fn()
    renderTarget(
      {segment: buildSegment({edit_area_locked: true, translation: 'locked'})},
      {removeSelection},
    )
    const area = screen.getByText('locked')
    // eslint-disable-next-line testing-library/no-node-access
    selectIn(area.firstChild, 1, 4)
    act(() => {
      area.dispatchEvent(new MouseEvent('mouseup', {bubbles: true}))
      jest.runAllTimers()
    })
    expect(CursorUtils.getSelectionData).toHaveBeenCalledWith(
      expect.anything(),
      area,
    )
    expect(SegmentActions.openIssuesPanel).toHaveBeenCalledWith(
      {sid: '1-1', selection: {selectionData: true}},
      true,
    )
    expect(SegmentActions.showIssuesMessage).toHaveBeenCalledWith('1-1', 2)
    expect(removeSelection).not.toHaveBeenCalled()
    jest.useRealTimers()
  })

  test('a mouse up without a selection in the locked translation clears the selection instead', () => {
    jest.useFakeTimers()
    const removeSelection = jest.fn()
    renderTarget(
      {segment: buildSegment({edit_area_locked: true, translation: 'locked'})},
      {removeSelection},
    )
    const area = screen.getByText('locked')
    // eslint-disable-next-line testing-library/no-node-access
    selectIn(area.firstChild, 2, 2)
    act(() => {
      area.dispatchEvent(new MouseEvent('mouseup', {bubbles: true}))
      jest.runAllTimers()
    })
    expect(SegmentActions.openIssuesPanel).not.toHaveBeenCalled()
    expect(removeSelection).toHaveBeenCalled()
    expect(SegmentActions.showIssuesMessage).toHaveBeenCalledWith('1-1', 0)
    jest.useRealTimers()
  })
})
