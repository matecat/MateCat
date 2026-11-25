import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import {
  UnlockAllSegmentsModal,
  HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE,
} from './UnlockAllSegmentsModal'
import {getFilteredSegments} from '../../api/getFilteredSegments'
import SegmentActions from '../../actions/SegmentActions'
import SegmentStore from '../../stores/SegmentStore'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../api/getFilteredSegments')
jest.mock('../../actions/SegmentActions')
jest.mock('../../actions/ModalsActions')

afterEach(() => {
  jest.clearAllMocks()
  localStorage.clear()
  SegmentStore.consecutiveUnlockSegments = undefined
})

test('confirming unlocks the locked segments, persists the checkbox and closes the modal', async () => {
  getFilteredSegments.mockResolvedValue({segment_ids: [1, 2, 3]})
  render(<UnlockAllSegmentsModal />)

  fireEvent.click(screen.getByLabelText(/Don't show this dialog again/))
  fireEvent.click(screen.getByText('Confirm'))

  await waitFor(() =>
    expect(SegmentActions.unlockSegments).toHaveBeenCalledWith([1, 2, 3]),
  )
  // Every unmodified locked segment, whatever its match type: not only the 101% ones.
  expect(getFilteredSegments).toHaveBeenCalledWith(
    config.id_job,
    config.currentPassword,
    {sample: {type: 'locked'}},
  )
  expect(localStorage.getItem(HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE)).toBe('1')
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(SegmentStore.consecutiveUnlockSegments).toEqual([])
})

test('canceling clears consecutive unlocks and closes the modal without unlocking', () => {
  render(<UnlockAllSegmentsModal />)

  fireEvent.click(screen.getByText('Cancel'))

  expect(getFilteredSegments).not.toHaveBeenCalled()
  expect(SegmentStore.consecutiveUnlockSegments).toEqual([])
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(
    localStorage.getItem(HIDE_UNLOCK_ALL_SEGMENTS_MODAL_STORAGE),
  ).toBeNull()
})
