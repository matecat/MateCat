import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {AddSeverity} from './AddSeverity'
import {QualityFrameworkTabContext} from './QualityFrameworkTabContext'
// Initializes window.eventHandler (required by the Popover component)
import '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const buildTemplate = (firstCategorySeverities) => ({
  categories: [
    {
      id: 1,
      label: 'Accuracy',
      code: 'ACC',
      severities: firstCategorySeverities ?? [
        {id: 10, id_category: 1, label: 'Minor', code: 'MIN', penalty: 1},
        {id: 11, id_category: 1, label: 'Major', code: 'MAJ', penalty: 5},
      ],
    },
    {
      id: 2,
      label: 'Fluency',
      code: 'FLU',
      severities: [
        {id: 20, id_category: 2, label: 'Minor', code: 'MIN', penalty: 1},
        {id: 21, id_category: 2, label: 'Major', code: 'MAJ', penalty: 5},
      ],
    },
  ],
})

const renderWithContext = ({
  numbersOfColumns = 2,
  currentTemplate = buildTemplate(),
  modifyingCurrentTemplate = jest.fn(),
} = {}) => {
  render(
    <QualityFrameworkTabContext.Provider
      value={{modifyingCurrentTemplate, currentTemplate}}
    >
      <AddSeverity numbersOfColumns={numbersOfColumns} />
    </QualityFrameworkTabContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate}
}

const openPopover = async (user) => {
  await user.click(screen.getByTestId('add-severity-button'))
  return screen.getByTestId('popover')
}

test('opens the popover with the name input empty and Confirm disabled', async () => {
  const user = userEvent.setup()
  renderWithContext()
  await openPopover(user)

  expect(screen.getByPlaceholderText('Name')).toHaveValue('')
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('shows a validation error for a severity name already used in the first category', async () => {
  const user = userEvent.setup()
  renderWithContext()
  await openPopover(user)

  await user.type(screen.getByPlaceholderText('Name'), 'minor')

  expect(
    screen.getByText('Name already in use for another severity.'),
  ).toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('confirming appends a new severity column to every category, sharing one id sequence', async () => {
  const user = userEvent.setup()
  const {modifyingCurrentTemplate, currentTemplate} = renderWithContext({
    numbersOfColumns: 2,
  })
  await openPopover(user)

  await user.type(screen.getByPlaceholderText('Name'), 'Terminology')
  await user.click(screen.getByText('Confirm'))

  expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  const result = updater(currentTemplate)

  expect(result.categories[0].severities).toEqual([
    ...currentTemplate.categories[0].severities,
    {id: 22, id_category: 1, sort: 3, label: 'Terminology', code: 'TER', penalty: 0},
  ])
  expect(result.categories[1].severities).toEqual([
    ...currentTemplate.categories[1].severities,
    {id: 23, id_category: 2, sort: 3, label: 'Terminology', code: 'TER', penalty: 0},
  ])
})

// Known bug: Popover.js renders its toggle button as
// `<Button {...defaultToggleButtonProps} disabled={disabled} />`, where
// `disabled` is Popover's own top-level prop (default false) — spread
// *after* defaultToggleButtonProps, so it always wins and silently clobbers
// whatever `toggleButtonProps.disabled` the caller (AddSeverity here) set.
// The limit tooltip still shows, which is why this goes unnoticed, but the
// button never actually becomes unclickable. See AddCategory.test.js for
// the same finding.
test('shows the limit tooltip once the severity limit is reached (toggle itself stays enabled — see comment)', () => {
  const severities = Array.from({length: 50}, (_, i) => ({
    id: i + 1,
    id_category: 1,
    label: `Severity ${i + 1}`,
    code: 'SEV',
    penalty: 1,
  }))
  renderWithContext({currentTemplate: buildTemplate(severities)})

  const toggleButton = screen.getByTestId('add-severity-button')
  expect(toggleButton).toHaveAttribute(
    'aria-label',
    expect.stringContaining('reached the limit of 50 severities'),
  )
  expect(toggleButton).toBeEnabled()
})
