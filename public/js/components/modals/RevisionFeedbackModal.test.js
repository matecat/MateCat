import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import RevisionFeedbackModal from './RevisionFeedbackModal'
import CatToolActions from '../../actions/CatToolActions'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../actions/CatToolActions')
jest.mock('../../actions/ModalsActions')

afterEach(() => jest.clearAllMocks())

test('shows the translator message for revisionNumber 1 and Submit is disabled until edited', () => {
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  expect(
    screen.getByText(/leave some feedback for the translator/),
  ).toBeInTheDocument()
  expect(screen.getByText('Submit')).toBeDisabled()
})

test('textarea is pre-filled from props.feedback but Modify stays disabled until edited (pre-existing quirk)', () => {
  render(
    <RevisionFeedbackModal revisionNumber={1} feedback="Existing feedback" />,
  )

  expect(screen.getByPlaceholderText('Leave your feedback here')).toHaveValue(
    'Existing feedback',
  )
  expect(screen.getByText('Modify')).toBeDisabled()
})

test('shows the reviser message for revisionNumber other than 1', () => {
  render(<RevisionFeedbackModal revisionNumber={2} feedback="" />)

  expect(
    screen.getByText(/leave some feedback for the reviser/),
  ).toBeInTheDocument()
})

test('typing feedback enables Submit, and sending it closes the modal and notifies success', async () => {
  CatToolActions.sendRevisionFeedback.mockResolvedValue()
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'Great job'}})
  expect(screen.getByText('Submit')).toBeEnabled()

  fireEvent.click(screen.getByText('Submit'))

  expect(CatToolActions.sendRevisionFeedback).toHaveBeenCalledWith('Great job')
  await waitFor(() =>
    expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1),
  )
  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Feedback submitted', type: 'success'}),
    ),
  )
})

test('while the send promise is pending, the button shows the disabled sending state with a spinner', async () => {
  let resolvePromise
  CatToolActions.sendRevisionFeedback.mockReturnValue(
    new Promise((resolve) => {
      resolvePromise = resolve
    }),
  )
  const {container} = render(
    <RevisionFeedbackModal revisionNumber={1} feedback="" />,
  )

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'Great job'}})
  fireEvent.click(screen.getByText('Submit'))

  expect(screen.getByText('Submit')).toBeDisabled()
  expect(container.innerHTML).toContain('button-loader show')
  expect(ModalsActions.onCloseModal).not.toHaveBeenCalled()

  resolvePromise()
  await waitFor(() =>
    expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1),
  )
})

test('on success, reloadQualityReport is fired (wrapped in a bare setTimeout)', async () => {
  CatToolActions.sendRevisionFeedback.mockResolvedValue()
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'Great job'}})
  fireEvent.click(screen.getByText('Submit'))

  await waitFor(() =>
    expect(CatToolActions.reloadQualityReport).toHaveBeenCalledTimes(1),
  )
})

test('on failure, onCloseModal and reloadQualityReport are not called', async () => {
  CatToolActions.sendRevisionFeedback.mockRejectedValue(new Error('fail'))
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'oops'}})
  fireEvent.click(screen.getByText('Submit'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Feedback not sent', type: 'error'}),
    ),
  )
  expect(ModalsActions.onCloseModal).not.toHaveBeenCalled()
  expect(CatToolActions.reloadQualityReport).not.toHaveBeenCalled()
})

test('clearing the feedback disables Submit again', () => {
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'Great job'}})
  fireEvent.change(textarea, {target: {value: ''}})

  expect(screen.getByText('Submit')).toBeDisabled()
})

test('shows an error notification when sending feedback fails', async () => {
  CatToolActions.sendRevisionFeedback.mockRejectedValue(new Error('fail'))
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  const textarea = screen.getByPlaceholderText('Leave your feedback here')
  fireEvent.change(textarea, {target: {value: 'oops'}})
  fireEvent.click(screen.getByText('Submit'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Feedback not sent', type: 'error'}),
    ),
  )
})

test('when feedback already exists the buttons read Modify/Close instead of Submit', () => {
  render(
    <RevisionFeedbackModal revisionNumber={1} feedback="Existing feedback" />,
  )

  expect(screen.getByText('Close')).toBeInTheDocument()
  expect(screen.getByText('Modify')).toBeInTheDocument()
})

test('clicking the dismiss button closes the modal without sending', () => {
  render(<RevisionFeedbackModal revisionNumber={1} feedback="" />)

  fireEvent.click(screen.getByText("I'll do it later"))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(CatToolActions.sendRevisionFeedback).not.toHaveBeenCalled()
})
