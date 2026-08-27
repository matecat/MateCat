import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {AltLang} from './AltLang'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <AltLang
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('AltLang', () => {
  test('renders provider information and contact button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /contact altlang/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('Engine Name')).toBeInTheDocument()
    expect(screen.getByText('Key')).toBeInTheDocument()
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
      target: {value: 'My AltLang'},
    })
    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-secret'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        name: 'My AltLang',
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
