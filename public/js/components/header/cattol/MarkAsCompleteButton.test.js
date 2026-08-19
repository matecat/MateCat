import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {MarkAsCompleteButton} from './MarkAsCompleteButton'
import ConfirmMessageModal from '../../modals/ConfirmMessageModal'
import AlertModal from '../../modals/AlertModal'
import {setChunkComplete} from '../../../api/setChunkComplete'
import {deleteCompletionEvents} from '../../../api/deleteCompletionEvents'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import CattolConstants from '../../../constants/CatToolConstants'
import CatToolActions from '../../../actions/CatToolActions'
import ModalsActions from '../../../actions/ModalsActions'

jest.mock('../../../api/setChunkComplete')
jest.mock('../../../api/deleteCompletionEvents')
jest.mock('../../../actions/CatToolActions')
jest.mock('../../../actions/ModalsActions')

beforeEach(() => {
  global.config = {
    job_marked_complete: false,
    mark_as_complete_button_enabled: true,
    job_completion_current_phase: 'translate',
    id_job: '1',
    password: 'pass',
    currentPassword: 'current',
    chunk_completion_undoable: false,
    last_completion_event_id: null,
  }
  jest.clearAllMocks()
  jest.spyOn(SegmentStore, 'getGlobalWarnings').mockReturnValue({totals: {ERROR: []}})
  jest.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  console.error.mockRestore()
})

test('renders nothing when the feature is disabled', () => {
  const {container} = render(
    <MarkAsCompleteButton featureEnabled={false} isReview={false} />,
  )
  expect(container.firstChild).toBeNull()
})

test('shows the confirm modal on click when there are no unresolved errors', () => {
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    ConfirmMessageModal,
    expect.objectContaining({
      successText: 'Continue',
      cancelText: 'Cancel',
    }),
    'Confirmation required',
  )
  const call = ModalsActions.showModalComponent.mock.calls[0][1]
  expect(call.text).toContain('mark this job as completed')
})

test('shows the review confirm message when isReview is true', () => {
  render(<MarkAsCompleteButton featureEnabled={true} isReview={true} />)
  fireEvent.click(screen.getByRole('button'))
  const call = ModalsActions.showModalComponent.mock.calls[0][1]
  expect(call.text).toContain('allow translators to edit')
})

test('shows the fix-warnings modal instead when there are unresolved errors', () => {
  SegmentStore.getGlobalWarnings.mockReturnValue({
    totals: {ERROR: [{}, {}]},
  })
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    ConfirmMessageModal,
    expect.objectContaining({
      successText: 'Mark as complete',
      cancelText: 'Fix errors',
    }),
    'Confirmation required',
  )
})

test('does nothing on click when the button is disabled', () => {
  global.config.mark_as_complete_button_enabled = false
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
})

test('warns the translator on mount when the job moved to the revise phase', () => {
  global.config.job_completion_current_phase = 'revise'
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({uid: 'translate-warning', title: 'Warning'}),
  )
})

test('warns the reviser on mount when the translator has not completed the job yet', () => {
  global.config.job_completion_current_phase = 'translate'
  global.config.job_marked_complete = false
  render(<MarkAsCompleteButton featureEnabled={true} isReview={true} />)
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Warning',
      text: expect.stringContaining('did not mark this job as complete'),
    }),
  )
})

test('enables the button once translation stats indicate it is completable', () => {
  global.config.mark_as_complete_button_enabled = false
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  act(() => {
    CatToolStore.emit(CattolConstants.SET_PROGRESS, {
      raw: {rejected: 0},
    })
  })
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).toHaveBeenCalled()
})

test('enables the button for a review once enough segments are approved or translated', () => {
  global.config.job_completion_current_phase = 'revise'
  global.config.mark_as_complete_button_enabled = false
  render(<MarkAsCompleteButton featureEnabled={true} isReview={true} />)
  act(() => {
    CatToolStore.emit(CattolConstants.SET_PROGRESS, {
      raw: {approved: 1, translated: 0},
    })
  })
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).toHaveBeenCalled()
})

test('disables the button again when stats indicate it is not yet completable', () => {
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  act(() => {
    CatToolStore.emit(CattolConstants.SET_PROGRESS, {
      raw: {rejected: 2},
    })
  })
  fireEvent.click(screen.getByRole('button'))
  expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
})

test('marks the job as complete when the confirmation succeeds', async () => {
  setChunkComplete.mockResolvedValue({data: {event: {id: 42}}})
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  fireEvent.click(screen.getByRole('button'))

  const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
  await act(async () => successCallback())

  expect(setChunkComplete).toHaveBeenCalledWith({
    action: 'Features_ProjectCompletion_SetChunkCompleted',
    id_job: '1',
    password: 'current',
  })
})

test('shows an alert modal when marking the job as complete fails', async () => {
  setChunkComplete.mockRejectedValue(new Error('network error'))
  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)
  fireEvent.click(screen.getByRole('button'))

  const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
  await act(async () => successCallback())

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    AlertModal,
    expect.objectContaining({
      text: expect.stringContaining('An error occurred'),
    }),
    'Error',
  )
})

test('undoes the completion event when the undo link is clicked', async () => {
  global.config.job_completion_current_phase = 'revise'
  global.config.chunk_completion_undoable = true
  global.config.last_completion_event_id = 99
  deleteCompletionEvents.mockResolvedValue({})

  render(<MarkAsCompleteButton featureEnabled={true} isReview={false} />)

  const link = document.createElement('a')
  link.id = 'showTranslateWarningMessageUndoLink'
  document.body.appendChild(link)

  await act(async () => fireEvent.click(link))

  expect(deleteCompletionEvents).toHaveBeenCalledTimes(1)
  document.body.removeChild(link)
})
