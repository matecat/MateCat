import {
  render,
  screen,
  fireEvent,
  createEvent,
  waitFor,
} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import SearchInput from './SearchInput'

test('Calls onChange with the typed value after the debounce delay', async () => {
  const onChange = jest.fn()
  render(<SearchInput onChange={onChange} />)

  await userEvent.type(screen.getByTestId('input-search-projects'), 'tesla')

  expect(onChange).not.toHaveBeenCalledWith('tesla')

  await waitFor(() => expect(onChange).toHaveBeenCalledWith('tesla'), {
    timeout: 1000,
  })
})

test('Pressing Enter prevents the default behavior and keeps the value', () => {
  const onChange = jest.fn()
  render(<SearchInput onChange={onChange} />)

  const input = screen.getByTestId('input-search-projects')

  fireEvent.change(input, {target: {value: 'tesla'}})

  const enterEvent = createEvent.keyPress(input, {
    key: 'Enter',
    which: 13,
    keyCode: 13,
  })
  fireEvent(input, enterEvent)

  expect(enterEvent.defaultPrevented).toBe(true)
  expect(input).toHaveValue('tesla')
})
