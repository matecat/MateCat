import React from 'react'
import {act, fireEvent, render, screen} from '@testing-library/react'
import ReviewExtendedPanel from './ReviewExtendedPanel'
import {SegmentContext} from '../segments/SegmentContext'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import SegmentUtils from '../../utils/segmentUtils'
import ModalsActions from '../../actions/ModalsActions'
import SegmentConstants from '../../constants/SegmentConstants'

jest.mock('./ReviewExtendedIssuesContainer', () => () => (
  <div data-testid="issues-container" />
))
jest.mock('./ReviewExtendedIssuePanel', () => () => (
  <div data-testid="issue-panel" />
))
jest.mock('../modals/ShortCutsModal', () => 'ShortCutsModal')

jest.mock('../../constants/SegmentConstants', () => ({
  SHOW_ISSUE_MESSAGE: 'SHOW_ISSUE_MESSAGE',
}))

jest.mock('../../utils/shortcuts', () => ({
  Shortcuts: {
    shortCutsKeyType: 'standard',
    cattol: {
      events: {
        navigateIssues: {
          equivalent: {
            standard: 'Ctrl + Alt + Arrows/Enter',
          },
        },
      },
    },
  },
}))

jest.mock('../../stores/SegmentStore', () => {
  const {EventEmitter} = require('events')
  const store = new EventEmitter()
  store.setMaxListeners(0)
  return store
})

jest.mock('../../actions/SegmentActions', () => ({
  closeSegmentIssuePanel: jest.fn(),
  unlockEditArea: jest.fn(),
}))

jest.mock('../../utils/segmentUtils', () => ({
  isIceSegment: jest.fn(() => false),
}))

jest.mock('../../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

const segmentContext = {removeSelection: jest.fn()}

const makeSegment = (overrides = {}) => ({
  sid: 'seg-1',
  translation: 'Hello world',
  versions: [],
  unlocked: false,
  ...overrides,
})

const renderPanel = (props = {}) =>
  render(
    <SegmentContext.Provider value={segmentContext}>
      <ReviewExtendedPanel
        segment={makeSegment()}
        isReview={true}
        selectionObj={null}
        {...props}
      />
    </SegmentContext.Provider>,
  )

describe('ReviewExtendedPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    SegmentUtils.isIceSegment.mockReturnValue(false)
  })

  afterEach(() => {
    SegmentStore.removeAllListeners()
  })

  describe('wrapper class', () => {
    test('has no thereAreIssues class when segment has no issues', () => {
      const {container} = renderPanel()
      expect(container.querySelector('.re-wrapper')).not.toHaveClass(
        'thereAreIssues',
      )
    })

    test('has thereAreIssues class when versions contain issues', () => {
      const {container} = renderPanel({
        segment: makeSegment({
          versions: [
            {version_number: 1, issues: [{id: 1, comment: 'mistake'}]},
          ],
        }),
      })
      expect(container.querySelector('.re-wrapper')).toHaveClass('thereAreIssues')
    })
  })

  describe('close button', () => {
    test('renders close button', () => {
      const {container} = renderPanel()
      expect(container.querySelector('.re-close-balloon')).toBeInTheDocument()
    })

    test('calls closeSegmentIssuePanel with segment sid on click', () => {
      const {container} = renderPanel()
      fireEvent.click(container.querySelector('.re-close-balloon'))
      expect(SegmentActions.closeSegmentIssuePanel).toHaveBeenCalledWith('seg-1')
    })
  })

  describe('child components', () => {
    test('renders ReviewExtendedIssuesContainer', () => {
      renderPanel()
      expect(screen.getByTestId('issues-container')).toBeInTheDocument()
    })

    test('renders ReviewExtendedIssuePanel when isReview is true and segment is not ICE-locked', () => {
      renderPanel({isReview: true})
      expect(screen.getByTestId('issue-panel')).toBeInTheDocument()
    })

    test('does not render ReviewExtendedIssuePanel when isReview is false', () => {
      renderPanel({isReview: false})
      expect(screen.queryByTestId('issue-panel')).not.toBeInTheDocument()
    })

    test('does not render ReviewExtendedIssuePanel when segment is ICE-locked and not unlocked', () => {
      SegmentUtils.isIceSegment.mockReturnValue(true)
      renderPanel({
        isReview: true,
        segment: makeSegment({unlocked: false}),
      })
      expect(screen.queryByTestId('issue-panel')).not.toBeInTheDocument()
    })

    test('renders ReviewExtendedIssuePanel when segment is ICE-locked but unlocked', () => {
      SegmentUtils.isIceSegment.mockReturnValue(true)
      renderPanel({
        isReview: true,
        segment: makeSegment({unlocked: true}),
      })
      expect(screen.getByTestId('issue-panel')).toBeInTheDocument()
    })
  })

  describe('SHOW_ISSUE_MESSAGE store event', () => {
    test('does not show approve-warning by default', () => {
      renderPanel()
      expect(
        screen.queryByText(/you must add an issue/i),
      ).not.toBeInTheDocument()
    })

    test('does not show selected-text warning by default', () => {
      renderPanel()
      expect(
        screen.queryByText(/select an issue from the list below/i),
      ).not.toBeInTheDocument()
    })

    test('shows approve-warning on event type 1', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      expect(
        screen.getByText(/you must add an issue from the list below/i),
      ).toBeInTheDocument()
    })

    test('shows settings note inside approve-warning on event type 1', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      expect(
        screen.getByText(/job owner and workspace members can disable/i),
      ).toBeInTheDocument()
    })

    test('shows keyboard shortcut link inside approve-warning on event type 1', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      expect(
        screen.getByText('Shortcut: Ctrl + Alt + Arrows/Enter'),
      ).toBeInTheDocument()
    })

    test('shows selected-text warning on event type 2', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 2)
      })
      expect(
        screen.getByText(/select an issue from the list below/i),
      ).toBeInTheDocument()
    })

    test('hides approve-warning when type 0 is emitted after type 1', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 0)
      })
      expect(
        screen.queryByText(/you must add an issue/i),
      ).not.toBeInTheDocument()
    })

    test('switching from type 1 to type 2 hides approve-warning and shows selected-text warning', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 2)
      })
      expect(
        screen.queryByText(/you must add an issue/i),
      ).not.toBeInTheDocument()
      expect(
        screen.getByText(/select an issue from the list below/i),
      ).toBeInTheDocument()
    })
  })

  describe('corner div classes', () => {
    test('has error class when approve-warning is active', () => {
      const {container} = renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      expect(container.querySelector('.error')).toBeInTheDocument()
    })

    test('has warning class when selected-text warning is active', () => {
      const {container} = renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 2)
      })
      expect(container.querySelector('.warning')).toBeInTheDocument()
    })

    test('has no error or warning class in default state', () => {
      const {container} = renderPanel()
      expect(container.querySelector('.error')).not.toBeInTheDocument()
      expect(container.querySelector('.warning')).not.toBeInTheDocument()
    })
  })

  describe('shortcut link', () => {
    test('clicking shortcut link opens ShortCutsModal via ModalsActions', () => {
      renderPanel()
      act(() => {
        SegmentStore.emit(SegmentConstants.SHOW_ISSUE_MESSAGE, 'seg-1', 1)
      })
      fireEvent.click(screen.getByText('Shortcut: Ctrl + Alt + Arrows/Enter'))
      expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
        'ShortCutsModal',
        null,
        'Shortcuts',
      )
    })
  })
})
