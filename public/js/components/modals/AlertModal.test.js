import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import AlertModal from './AlertModal'

test('renders string text as html and the default Ok button label', () => {
  render(<AlertModal text="<b>hi</b>" onClose={jest.fn()} />)

  expect(screen.getByText('Ok')).toBeInTheDocument()
})

test('renders node text as-is', () => {
  render(<AlertModal text={<span>node text</span>} onClose={jest.fn()} />)

  expect(screen.getByText('node text')).toBeInTheDocument()
})

test('renders a custom buttonText', () => {
  render(<AlertModal text="t" buttonText="Got it" onClose={jest.fn()} />)

  expect(screen.getByText('Got it')).toBeInTheDocument()
})

test('clicking the button calls successCallback but does not close by default', () => {
  const successCallback = jest.fn()
  const onClose = jest.fn()
  render(
    <AlertModal text="t" successCallback={successCallback} onClose={onClose} />,
  )

  fireEvent.click(screen.getByText('Ok'))

  expect(successCallback).toHaveBeenCalledTimes(1)
  expect(onClose).not.toHaveBeenCalled()
})

test('closeOnSuccess also closes the modal after calling successCallback', () => {
  const successCallback = jest.fn()
  const onClose = jest.fn()
  render(
    <AlertModal
      text="t"
      successCallback={successCallback}
      closeOnSuccess
      onClose={onClose}
    />,
  )

  fireEvent.click(screen.getByText('Ok'))

  expect(successCallback).toHaveBeenCalledTimes(1)
  expect(onClose).toHaveBeenCalledTimes(1)
})

test('works without a successCallback', () => {
  render(<AlertModal text="t" onClose={jest.fn()} />)

  expect(() => fireEvent.click(screen.getByText('Ok'))).not.toThrow()
})
