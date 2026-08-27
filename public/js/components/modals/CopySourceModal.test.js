import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import Cookies from 'js-cookie'
import CopySourceModal from './CopySourceModal'
import ModalsActions from '../../actions/ModalsActions'
import {COPY_SOURCE_COOKIE} from '../../constants/ModalKeys'

jest.mock('../../actions/ModalsActions')
jest.mock('js-cookie', () => ({set: jest.fn()}))

afterEach(() => {
  jest.clearAllMocks()
  sessionStorage.clear()
})

test('choosing "ALL new segments" confirms, persists the checkbox and closes the modal', () => {
  const confirmCopyAllSources = jest.fn()
  const abortCopyAllSources = jest.fn()
  render(
    <CopySourceModal
      confirmCopyAllSources={confirmCopyAllSources}
      abortCopyAllSources={abortCopyAllSources}
    />,
  )

  fireEvent.click(screen.getByLabelText(/Don't show this dialog again/))
  fireEvent.click(screen.getByText('ALL new segments'))

  expect(confirmCopyAllSources).toHaveBeenCalledTimes(1)
  expect(abortCopyAllSources).not.toHaveBeenCalled()
  expect(sessionStorage.getItem(COPY_SOURCE_COOKIE)).toBe('0')
  expect(Cookies.set).toHaveBeenCalledWith(COPY_SOURCE_COOKIE, '0', {
    expires: 1,
    secure: true,
  })
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('choosing "This segment only" aborts and closes the modal without checking the box', () => {
  const confirmCopyAllSources = jest.fn()
  const abortCopyAllSources = jest.fn()
  render(
    <CopySourceModal
      confirmCopyAllSources={confirmCopyAllSources}
      abortCopyAllSources={abortCopyAllSources}
    />,
  )

  fireEvent.click(screen.getByText('This segment only'))

  expect(abortCopyAllSources).toHaveBeenCalledTimes(1)
  expect(confirmCopyAllSources).not.toHaveBeenCalled()
  expect(sessionStorage.getItem(COPY_SOURCE_COOKIE)).toBeNull()
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})
