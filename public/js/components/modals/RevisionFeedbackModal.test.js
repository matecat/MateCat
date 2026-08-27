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
