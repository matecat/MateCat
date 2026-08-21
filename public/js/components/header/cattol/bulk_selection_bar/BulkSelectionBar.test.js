import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'

import BulkSelectionBar from './BulkSelectionBar'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'

jest.mock('../../../../actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    approveFilteredSegments: jest.fn(),
    translateFilteredSegments: jest.fn(),
    removeSegmentsOnBulk: jest.fn(),
  },
}))

jest.mock('../../../../actions/CatToolActions', () => ({
  __esModule: true,
  default: {
    addNotification: jest.fn(),
    onRender: jest.fn(),
    reloadQualityReport: jest.fn(),
  },
}))

const setSegments = (segments) =>
  act(() => {
    SegmentStore.emitChange(
      SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
      segments,
    )
  })

const errorNotifications = () =>
  CatToolActions.addNotification.mock.calls
    .map(([notification]) => notification)
    .filter((notification) => notification.type === 'error')

beforeEach(() => {
  global.config = {}
  jest.clearAllMocks()
})

test('renders nothing when there is no bulk selection', () => {
  const {container} = render(<BulkSelectionBar isReview={false} />)
  expect(container.firstChild).toBeNull()
})

test('shows the singular label for a single selected segment', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1])
  expect(screen.getByText('1 Segment selected')).toBeInTheDocument()
})

test('shows the plural label and translates the filtered segments when marking as translated', async () => {
  SegmentActions.translateFilteredSegments.mockResolvedValue()
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2])

  expect(screen.getByText('2 Segments selected')).toBeInTheDocument()

  await act(async () => {
    fireEvent.click(screen.getByText('MARK AS TRANSLATED'))
  })

  expect(SegmentActions.translateFilteredSegments).toHaveBeenCalledWith([1, 2])
  expect(CatToolActions.onRender).toHaveBeenCalledWith({segmentToOpen: 1})
  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('approves the filtered segments and reloads the quality report when reviewing', async () => {
  SegmentActions.approveFilteredSegments.mockResolvedValue()
  render(<BulkSelectionBar isReview={true} />)
  setSegments([5, 6])

  await act(async () => {
    fireEvent.click(screen.getByText('MARK AS APPROVED'))
  })

  expect(SegmentActions.approveFilteredSegments).toHaveBeenCalledWith([5, 6])
  expect(CatToolActions.onRender).toHaveBeenCalledWith({segmentToOpen: 5})
  expect(CatToolActions.reloadQualityReport).toHaveBeenCalledTimes(1)
  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('shows a loading state while the status change is in flight', async () => {
  let resolveApprove
  SegmentActions.approveFilteredSegments.mockReturnValue(
    new Promise((resolve) => {
      resolveApprove = resolve
    }),
  )
  render(<BulkSelectionBar isReview={true} />)
  setSegments([1])

  fireEvent.click(screen.getByText('MARK AS APPROVED'))

  expect(screen.getByText('Applying changes')).toBeInTheDocument()
  await act(async () => resolveApprove())
})

test('clears the selection when the back button is clicked', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2, 3])

  fireEvent.click(screen.getByText('back'))

  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('resets the selection when the store reports it was removed', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2, 3])
  expect(screen.getByText('3 Segments selected')).toBeInTheDocument()

  act(() => {
    SegmentStore.emitChange(SegmentConstants.REMOVE_SEGMENTS_ON_BULK)
  })

  expect(screen.queryByText(/Segments selected/)).toBeNull()
})

test('toggles a single segment in and out of the bulk selection', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2])

  act(() => {
    SegmentStore.emitChange(SegmentConstants.TOGGLE_SEGMENT_ON_BULK, 1)
  })
  expect(screen.getByText('1 Segment selected')).toBeInTheDocument()

  act(() => {
    SegmentStore.emitChange(SegmentConstants.TOGGLE_SEGMENT_ON_BULK, 1)
  })
  expect(screen.getByText('2 Segments selected')).toBeInTheDocument()
})

test('unmounting removes the SegmentStore listeners', () => {
  const baselineToggle = SegmentStore.listenerCount(
    SegmentConstants.TOGGLE_SEGMENT_ON_BULK,
  )
  const baselineRemove = SegmentStore.listenerCount(
    SegmentConstants.REMOVE_SEGMENTS_ON_BULK,
  )
  const baselineSet = SegmentStore.listenerCount(
    SegmentConstants.SET_BULK_SELECTION_SEGMENTS,
  )

  const {unmount} = render(<BulkSelectionBar isReview={false} />)

  expect(
    SegmentStore.listenerCount(SegmentConstants.TOGGLE_SEGMENT_ON_BULK),
  ).toBe(baselineToggle + 1)
  expect(
    SegmentStore.listenerCount(SegmentConstants.REMOVE_SEGMENTS_ON_BULK),
  ).toBe(baselineRemove + 1)
  expect(
    SegmentStore.listenerCount(SegmentConstants.SET_BULK_SELECTION_SEGMENTS),
  ).toBe(baselineSet + 1)

  unmount()

  expect(
    SegmentStore.listenerCount(SegmentConstants.TOGGLE_SEGMENT_ON_BULK),
  ).toBe(baselineToggle)
  expect(
    SegmentStore.listenerCount(SegmentConstants.REMOVE_SEGMENTS_ON_BULK),
  ).toBe(baselineRemove)
  expect(
    SegmentStore.listenerCount(SegmentConstants.SET_BULK_SELECTION_SEGMENTS),
  ).toBe(baselineSet)
})

// Refused bulk actions: SegmentActions rejects (403 from a stale/wrong-phase password, or a
// network error), and BulkSelectionBar must raise an error notification naming the job/revision
// and the segments that kept their previous status, rather than silently losing the selection.
describe('BulkSelectionBar refused bulk actions', () => {
  const SELECTED_SEGMENTS = [1201, 1202, 1203]

  beforeEach(() => {
    config.id_job = 4321
    config.revisionNumber = 1
  })

  test('a bulk approve that goes through raises no error notification', async () => {
    SegmentActions.approveFilteredSegments.mockResolvedValue(undefined)

    render(<BulkSelectionBar isReview={true} />)
    setSegments(SELECTED_SEGMENTS)

    await act(async () => {
      fireEvent.click(screen.getByText('MARK AS APPROVED'))
    })

    expect(SegmentActions.approveFilteredSegments).toHaveBeenCalledWith(
      SELECTED_SEGMENTS,
    )
    expect(errorNotifications()).toHaveLength(0)
  })

  test('a refused bulk approve names the segments that kept their status', async () => {
    SegmentActions.approveFilteredSegments.mockRejectedValue({
      response: {status: 403},
      errors: [{message: 'the review password does not open this phase'}],
    })

    render(<BulkSelectionBar isReview={true} />)
    setSegments(SELECTED_SEGMENTS)

    await act(async () => {
      fireEvent.click(screen.getByText('MARK AS APPROVED'))
    })

    const notifications = errorNotifications()
    expect(notifications).toHaveLength(1)

    const [notification] = notifications
    expect(notification.title).toBe(
      'The status of the selected segments was not changed',
    )
    expect(notification.autoDismiss).toBe(false)
    expect(notification.position).toBe('bl')
    expect(notification.allowHtml).toBeUndefined()

    const {getByText} = render(<div>{notification.text}</div>)
    expect(
      getByText(
        /3 segments kept their status: the change to APPROVED was refused because the review password does not open this phase\./,
      ),
    ).toBeInTheDocument()
    expect(
      getByText(/Job 4321, revision 1 — segments 1201, 1202, 1203/),
    ).toBeInTheDocument()
  })

  test('the selection and the button come back after a refused bulk approve', async () => {
    SegmentActions.approveFilteredSegments.mockRejectedValue(
      new Error('network down'),
    )

    render(<BulkSelectionBar isReview={true} />)
    setSegments(SELECTED_SEGMENTS)

    await act(async () => {
      fireEvent.click(screen.getByText('MARK AS APPROVED'))
    })

    expect(screen.queryByText('Applying changes')).not.toBeInTheDocument()
    expect(screen.getByText('MARK AS APPROVED')).toBeInTheDocument()
    expect(screen.getByText('3 Segments selected')).toBeInTheDocument()
    expect(SegmentActions.removeSegmentsOnBulk).not.toHaveBeenCalled()

    const [notification] = errorNotifications()
    const {getByText} = render(<div>{notification.text}</div>)
    expect(getByText(/refused because network down\./)).toBeInTheDocument()
  })

  test('a refused bulk translate reports the translate phase and its own segments', async () => {
    SegmentActions.translateFilteredSegments.mockRejectedValue({
      errors: {message: 'the segments are locked'},
    })
    config.revisionNumber = null

    render(<BulkSelectionBar isReview={false} />)
    setSegments([77])

    await act(async () => {
      fireEvent.click(screen.getByText('MARK AS TRANSLATED'))
    })

    const notifications = errorNotifications()
    expect(notifications).toHaveLength(1)
    expect(notifications[0].autoDismiss).toBe(false)

    const {getByText} = render(<div>{notifications[0].text}</div>)
    expect(
      getByText(
        /1 segments kept their status: the change to TRANSLATED was refused because the segments are locked\./,
      ),
    ).toBeInTheDocument()
    expect(getByText(/Job 4321 — segments 77/)).toBeInTheDocument()
  })
})
