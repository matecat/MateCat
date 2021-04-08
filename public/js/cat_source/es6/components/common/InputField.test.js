import {render, waitFor, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import InputField from './InputField'

test('works properly', async () => {
  const onFieldChanged = jest.fn()
  render(<InputField onFieldChanged={onFieldChanged} />)

  const elInput = document.querySelector('input')
  
  expect(elInput).toBeVisible()
  expect(elInput).toHaveValue('')
  expect(elInput).toHaveAttribute('type', 'text')
  
  userEvent.type(elInput, 'something')
  
  expect(elInput).toHaveValue('something')
  expect(screen.queryByTestId('reset-button')).not.toBeInTheDocument()
  
  await waitFor(() => {
    expect(onFieldChanged).toHaveBeenCalledTimes(1)
    expect(onFieldChanged).toHaveBeenCalledWith('something')
  })
})

test('supports reset button', () => {
  const onFieldChanged = jest.fn()
  render(<InputField value="something" onFieldChanged={onFieldChanged} showCancel />)
  
  
  const elInput = document.querySelector('input')
  expect(elInput).toHaveValue('something')
  
  const elResetButton = screen.getByTestId('reset-button')
  expect(elResetButton).toBeVisible()
  
  userEvent.click(elResetButton)
  
  expect(elResetButton).not.toBeInTheDocument()
  expect(elInput).toHaveValue('')
  expect(onFieldChanged).toHaveBeenCalledTimes(1)
  expect(onFieldChanged).toHaveBeenCalledWith('')
})

test('supports type prop', () => {
  const onFieldChanged = jest.fn()
  render(<InputField onFieldChanged={onFieldChanged} type="password" />)

  expect(document.querySelector('input')).toHaveAttribute('type', 'password')
})
