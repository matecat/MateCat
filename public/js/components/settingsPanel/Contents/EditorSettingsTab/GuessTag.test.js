import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {GuessTag} from './GuessTag'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import SegmentActions from '../../../../actions/SegmentActions'

jest.mock('../../../../actions/SegmentActions', () => ({
  changeTagProjectionStatus: jest.fn(),
}))

const sourceLang = {code: 'en-US', name: 'English'}
const targetLangs = [{code: 'it-IT', name: 'Italian'}]

const acceptedLanguages = {'en-it': 'English - Italian'}

const renderComponent = ({
  metadata = {},
  setUserMetadataKey = jest.fn(() => Promise.resolve()),
  sourceLang: source = sourceLang,
  targetLangs: targets = targetLangs,
} = {}) => {
  global.config = {...global.config, tag_projection_languages: acceptedLanguages}
  return render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <GuessTag sourceLang={source} targetLangs={targets} />
    </ApplicationWrapperContext.Provider>,
  )
}

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {}
})

test('renders unchecked by default for a supported language pair', () => {
  renderComponent()
  const toggle = screen.getByTestId('switch-guesstag')
  expect(toggle).not.toBeChecked()
  expect(toggle).not.toBeDisabled()
})

test('renders checked when metadata flag is set', () => {
  renderComponent({metadata: {guess_tags: 1}})
  expect(screen.getByTestId('switch-guesstag')).toBeChecked()
})

test('disables the toggle and lists unsupported languages when the pair is not supported', () => {
  renderComponent({
    metadata: {guess_tags: 1},
    sourceLang: {code: 'af-ZA', name: 'Afrikaans'},
  })

  const toggle = screen.getByTestId('switch-guesstag')
  expect(toggle).toBeDisabled()
  expect(toggle).not.toBeChecked()
  expect(screen.getByText(/Not available for:/)).toBeInTheDocument()
  expect(screen.getByText(/Afrikaans - Italian/)).toBeInTheDocument()
})

test('shows the revise-mode message and starts disabled when config.isReview is set', () => {
  global.config.isReview = true
  renderComponent()

  expect(screen.getByText(/Not available in revise mode\./)).toBeInTheDocument()
  expect(screen.getByTestId('switch-guesstag')).toBeDisabled()
})

test('toggling calls setUserMetadataKey then changeTagProjectionStatus', async () => {
  const setUserMetadataKey = jest.fn(() => Promise.resolve())
  renderComponent({setUserMetadataKey})

  await userEvent.click(screen.getByTestId('switch-guesstag'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('guess_tags', 1)
  expect(SegmentActions.changeTagProjectionStatus).toHaveBeenCalledWith(true)
})
