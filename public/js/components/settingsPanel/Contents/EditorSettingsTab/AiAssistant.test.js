import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {AiAssistant} from './AiAssistant'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import UserStore from '../../../../stores/UserStore'

jest.mock('../../../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('../../../../stores/UserStore', () => ({
  getUser: jest.fn(() => ({user: {uid: 42}})),
}))

const renderComponent = (metadata = {}) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{
        userInfo: {metadata},
        setUserMetadataKey: jest.fn(),
      }}
    >
      <AiAssistant />
    </ApplicationWrapperContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
})

test('renders unchecked by default', () => {
  renderComponent()
  expect(screen.getByTestId('switch-ai-assistant')).not.toBeChecked()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({ai_assistant: 1})
  expect(screen.getByTestId('switch-ai-assistant')).toBeChecked()
})

test('toggling calls setUserMetadataKey and tracks the event', async () => {
  const setUserMetadataKey = jest.fn()
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata: {}}, setUserMetadataKey}}
    >
      <AiAssistant />
    </ApplicationWrapperContext.Provider>,
  )

  await userEvent.click(screen.getByTestId('switch-ai-assistant'))

  expect(screen.getByTestId('switch-ai-assistant')).toBeChecked()
  expect(setUserMetadataKey).toHaveBeenCalledWith('ai_assistant', 1)
  expect(UserStore.getUser).toHaveBeenCalled()
  expect(CommonUtils.dispatchTrackingEvents).toHaveBeenCalledWith(
    'AiAssistantSwitch',
    expect.objectContaining({user: 42, onHighlight: true}),
  )
})
