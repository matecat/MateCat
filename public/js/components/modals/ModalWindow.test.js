import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {createRoot} from 'react-dom/client'
import React from 'react'

import {ModalWindow, onModalWindowMounted} from './ModalWindow'
import AppDispatcher from '../../stores/AppDispatcher'
import CatToolStore from '../../stores/CatToolStore'
import ModalsConstants from '../../constants/ModalsConstants'
import ModalsActions from '../../actions/ModalsActions'

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
  act(() => {
    render(<ModalWindow />)
  })
  const onClose = jest.fn()
  const onCloseCallback = jest.fn()
  act(() => {
    ModalsActions.showModalComponent(
      DummyComponent,
      {onCloseCallback},
      'Random title',
      undefined,
      onClose,
    )
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

  act(() => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.CLOSE_MODAL,
    })
  })

  expect(onCloseCallback).toHaveBeenCalledTimes(1)
  expect(elButtonClose).not.toBeVisible()
})

test('works properly ModalOverlay version', () => {
  act(() => {
    render(<ModalWindow />)
  })
  const onClose = jest.fn()
  const onCloseCallback = jest.fn()
  act(() => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.SHOW_MODAL,
      component: DummyComponent,
      props: {onCloseCallback, overlay: true},
      title: 'Random title',
      onCloseCallback: onClose,
    })
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

  act(() => {
    AppDispatcher.dispatch({
      actionType: ModalsConstants.CLOSE_MODAL,
    })
  })

  expect(onCloseCallback).toHaveBeenCalledTimes(1)
  expect(elButtonClose).not.toBeVisible()
  expect(elTitle).not.toBeVisible()
})

test('onModalWindowMounted resolves once the component mounts', async () => {
  const mountedPromise = onModalWindowMounted()

  render(<ModalWindow />)

  const result = await Promise.race([
    mountedPromise.then(() => 'resolved'),
    new Promise((resolve) => setTimeout(() => resolve('timeout'), 1000)),
  ])

  expect(result).toBe('resolved')
})

test('unmounting removes the CatToolStore listeners', () => {
  const baselineShowCount = CatToolStore.listenerCount(
    ModalsConstants.SHOW_MODAL,
  )
  const baselineCloseCount = CatToolStore.listenerCount(
    ModalsConstants.CLOSE_MODAL,
  )

  const {unmount} = render(<ModalWindow />)

  expect(CatToolStore.listenerCount(ModalsConstants.SHOW_MODAL)).toBe(
    baselineShowCount + 1,
  )
  expect(CatToolStore.listenerCount(ModalsConstants.CLOSE_MODAL)).toBe(
    baselineCloseCount + 1,
  )

  unmount()

  expect(CatToolStore.listenerCount(ModalsConstants.SHOW_MODAL)).toBe(
    baselineShowCount,
  )
  expect(CatToolStore.listenerCount(ModalsConstants.CLOSE_MODAL)).toBe(
    baselineCloseCount,
  )
})
