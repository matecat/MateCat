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

const SELECTED_SEGMENTS = [1201, 1202, 1203]

const selectSegments = (segments) =>
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

describe('BulkSelectionBar', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    config.id_job = 4321
    config.revisionNumber = 1
  })

  test('a bulk approve that goes through raises no error notification', async () => {
    SegmentActions.approveFilteredSegments.mockResolvedValue(undefined)

    render(<BulkSelectionBar isReview={true} />)
    selectSegments(SELECTED_SEGMENTS)

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
    selectSegments(SELECTED_SEGMENTS)

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
    selectSegments(SELECTED_SEGMENTS)

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
    selectSegments([77])

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
