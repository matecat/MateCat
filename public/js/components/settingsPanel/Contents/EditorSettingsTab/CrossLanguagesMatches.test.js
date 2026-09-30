import React from 'react'
import {render, screen, within} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {CrossLanguagesMatches} from './CrossLanguagesMatches'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import ApplicationStore from '../../../../stores/ApplicationStore'
import SegmentActions from '../../../../actions/SegmentActions'

const mockLanguages = [
  {code: 'fr-FR', name: 'French'},
  {code: 'el-GR', name: 'Greek'},
  {code: 'it-IT', name: 'Italian'},
]

jest.mock('../../../../stores/ApplicationStore', () => ({
  getLanguages: jest.fn(),
}))

jest.mock('../../../../actions/SegmentActions', () => ({
  getContribution: jest.fn(),
  getContributions: jest.fn(),
  modifyTabVisibility: jest.fn(),
  updateAllSegments: jest.fn(),
}))

jest.mock('../../../../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(() => 'segment-1'),
}))

// Simplified stand-in for the real dropdown: renders one button per option so
// tests can drive selection without the real widget's open/close mechanics.
jest.mock('../../../common/Select', () => ({
  Select: ({
    title,
    name,
    id,
    label,
    options,
    activeOption,
    onSelect,
    showResetButton,
    resetFunction,
    children,
  }) => (
    <div data-testid={`select-${title}`} data-name={name} data-id={id}>
      {label && <span data-testid={`label-${title}`}>{label}</span>}
      <span data-testid={`active-${title}`}>{activeOption?.name ?? ''}</span>
      {showResetButton && activeOption && (
        <button data-testid={`reset-${title}`} onClick={() => resetFunction()}>
          reset
        </button>
      )}
      {options.map((option) => (
        <button
          key={option.id}
          data-testid={`option-${title}-${option.id}`}
          onClick={() => onSelect(option)}
        >
          {option.name}
          {children?.(option)?.row}
        </button>
      ))}
    </div>
  ),
}))

const renderComponent = (metadata = {}, setUserMetadataKey = jest.fn()) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {metadata}, setUserMetadataKey}}
    >
      <CrossLanguagesMatches />
    </ApplicationWrapperContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
  ApplicationStore.getLanguages.mockReturnValue(mockLanguages)
})

test('renders the description and both language selects', () => {
  renderComponent()

  expect(screen.getByText('Reference languages')).toBeInTheDocument()
  const wrapper = within(screen.getByTestId('container-crosslanguagesmatches'))
  expect(
    wrapper.getByTestId('select-Primary language suggestion'),
  ).toBeInTheDocument()
  expect(
    wrapper.getByTestId('select-Secondary language suggestion'),
  ).toBeInTheDocument()
})

test('gives each select a distinct name/id, avoiding the duplicate multi-match-1 regression', () => {
  renderComponent()

  expect(
    screen.getByTestId('select-Primary language suggestion'),
  ).toHaveAttribute('data-name', 'multi-match-1')
  expect(
    screen.getByTestId('select-Primary language suggestion'),
  ).toHaveAttribute('data-id', 'multi-match-1')
  expect(
    screen.getByTestId('select-Secondary language suggestion'),
  ).toHaveAttribute('data-name', 'multi-match-2')
  expect(
    screen.getByTestId('select-Secondary language suggestion'),
  ).toHaveAttribute('data-id', 'multi-match-2')
})

test('initializes active options from stored metadata', () => {
  renderComponent({
    cross_language_matches: {primary: 'fr-FR', secondary: 'el-GR'},
  })

  expect(
    screen.getByTestId('active-Primary language suggestion'),
  ).toHaveTextContent('French')
  expect(
    screen.getByTestId('active-Secondary language suggestion'),
  ).toHaveTextContent('Greek')
})

test('selecting a primary language persists metadata and enables the multi-matches tab', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(
    screen.getByTestId('option-Primary language suggestion-fr-FR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {
    primary: 'fr-FR',
    secondary: undefined,
  })
  expect(SegmentActions.modifyTabVisibility).toHaveBeenCalledWith(
    'multiMatches',
    true,
  )
  expect(SegmentActions.getContributions).toHaveBeenCalledWith(
    'segment-1',
    {primary: 'fr-FR', secondary: undefined},
    true,
  )
})

test('selecting a secondary language with no primary set persists metadata and enables the tab', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(
    screen.getByTestId('option-Secondary language suggestion-el-GR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {
    primary: undefined,
    secondary: 'el-GR',
  })
  expect(SegmentActions.modifyTabVisibility).toHaveBeenCalledWith(
    'multiMatches',
    true,
  )
  expect(SegmentActions.getContributions).toHaveBeenCalledWith(
    'segment-1',
    {primary: undefined, secondary: 'el-GR'},
    true,
  )
})

test('deselecting the primary language keeps the secondary selection', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent(
    {cross_language_matches: {primary: 'fr-FR', secondary: 'el-GR'}},
    setUserMetadataKey,
  )

  await userEvent.click(
    screen.getByTestId('option-Primary language suggestion-fr-FR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {
    primary: undefined,
    secondary: 'el-GR',
  })
  expect(SegmentActions.modifyTabVisibility).toHaveBeenCalledWith(
    'multiMatches',
    true,
  )
})

test('clearing both languages disables the multi-matches tab', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent(
    {cross_language_matches: {secondary: 'el-GR'}},
    setUserMetadataKey,
  )

  await userEvent.click(
    screen.getByTestId('option-Secondary language suggestion-el-GR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {})
  expect(SegmentActions.modifyTabVisibility).toHaveBeenCalledWith(
    'multiMatches',
    false,
  )
  expect(SegmentActions.updateAllSegments).toHaveBeenCalled()
})

test('selecting a secondary language updates metadata with both languages', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent(
    {cross_language_matches: {primary: 'fr-FR'}},
    setUserMetadataKey,
  )

  await userEvent.click(
    screen.getByTestId('option-Secondary language suggestion-el-GR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {
    primary: 'fr-FR',
    secondary: 'el-GR',
  })
})

test('clicking the reset button clears just that language, keeping the other', async () => {
  const setUserMetadataKey = jest.fn()
  renderComponent(
    {cross_language_matches: {primary: 'fr-FR', secondary: 'el-GR'}},
    setUserMetadataKey,
  )

  await userEvent.click(screen.getByTestId('reset-Primary language suggestion'))

  expect(setUserMetadataKey).toHaveBeenCalledWith('cross_language_matches', {
    primary: undefined,
    secondary: 'el-GR',
  })
})

test('does not touch SegmentActions when getContribution is unavailable', async () => {
  SegmentActions.getContribution = undefined
  const setUserMetadataKey = jest.fn()
  renderComponent({}, setUserMetadataKey)

  await userEvent.click(
    screen.getByTestId('option-Primary language suggestion-fr-FR'),
  )

  expect(setUserMetadataKey).toHaveBeenCalled()
  expect(SegmentActions.modifyTabVisibility).not.toHaveBeenCalled()
  expect(SegmentActions.getContributions).not.toHaveBeenCalled()

  SegmentActions.getContribution = jest.fn()
})
