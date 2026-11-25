import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import InputField from './InputField'

test('works properly', async () => {
  jest.useFakeTimers()
  const user = userEvent.setup({advanceTimers: jest.advanceTimersByTime})
  const onFieldChanged = jest.fn()

  render(<InputField onFieldChanged={onFieldChanged} />)

  const elInput = screen.getByTestId('input')

  expect(elInput).toBeVisible()
  expect(elInput).toHaveValue('')
  expect(elInput).toHaveAttribute('type', 'text')

  await user.type(elInput, 'something')

  expect(elInput).toHaveValue('something')
  expect(screen.queryByTestId('reset-button')).not.toBeInTheDocument()

  // Wait for debounce (500ms)
  act(() => {
    jest.advanceTimersByTime(600)
  })

  expect(onFieldChanged).toHaveBeenCalledTimes(1)
  expect(onFieldChanged).toHaveBeenCalledWith('something')
  jest.useRealTimers()
})

test('supports reset button', async () => {
  const user = userEvent.setup()
  const onFieldChanged = jest.fn()

  render(
    <InputField value="something" onFieldChanged={onFieldChanged} showCancel />,
  )

  const elInput = screen.getByTestId('input')
  expect(elInput).toHaveValue('something')

  const elResetButton = screen.getByTestId('reset-button')
  expect(elResetButton).toBeVisible()

  await user.click(elResetButton)

  expect(screen.queryByTestId('reset-button')).not.toBeInTheDocument()
  expect(elInput).toHaveValue('')
  expect(onFieldChanged).toHaveBeenCalledTimes(1)
  expect(onFieldChanged).toHaveBeenCalledWith('')
})

test('supports type prop', () => {
  const onFieldChanged = jest.fn()
  render(<InputField onFieldChanged={onFieldChanged} type="password" />)

  expect(screen.getByTestId('input')).toHaveAttribute('type', 'password')
})
