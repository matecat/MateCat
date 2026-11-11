import React from 'react'
import {render, screen, within, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {LanguagesExceptions} from './LanguagesExceptions'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

// Isolates LanguagesExceptions' own add/remove/modify logic from Select's
// portal-dropdown internals (covered separately by Select.test.js).
jest.mock('../../../common/Select', () => ({
  Select: ({options, activeOption, onSelect, placeholder}) => (
    <div data-testid="select-mock">
      <span data-testid="select-mock-value">
        {activeOption?.name ?? placeholder}
      </span>
      {options.map((opt) => (
        <button key={opt.id} onClick={() => onSelect(opt)}>
          {opt.name}
        </button>
      ))}
    </div>
  ),
}))

const LANGUAGES = [
  {id: 'en-US', code: 'en-US', name: 'English (US)'},
  {id: 'it-IT', code: 'it-IT', name: 'Italian (IT)'},
]

const TEMPLATE_ID = 'tpl1'

const Wrapper = ({
  breakdowns,
  updateExceptions = jest.fn(),
  languages = LANGUAGES,
  currentTemplateBreakdowns = breakdowns,
}) => (
  <CreateProjectContext.Provider value={{languages}}>
    <SettingsPanelContext.Provider
      value={{
        analysisTemplates: {
          templates: [
            {
              id: TEMPLATE_ID,
              isTemporary: false,
              breakdowns: currentTemplateBreakdowns,
            },
          ],
          currentTemplate: {id: TEMPLATE_ID, breakdowns},
        },
      }}
    >
      <LanguagesExceptions
        breakdowns={breakdowns}
        updateExceptions={updateExceptions}
      />
    </SettingsPanelContext.Provider>
  </CreateProjectContext.Provider>
)

test('renders an existing exception row from breakdowns', () => {
  const breakdowns = {
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 80}},
  }
  render(<Wrapper breakdowns={breakdowns} />)

  const selects = screen.getAllByTestId('select-mock')
  expect(selects).toHaveLength(2)
  expect(within(selects[0]).getByTestId('select-mock-value')).toHaveTextContent(
    'English (US)',
  )
  expect(within(selects[1]).getByTestId('select-mock-value')).toHaveTextContent(
    'Italian (IT)',
  )
})

test('removing an existing exception calls updateExceptions with it deleted', async () => {
  const user = userEvent.setup()
  const updateExceptions = jest.fn()
  const breakdowns = {
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 80}},
  }
  render(<Wrapper breakdowns={breakdowns} updateExceptions={updateExceptions} />)

  // the mocked Select renders a button per language option, so the real
  // close button (rendered last, after both Selects) is the last one
  const rowButtons = within(
    screen.getByTestId('analysis-tab-exceptions-row'),
  ).getAllByRole('button')
  await user.click(rowButtons[rowButtons.length - 1])

  expect(updateExceptions).toHaveBeenCalledWith({default: {MT: 50}})
})

test('adding a new exception calls updateExceptions with the merged shape', async () => {
  const user = userEvent.setup()
  const updateExceptions = jest.fn()
  const breakdowns = {default: {MT: 50}}

  render(<Wrapper breakdowns={breakdowns} updateExceptions={updateExceptions} />)

  await user.click(screen.getByText('Add exception'))

  const [sourceSelect, targetSelect] = screen.getAllByTestId('select-mock')
  await user.click(within(sourceSelect).getByText('English (US)'))
  await user.click(within(targetSelect).getByText('Italian (IT)'))

  const percentInput = screen.getByTestId('analysis-tab-exception-value')
  await user.clear(percentInput)
  await user.type(percentInput, '75')
  fireEvent.blur(percentInput)

  await user.click(screen.getByText('Confirm'))

  expect(updateExceptions).toHaveBeenCalledWith({
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 75}},
  })
})

test('Confirm is disabled until source, target, and a value are all set', async () => {
  const user = userEvent.setup()
  render(<Wrapper breakdowns={{default: {MT: 50}}} />)

  await user.click(screen.getByText('Add exception'))

  expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()

  const [sourceSelect, targetSelect] = screen.getAllByTestId('select-mock')
  await user.click(within(sourceSelect).getByText('English (US)'))
  await user.click(within(targetSelect).getByText('Italian (IT)'))

  expect(screen.getByRole('button', {name: 'Confirm'})).toBeDisabled()
})

test('groups same-language-code regions into an "All variants" option plus the individual regions', async () => {
  const user = userEvent.setup()
  const languages = [
    {id: 'fr-FR', code: 'fr-FR', name: 'French (France)'},
    {id: 'fr-CA', code: 'fr-CA', name: 'French (Canada)'},
  ]
  render(<Wrapper breakdowns={{default: {MT: 50}}} languages={languages} />)

  await user.click(screen.getByText('Add exception'))

  const [sourceSelect] = screen.getAllByTestId('select-mock')
  expect(
    within(sourceSelect).getByText('French (All variants)'),
  ).toBeInTheDocument()
  expect(within(sourceSelect).getByText('French (France)')).toBeInTheDocument()
  expect(
    within(sourceSelect).getByText('French (Canada)'),
  ).toBeInTheDocument()
})

test('highlights an exception as not-saved when it differs from the original template', () => {
  const breakdowns = {
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 80}},
  }
  const savedBreakdowns = {
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 60}},
  }

  render(
    <Wrapper
      breakdowns={breakdowns}
      currentTemplateBreakdowns={savedBreakdowns}
    />,
  )

  expect(screen.getByTestId('analysis-tab-exceptions-row')).toHaveClass(
    'analysis-value-not-saved',
  )
})

test('does not highlight an exception that matches the original saved template', () => {
  const breakdowns = {
    default: {MT: 50},
    'en-US': {'it-IT': {MT: 80}},
  }

  render(
    <Wrapper breakdowns={breakdowns} currentTemplateBreakdowns={breakdowns} />,
  )

  expect(screen.getByTestId('analysis-tab-exceptions-row')).not.toHaveClass(
    'analysis-value-not-saved',
  )
})
