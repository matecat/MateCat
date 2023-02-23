import {render, waitFor, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import InputField from './InputField'

test('works properly', async () => {
  const onFieldChanged = jest.fn()
  act(() => {
    render(<InputField onFieldChanged={onFieldChanged} />)
  })

  const elInput = screen.getByTestId('input')

  expect(elInput).toBeVisible()
  expect(elInput).toHaveValue('')
  expect(elInput).toHaveAttribute('type', 'text')
  act(() => {
    userEvent.type(elInput, 'something')
  })

  await waitFor(() => {
    expect(elInput).toHaveValue('something')
    expect(screen.queryByTestId('reset-button')).not.toBeInTheDocument()
  })

  await waitFor(() => {
    expect(onFieldChanged).toHaveBeenCalledTimes(1)
    expect(onFieldChanged).toHaveBeenCalledWith('something')
  })
})

test('supports reset button', () => {
  const onFieldChanged = jest.fn()
  act(() => {
    render(
      <InputField
        value="something"
        onFieldChanged={onFieldChanged}
        showCancel
      />,
    )
  })

  const elInput = screen.getByTestId('input')
  expect(elInput).toHaveValue('something')

  const elResetButton = screen.getByTestId('reset-button')
  expect(elResetButton).toBeVisible()
  act(() => {
    userEvent.click(elResetButton)
  })

  setTimeout(() => {
    expect(elResetButton).not.toBeInTheDocument()
    expect(elInput).toHaveValue('')
    expect(onFieldChanged).toHaveBeenCalledTimes(1)
    expect(onFieldChanged).toHaveBeenCalledWith('')
  })
})

test('supports type prop', () => {
  const onFieldChanged = jest.fn()
  render(<InputField onFieldChanged={onFieldChanged} type="password" />)

  expect(screen.getByTestId('input')).toHaveAttribute('type', 'password')
})
