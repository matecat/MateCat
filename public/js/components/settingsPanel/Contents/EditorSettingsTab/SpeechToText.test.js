import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SpeechToText} from './SpeechToText'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import ModalsActions from '../../../../actions/ModalsActions'
import Speech2TextFeature from '../../../../utils/speech2text'

jest.mock('../../../../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('../../../../utils/speech2text', () => ({
  enable: jest.fn(),
  disable: jest.fn(),
  init: jest.fn(),
  loadRecognition: jest.fn(),
}))

jest.mock('../../../modals/AlertModal', () => 'AlertModal')

const renderComponent = (metadata = {}, setUserMetadataKey = jest.fn()) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <SpeechToText />
    </ApplicationWrapperContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
})

test('renders unchecked by default', () => {
  renderComponent()
  expect(screen.getByTestId('switch-speechtotext')).not.toBeChecked()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({dictation: 1})
  expect(screen.getByTestId('switch-speechtotext')).toBeChecked()
})

test('is disabled when webkitSpeechRecognition is unavailable and shows a modal on click', async () => {
  renderComponent()

  const toggle = screen.getByTestId('switch-speechtotext')
  expect(toggle).toBeDisabled()

  fireEvent.click(toggle.closest('div'))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.anything(),
    expect.objectContaining({
      text: 'This options is only available on your browser.',
    }),
    'Option not available',
  )
})

test('enables speech2text feature when toggled on (browser supported)', async () => {
  window.webkitSpeechRecognition = function () {}
  const setUserMetadataKey = jest.fn(() => Promise.resolve())
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(screen.getByTestId('switch-speechtotext'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('dictation', 1)
  await Promise.resolve()
  expect(Speech2TextFeature.enable).toHaveBeenCalled()
  expect(Speech2TextFeature.init).toHaveBeenCalled()
  expect(Speech2TextFeature.loadRecognition).toHaveBeenCalled()

  delete window.webkitSpeechRecognition
})

test('disables speech2text feature when toggled off', async () => {
  window.webkitSpeechRecognition = function () {}
  const setUserMetadataKey = jest.fn(() => Promise.resolve())
  renderComponent({dictation: 1}, setUserMetadataKey)

  await userEvent.click(screen.getByTestId('switch-speechtotext'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('dictation', 0)
  await Promise.resolve()
  expect(Speech2TextFeature.disable).toHaveBeenCalled()

  delete window.webkitSpeechRecognition
})
