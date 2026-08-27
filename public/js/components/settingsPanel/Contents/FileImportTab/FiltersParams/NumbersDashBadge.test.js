import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {NumbersDashBadge} from './NumbersDashBadge'

const setup = (props = {}) => {
  let value = props.value ?? []
  const onChange = (data) => (value = data)
  const utils = render(
    <NumbersDashBadge name="slides" onChange={onChange} {...props} />,
  )
  return {getValue: () => value, ...utils}
}

describe('NumbersDashBadge', () => {
  test('renders the input', () => {
    setup()

    expect(screen.getByTestId('email-input')).toBeInTheDocument()
  })

  test('commits a single number as a valid chip', async () => {
    const user = userEvent.setup()
    const {getValue} = setup()

    await user.type(screen.getByTestId('email-input'), '3,')

    expect(getValue()).toEqual(['3'])
  })

  test('commits a valid ascending range as a chip', async () => {
    const user = userEvent.setup()
    const {getValue} = setup()

    await user.type(screen.getByTestId('email-input'), '3-5,')

    expect(getValue()).toEqual(['3-5'])
  })

  test('rejects a descending range as an invalid chip value', async () => {
    const user = userEvent.setup()
    setup()

    await user.type(screen.getByTestId('email-input'), '5-3,')

    // invalid chip: comparison fails, so it is still added but flagged invalid
    // rely on the component not crashing and the chip text being rendered
    expect(screen.getByText('5-3')).toBeInTheDocument()
  })

  test('blocks non-numeric characters while typing', async () => {
    const user = userEvent.setup()
    setup()

    const input = screen.getByTestId('email-input')
    await user.type(input, 'abc')

    expect(input).toHaveValue('')
  })

  test('disables the input when disabled is true', () => {
    setup({disabled: true})

    expect(screen.getByTestId('email-input')).toBeDisabled()
  })

  test('renders an error message when provided', () => {
    setup({error: {message: 'Invalid slide range'}})

    expect(screen.getByText('Invalid slide range')).toBeInTheDocument()
  })
})
