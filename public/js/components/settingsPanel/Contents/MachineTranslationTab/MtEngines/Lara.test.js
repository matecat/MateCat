import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {Lara} from './Lara'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <Lara
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('Lara', () => {
  test('renders provider information and learn more button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /learn more/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('ModernMT License')).toBeInTheDocument()
  })

  test('does not call addMTEngine when required fields are empty', async () => {
    const {addMTEngine} = setup()

    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).not.toHaveBeenCalled()
    })
  })

  test('submits form data through addMTEngine, including optional license', async () => {
    const {addMTEngine, container} = setup()

    fireEvent.change(
      container.querySelector('input[name="lara-access-key-id"]'),
      {target: {value: 'my-key-id'}},
    )
    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-secret'},
    })
    fireEvent.change(container.querySelector('input[name="mmt-license"]'), {
      target: {value: 'my-license'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        'lara-access-key-id': 'my-key-id',
        secret: 'my-secret',
        'mmt-license': 'my-license',
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
