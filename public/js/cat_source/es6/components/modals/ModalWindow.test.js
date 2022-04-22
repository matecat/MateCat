import {screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {createRoot} from 'react-dom/client'
import React from 'react'

import {ModalWindowComponent} from './ModalWindow'
import ModalsActions from '../../actions/ModalsActions'
import AppDispatcher from '../../stores/AppDispatcher'
import ModalsConstants from '../../constants/ModalsConstants'

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
  const mountPoint = createRoot(screen.getByTestId('modal'))
  mountPoint.render(<ModalWindowComponent />)

  const onClose = jest.fn()
  const onCloseCallback = jest.fn()
  AppDispatcher.dispatch({
    actionType: ModalsConstants.SHOW_MODAL,
    component: DummyComponent,
    props: {onCloseCallback},
    title: 'Random title',
    onCloseCallback: onClose,
  })

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
  const mountPoint = createRoot(screen.getByTestId('modal'))
  mountPoint.render(<ModalWindowComponent />)

  const onClose = jest.fn()
  const onCloseCallback = jest.fn()
  AppDispatcher.dispatch({
    actionType: ModalsConstants.SHOW_MODAL,
    component: DummyComponent,
    props: {onCloseCallback, overlay: true},
    title: 'Random title',
    onCloseCallback: onClose,
  })

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
