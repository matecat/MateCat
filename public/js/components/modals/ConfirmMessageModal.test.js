import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import ConfirmMessageModal from './ConfirmMessageModal'

test('renders string text as HTML and only the default Confirm button when no callbacks are given', () => {
  render(<ConfirmMessageModal text="<b>hello</b>" onClose={jest.fn()} />)

  expect(screen.queryByText('Cancel')).not.toBeInTheDocument()
  expect(screen.queryByText('Confirm')).not.toBeInTheDocument()
})

test('renders node text as-is', () => {
  render(
    <ConfirmMessageModal
      text={<span>node content</span>}
      onClose={jest.fn()}
    />,
  )

  expect(screen.getByText('node content')).toBeInTheDocument()
})

test('cancel button calls cancelCallback and closes when closeOnSuccess is true', () => {
  const onClose = jest.fn()
  const cancelCallback = jest.fn()
  render(
    <ConfirmMessageModal
      text="t"
      onClose={onClose}
      closeOnSuccess
      cancelCallback={cancelCallback}
      cancelText="Nope"
    />,
  )

  fireEvent.click(screen.getByText('Nope'))

  expect(onClose).toHaveBeenCalledTimes(1)
  expect(cancelCallback).toHaveBeenCalledTimes(1)
})

test('cancel button is rendered with default label when only cancelText is provided', () => {
  render(<ConfirmMessageModal text="t" onClose={jest.fn()} cancelText="Skip" />)

  expect(screen.getByText('Skip')).toBeInTheDocument()
})

test('warning button calls warningCallback and respects closeOnSuccess', () => {
  const onClose = jest.fn()
  const warningCallback = jest.fn()
  render(
    <ConfirmMessageModal
      text="t"
      onClose={onClose}
      closeOnSuccess={false}
      warningCallback={warningCallback}
      warningText="Careful"
    />,
  )

  fireEvent.click(screen.getByText('Careful'))

  expect(onClose).not.toHaveBeenCalled()
  expect(warningCallback).toHaveBeenCalledTimes(1)
})

test('success button calls successCallback and closes with default label', () => {
  const onClose = jest.fn()
  const successCallback = jest.fn()
  render(
    <ConfirmMessageModal
      text="t"
      onClose={onClose}
      closeOnSuccess
      successCallback={successCallback}
    />,
  )

  fireEvent.click(screen.getByText('Confirm'))

  expect(onClose).toHaveBeenCalledTimes(1)
  expect(successCallback).toHaveBeenCalledTimes(1)
})

test('renders custom successText and modalName class', () => {
  const {container} = render(
    <ConfirmMessageModal
      text="t"
      onClose={jest.fn()}
      successText="Go"
      modalName="my-modal"
    />,
  )

  expect(screen.getByText('Go')).toBeInTheDocument()
  expect(container.querySelector('.modal-grid.my-modal')).toBeInTheDocument()
})
