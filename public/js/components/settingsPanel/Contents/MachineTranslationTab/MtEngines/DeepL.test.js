import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {DeepL} from './DeepL'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <DeepL
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('DeepL', () => {
  test('renders provider information and learn more button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /learn more/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('DeepL API key')).toBeInTheDocument()
  })

  test('does not submit and does not call addMTEngine when client_id is empty', async () => {
    const {addMTEngine} = setup()

    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).not.toHaveBeenCalled()
    })
  })

  test('submits form data through addMTEngine with default engine name', async () => {
    const {addMTEngine, container} = setup()

    fireEvent.change(container.querySelector('input[name="client_id"]'), {
      target: {value: 'my-license'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        name: 'DeepL',
        client_id: 'my-license',
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
