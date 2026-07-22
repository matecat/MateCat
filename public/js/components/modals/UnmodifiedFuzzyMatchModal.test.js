import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {
  UnmodifiedFuzzyMatchModal,
  HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE,
} from './UnmodifiedFuzzyMatchModal'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../actions/ModalsActions')

afterEach(() => {
  jest.clearAllMocks()
  localStorage.clear()
})

test('confirming persists the checkbox, closes the modal and calls successCallback', () => {
  const successCallback = jest.fn()
  const cancelCallback = jest.fn()
  render(
    <UnmodifiedFuzzyMatchModal
      successCallback={successCallback}
      cancelCallback={cancelCallback}
    />,
  )

  fireEvent.click(screen.getByLabelText(/Don't show this dialog again/))
  fireEvent.click(screen.getByText('Confirm'))

  expect(localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE)).toBe(
    '1',
  )
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(successCallback).toHaveBeenCalledTimes(1)
  expect(cancelCallback).not.toHaveBeenCalled()
})

test('canceling closes the modal and calls cancelCallback without persisting the checkbox', () => {
  const successCallback = jest.fn()
  const cancelCallback = jest.fn()
  render(
    <UnmodifiedFuzzyMatchModal
      successCallback={successCallback}
      cancelCallback={cancelCallback}
    />,
  )

  fireEvent.click(screen.getByText('Cancel'))

  expect(
    localStorage.getItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE),
  ).toBeNull()
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(cancelCallback).toHaveBeenCalledTimes(1)
  expect(successCallback).not.toHaveBeenCalled()
})

test('works without optional callbacks provided', () => {
  render(<UnmodifiedFuzzyMatchModal />)

  expect(() => fireEvent.click(screen.getByText('Confirm'))).not.toThrow()
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})
