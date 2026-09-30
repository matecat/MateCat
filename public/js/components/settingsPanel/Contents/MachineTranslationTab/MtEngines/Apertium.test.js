import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {Apertium} from './Apertium'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <Apertium
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('Apertium', () => {
  test('renders provider information and contact button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /contact apertium/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('Engine Name')).toBeInTheDocument()
  })

  test('shows validation errors when submitting an empty form', async () => {
    setup()

    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(screen.getAllByText('Required field')).toHaveLength(2)
    })
  })

  test('submits form data through addMTEngine', async () => {
    const {addMTEngine, container} = setup()

    fireEvent.change(container.querySelector('input[name="name"]'), {
      target: {value: 'My Apertium'},
    })
    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-secret'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        name: 'My Apertium',
        secret: 'my-secret',
      })
    })
  })

  test('cancel button calls setAddMTVisible(false)', () => {
    const {setAddMTVisible, container} = setup()

    fireEvent.click(container.querySelector('.container-actions button'))

    expect(setAddMTVisible).toHaveBeenCalledWith(false)
  })

  test('confirm button is disabled while request is in progress', () => {
    setup({isRequestInProgress: true})

    expect(screen.getByRole('button', {name: /confirm/i})).toBeDisabled()
  })

  test('renders an API error message', () => {
    setup({error: {message: 'Invalid key'}})

    expect(screen.getByText('Invalid key')).toBeInTheDocument()
  })
})
