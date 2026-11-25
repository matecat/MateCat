import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {SmartMate} from './SmartMate'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <SmartMate
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('SmartMate', () => {
  test('renders provider information and contact button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /contact smartmate/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('Engine Name')).toBeInTheDocument()
    expect(screen.getByText('User')).toBeInTheDocument()
    expect(screen.getByText('Key')).toBeInTheDocument()
  })

  test('shows validation errors when submitting an empty form', async () => {
    setup()

    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(screen.getAllByText('Required field')).toHaveLength(3)
    })
  })

  test('submits form data through addMTEngine', async () => {
    const {addMTEngine, container} = setup()

    fireEvent.change(container.querySelector('input[name="name"]'), {
      target: {value: 'My SmartMate'},
    })
    fireEvent.change(container.querySelector('input[name="client_id"]'), {
      target: {value: 'my-user'},
    })
    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-secret'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        name: 'My SmartMate',
        client_id: 'my-user',
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
