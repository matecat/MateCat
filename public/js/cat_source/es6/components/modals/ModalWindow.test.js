import {screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import ReactDOM from 'react-dom'
import React from 'react'

import {ModalWindow, ModalWindowComponent} from './ModalWindow'

const DummyComponent = () => {
  return <div>something</div>
}

beforeAll(() => {
  const div = document.createElement('div')
  div.id = 'modal'
  div.setAttribute('data-testid', 'modal')

  document.body.appendChild(div)
})

test('works properly', () => {
  const modalWindow = ReactDOM.render(
    <ModalWindowComponent />,
    screen.getByTestId('modal'),
  )

  const onClose = jest.fn()
  const onCloseCallback = jest.fn()

  modalWindow.showModalComponent(
    DummyComponent,
    {onCloseCallback},
    'Random title',
    null,
    onClose,
  )

  const elTitle = screen.getByRole('heading', {name: 'Random title'})
  expect(elTitle).toBeVisible()
  expect(screen.getByText('something')).toBeVisible()

  /**
   * clicking inside the area of the modal should not trigger
   * the close mechanism
   */
  userEvent.click(elTitle)

  expect(elTitle).toBeVisible()

  const elButtonClose = screen.getByTestId('close-button')
  expect(elButtonClose).toBeVisible()

  userEvent.click(elButtonClose)

  expect(onCloseCallback).toHaveBeenCalledTimes(1)
  expect(elButtonClose).not.toBeVisible()
})

test('works properly ModalOverlay version', () => {
  const modalWindow = ReactDOM.render(
    <ModalWindowComponent />,
    screen.getByTestId('modal'),
  )

  const onClose = jest.fn()
  const onCloseCallback = jest.fn()

  modalWindow.showModalComponent(
    DummyComponent,
    {onCloseCallback, overlay: true},
    'Random title',
    null,
    onClose,
  )

  const elTitle = screen.getByRole('heading', {name: 'Random title'})
  expect(elTitle).toBeVisible()
  expect(screen.getByText('something')).toBeVisible()

  /**
   * clicking inside the area of the modal should not trigger
   * the close mechanism
   */
  userEvent.click(elTitle)

  expect(elTitle).toBeVisible()

  const elButtonClose = screen.getByTestId('close-button')
  expect(elButtonClose).toBeVisible()

  userEvent.click(elButtonClose)

  expect(onCloseCallback).toHaveBeenCalledTimes(1)
  expect(elButtonClose).not.toBeVisible()
  expect(elTitle).not.toBeVisible()
})
