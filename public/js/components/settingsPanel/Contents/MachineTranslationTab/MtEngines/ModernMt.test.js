import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {ModernMt} from './ModernMt'

const setup = (props = {}) => {
  const addMTEngine = jest.fn()
  const setAddMTVisible = jest.fn()
  const utils = render(
    <ModernMt
      addMTEngine={addMTEngine}
      setAddMTVisible={setAddMTVisible}
      error={undefined}
      isRequestInProgress={false}
      {...props}
    />,
  )
  return {addMTEngine, setAddMTVisible, ...utils}
}

describe('ModernMt', () => {
  beforeEach(() => {
    global.config = Object.assign(global.config ?? {}, {
      isAnInternalUser: false,
    })
  })

  test('renders provider information and learn more button', () => {
    setup()

    expect(
      screen.getByRole('button', {name: /learn more/i}),
    ).toBeInTheDocument()
    expect(screen.getByText('ModernMT License')).toBeInTheDocument()
  })

  test('shows validation error when submitting an empty form', async () => {
    setup()

    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(screen.getByText('Required field')).toBeInTheDocument()
    })
  })

  test('submits form data with default values for non-internal users', async () => {
    const {addMTEngine, container} = setup()

    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-license'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        secret: 'my-license',
        preimport: true,
        context_analyzer: true,
        pretranslate: false,
      })
    })
  })

  test('submits form data with default values for internal users', async () => {
    global.config.isAnInternalUser = true
    const {addMTEngine, container} = setup()

    fireEvent.change(container.querySelector('input[name="secret"]'), {
      target: {value: 'my-license'},
    })
    fireEvent.click(screen.getByRole('button', {name: /confirm/i}))

    await waitFor(() => {
      expect(addMTEngine).toHaveBeenCalledWith({
        secret: 'my-license',
        preimport: false,
        context_analyzer: false,
        pretranslate: true,
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
