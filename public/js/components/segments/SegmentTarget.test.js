import React from 'react'
import {render, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentTarget from './SegmentTarget'
import {SegmentContext} from './SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {
  removeTagsFromText,
  textHasTags,
} from './utils/DraftMatecatUtils/tagUtils'
import OfflineUtils from '../../utils/offlineUtils'

jest.mock('./Editarea', () => (props) => (
  <div
    data-testid="edit-area"
    data-translation={props.translation}
    data-sid={props.segment?.sid}
  />
))

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

jest.mock('./utils/DraftMatecatUtils', () => ({
  transformTagsToHtml: jest.fn((text) => text),
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
  getRelativeTransUnitCharactersCounter: jest.fn(({charactersCounter}) => ({
    segmentCharacters: charactersCounter?.segmentCharacters ?? 0,
    unitCharacters: charactersCounter?.counter ?? 0,
  })),
}))

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
  const ref = React.createRef()
  const defaultContext = {
    segment,
    removeSelection: jest.fn(),
    speech2textEnabledFn: jest.fn(() => false),
  }
  const utils = render(
    <SegmentContext.Provider value={{...defaultContext, ...contextValue}}>
      <SegmentTarget {...{segment}} {...props} ref={ref} />
    </SegmentContext.Provider>,
  )
  return {...utils, ref, segment}
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
    expect(container.querySelector('.target.item')).toHaveClass(
      'target-en',
    )
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
      const lockButton = container.querySelector(
        '.revise-lock-editArea-active',
      )
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
          versions: [{translation: 'versioned text'}],
        }),
      })
      expect(DraftMatecatUtils.transformTagsToHtml).toHaveBeenCalledWith(
        'versioned text',
        false,
      )
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
      expect(lastCallProps.qrLink).toBe('/revise-summary/10-pwd?revision_type=1&id_segment=2-1')
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

  describe('instance methods', () => {
    test('autoFillTagsInTarget replaces target content when sid matches', () => {
      jest.useFakeTimers()
      const {ref, segment} = renderTarget()

      act(() => {
        ref.current.autoFillTagsInTarget(segment.sid)
        jest.advanceTimersByTime(150)
      })

      expect(SegmentActions.replaceEditAreaTextContent).toHaveBeenCalledWith(
        segment.sid,
        'auto-filled-translation',
      )
      expect(SegmentActions.getSegmentsQa).toHaveBeenCalledWith(segment)
      jest.useRealTimers()
    })

    test('autoFillTagsInTarget is a no-op for a different sid', () => {
      jest.useFakeTimers()
      const {ref} = renderTarget()

      act(() => {
        ref.current.autoFillTagsInTarget('other-sid')
        jest.advanceTimersByTime(150)
      })

      expect(SegmentActions.replaceEditAreaTextContent).not.toHaveBeenCalled()
      jest.useRealTimers()
    })

    test('lockEditArea shows the issues message when not already locked', () => {
      const {ref, segment} = renderTarget({
        segment: buildSegment({edit_area_locked: false}),
      })
      const fakeEvent = {preventDefault: jest.fn()}

      act(() => {
        ref.current.lockEditArea(fakeEvent)
      })

      expect(fakeEvent.preventDefault).toHaveBeenCalled()
      expect(SegmentActions.showIssuesMessage).toHaveBeenCalledWith(
        segment.sid,
        0,
      )
      expect(SegmentActions.lockEditArea).toHaveBeenCalledWith(
        segment.sid,
        segment.fid,
      )
    })

    test('removeTagsFromText replaces the edit area content with the cleaned text', () => {
      const {ref, segment} = renderTarget({
        segment: buildSegment({translation: 'raw <ph/> text'}),
      })

      act(() => {
        ref.current.removeTagsFromText()
      })

      expect(removeTagsFromText).toHaveBeenCalledWith('raw <ph/> text')
      expect(SegmentActions.replaceEditAreaTextContent).toHaveBeenCalledWith(
        segment.sid,
        'clean-raw <ph/> text',
      )
    })

    test('toggleFormatMenu(true) shows the format menu immediately', () => {
      const {ref} = renderTarget()

      act(() => {
        ref.current.toggleFormatMenu(true)
      })

      expect(ref.current.state.showFormatMenu).toBe(true)
    })

    test('toggleFormatMenu(false) hides the format menu after a delay', () => {
      jest.useFakeTimers()
      const {ref} = renderTarget()

      act(() => {
        ref.current.toggleFormatMenu(true)
      })
      expect(ref.current.state.showFormatMenu).toBe(true)

      act(() => {
        ref.current.toggleFormatMenu(false)
        jest.advanceTimersByTime(250)
      })

      expect(ref.current.state.showFormatMenu).toBe(false)
      jest.useRealTimers()
    })

    test('updateCounter updates character counter state and dispatches characterCounter action', () => {
      jest.useFakeTimers()
      const {ref, segment} = renderTarget({
        segment: buildSegment({metadata: []}),
      })

      act(() => {
        ref.current.updateCounter({counter: 12, segmentCharacters: 12})
      })
      act(() => {
        jest.advanceTimersByTime(10)
      })

      expect(ref.current.state.charactersCounter).toBe(12)
      expect(SegmentActions.characterCounter).toHaveBeenCalledWith(
        expect.objectContaining({sid: segment.sid, counter: 12}),
      )
      jest.useRealTimers()
    })

    test('componentDidUpdate picks up a matching sizeRestriction metadata limit', () => {
      jest.useFakeTimers()
      const {ref} = renderTarget({
        segment: buildSegment({
          sid: '1-1',
          metadata: [
            {
              meta_key: 'sizeRestriction',
              id_segment: '1-1',
              meta_value: 42,
            },
          ],
        }),
      })

      act(() => {
        ref.current.updateCounter({counter: 1, segmentCharacters: 1})
        jest.advanceTimersByTime(10)
      })

      expect(ref.current.state.charactersCounterLimit).toBe(42)
      jest.useRealTimers()
    })
  })
})
