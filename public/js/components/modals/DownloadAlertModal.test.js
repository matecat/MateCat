import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {DownloadAlertModal} from './DownloadAlertModal'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../actions/ModalsActions')

const originalConfig = {...global.config}
afterEach(() => {
  jest.clearAllMocks()
  global.config = {...originalConfig}
})

test('owner sees the plain Download option and can use it', () => {
  global.config.ownerIsMe = true
  const successCallback = jest.fn()
  const successCallbackWithoutErrors = jest.fn()
  const cancelCallback = jest.fn()
  render(
    <DownloadAlertModal
      successCallback={successCallback}
      successCallbackWithoutErrors={successCallbackWithoutErrors}
      cancelCallback={cancelCallback}
    />,
  )

  expect(screen.getByText('Download')).toBeInTheDocument()
  fireEvent.click(screen.getByText('Download'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(successCallbackWithoutErrors).toHaveBeenCalledTimes(1)
})

test('non-owner does not see the plain Download option', () => {
  global.config.ownerIsMe = false
  render(
    <DownloadAlertModal
      successCallback={jest.fn()}
      successCallbackWithoutErrors={jest.fn()}
      cancelCallback={jest.fn()}
    />,
  )

  expect(screen.queryByText('Download')).not.toBeInTheDocument()
  expect(screen.getByText('Download with markers')).toBeInTheDocument()
})

test('"Fix issues" calls cancelCallback and closes the modal', () => {
  global.config.ownerIsMe = false
  const cancelCallback = jest.fn()
  render(
    <DownloadAlertModal
      successCallback={jest.fn()}
      successCallbackWithoutErrors={jest.fn()}
      cancelCallback={cancelCallback}
    />,
  )

  fireEvent.click(screen.getByText('Fix issues'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(cancelCallback).toHaveBeenCalledTimes(1)
})

test('"Download with markers" calls successCallback and closes the modal', () => {
  global.config.ownerIsMe = false
  const successCallback = jest.fn()
  render(
    <DownloadAlertModal
      successCallback={successCallback}
      successCallbackWithoutErrors={jest.fn()}
      cancelCallback={jest.fn()}
    />,
  )

  fireEvent.click(screen.getByText('Download with markers'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
  expect(successCallback).toHaveBeenCalledTimes(1)
})
