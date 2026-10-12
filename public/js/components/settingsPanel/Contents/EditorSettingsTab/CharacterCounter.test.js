import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {CharacterCounter} from './CharacterCounter'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const renderComponent = (metadata = {}, setUserMetadataKey = jest.fn()) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <CharacterCounter />
    </ApplicationWrapperContext.Provider>,
  )

test('renders unchecked by default', () => {
  renderComponent()
  expect(screen.getByTestId('switch-chars-counter')).not.toBeChecked()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({character_counter: 1})
  expect(screen.getByTestId('switch-chars-counter')).toBeChecked()
})

test('toggling calls setUserMetadataKey with the new value', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(screen.getByTestId('switch-chars-counter'))

  expect(screen.getByTestId('switch-chars-counter')).toBeChecked()
  expect(setUserMetadataKey).toHaveBeenCalledWith('character_counter', 1)
})
