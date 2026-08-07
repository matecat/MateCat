import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Lexiqa} from './Lexiqa'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import ApplicationStore from '../../../../stores/ApplicationStore'
import LXQ from '../../../../utils/lxq.main'

jest.mock('../../../../stores/ApplicationStore', () => ({
  getLanguageNameFromLocale: jest.fn((code) => code),
}))

jest.mock('../../../../utils/lxq.main', () => ({
  enable: jest.fn(),
  disable: jest.fn(),
}))

const sourceLang = {code: 'en-US', name: 'English'}
const targetLangs = [{code: 'it-IT', name: 'Italian'}]
const acceptedLanguages = ['en-US', 'it-IT']

const renderComponent = ({
  metadata = {},
  setUserMetadataKey = jest.fn(),
  sourceLang: source = sourceLang,
  targetLangs: targets = targetLangs,
  license = 'a-license-key',
} = {}) => {
  global.config = {
    ...global.config,
    lexiqa_languages: acceptedLanguages,
    lxq_license: license,
  }
  return render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <Lexiqa sourceLang={source} targetLangs={targets} />
    </ApplicationWrapperContext.Provider>,
  )
}

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {}
})

test('renders unchecked by default for a supported language pair', () => {
  renderComponent()
  const toggle = screen.getByTestId('switch-lexiqa')
  expect(toggle).not.toBeChecked()
  expect(toggle).not.toBeDisabled()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({metadata: {lexiqa: 1}})
  expect(screen.getByTestId('switch-lexiqa')).toBeChecked()
})

test('shows the license request message when no license is configured', () => {
  renderComponent({license: null})
  expect(screen.getByText(/Request your license key at/)).toBeInTheDocument()
  expect(screen.getByTestId('switch-lexiqa')).toBeDisabled()
})

test('disables the toggle and lists unsupported languages for an unsupported source', () => {
  renderComponent({
    metadata: {lexiqa: 1},
    sourceLang: {code: 'af-ZA', name: 'Afrikaans'},
  })

  const toggle = screen.getByTestId('switch-lexiqa')
  expect(toggle).toBeDisabled()
  expect(toggle).not.toBeChecked()
  expect(screen.getByText(/Not available for:/)).toBeInTheDocument()
  expect(screen.getByText('Afrikaans')).toBeInTheDocument()
})

test('disables the toggle for an unsupported target language', () => {
  renderComponent({
    metadata: {lexiqa: 1},
    targetLangs: [{code: 'ln-LIN', name: 'Lingala'}],
  })

  expect(screen.getByTestId('switch-lexiqa')).toBeDisabled()
  expect(ApplicationStore.getLanguageNameFromLocale).toHaveBeenCalledWith(
    'ln-LIN',
  )
})

test('toggling on enables LXQ and persists metadata', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({setUserMetadataKey})

  await userEvent.click(screen.getByTestId('switch-lexiqa'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('lexiqa', 1)
  expect(LXQ.enable).toHaveBeenCalled()
})

test('toggling off disables LXQ and persists metadata', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({setUserMetadataKey, metadata: {lexiqa: 1}})

  await userEvent.click(screen.getByTestId('switch-lexiqa'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('lexiqa', 0)
  expect(LXQ.disable).toHaveBeenCalled()
})
