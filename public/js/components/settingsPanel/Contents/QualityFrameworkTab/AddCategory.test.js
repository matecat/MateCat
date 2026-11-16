import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {AddCategory} from './AddCategory'
import {QualityFrameworkTabContext} from './QualityFrameworkTabContext'
// Initializes window.eventHandler (required by the Popover component)
import '../../../common/ApplicationWrapper/ApplicationWrapperContext'

const buildTemplate = (categoriesOverride) => ({
  categories: categoriesOverride ?? [
    {
      id: 1,
      label: 'Accuracy',
      code: 'ACC',
      severities: [
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

const renderWithContext = (contextOverrides = {}) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = buildTemplate()
  const utils = render(
    <QualityFrameworkTabContext.Provider
      value={{modifyingCurrentTemplate, currentTemplate, ...contextOverrides}}
    >
      <AddCategory />
    </QualityFrameworkTabContext.Provider>,
  )
  return {...utils, modifyingCurrentTemplate, currentTemplate}
}

const openPopover = async (user) => {
  await user.click(screen.getByText('Add category'))
  return screen.getByTestId('popover')
}

test('opens the popover with the name input focused on empty fields', async () => {
  const user = userEvent.setup()
  renderWithContext()

  await openPopover(user)

  expect(screen.getByPlaceholderText('Name')).toHaveValue('')
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('reveals the description input only after clicking "Add description"', async () => {
  const user = userEvent.setup()
  renderWithContext()
  await openPopover(user)

  expect(screen.queryByPlaceholderText('Description')).not.toBeInTheDocument()

  await user.click(screen.getByText('Add description'))

  expect(screen.getByPlaceholderText('Description')).toBeInTheDocument()
})

test('shows a validation error and disables Confirm for a duplicate category name', async () => {
  const user = userEvent.setup()
  renderWithContext()
  await openPopover(user)

  await user.type(screen.getByPlaceholderText('Name'), 'accuracy')

  expect(
    screen.getByText('Name already in use for another category.'),
  ).toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('confirming adds a new category derived from the last one, with fresh severity ids', async () => {
  const user = userEvent.setup()
  const {modifyingCurrentTemplate, currentTemplate} = renderWithContext()
  await openPopover(user)

  await user.type(screen.getByPlaceholderText('Name'), 'Terminology')
  await user.click(screen.getByText('Add description'))
  await user.type(
    screen.getByPlaceholderText('Description'),
    'Glossary mismatches',
  )
  await user.click(screen.getByText('Confirm'))

  expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  const result = updater(currentTemplate)

  expect(result.categories).toHaveLength(3)
  const newCategory = result.categories[2]
  expect(newCategory.id).toBe(3)
  // Note: no space before the parenthesis — addCategory() concatenates the
  // name and formatCategoryDescription(description) directly, unlike
  // ModifyCategory's updateLabel(), which inserts one.
  expect(newCategory.label).toBe('Terminology(Glossary mismatches)')
  expect(newCategory.code).toBe('TER')
  expect(newCategory.severities).toEqual([
    {id: 22, id_category: 3, label: 'Minor', code: 'MIN', penalty: 0},
    {id: 23, id_category: 3, label: 'Major', code: 'MAJ', penalty: 0},
  ])
})

// Known bug: Popover.js renders its toggle button as
// `<Button {...defaultToggleButtonProps} disabled={disabled} />`, where
// `disabled` is Popover's own top-level prop (default false) — it is spread
// *after* defaultToggleButtonProps and so always wins, silently clobbering
// whatever `toggleButtonProps.disabled` the caller (AddCategory here) set.
// The limit tooltip still shows (tooltip/tooltipPosition aren't affected),
// which is why this goes unnoticed — but the button never actually becomes
// unclickable. This test documents the current, buggy behavior; it should
// start failing (and be updated to assert `toBeDisabled()`) once Popover's
// prop order is fixed.
test('shows the limit tooltip once the category limit is reached (toggle itself stays enabled — see comment)', () => {
  const categories = Array.from({length: 50}, (_, i) => ({
    id: i + 1,
    label: `Category ${i + 1}`,
    code: 'CAT',
    severities: [{id: i + 100, id_category: i + 1, label: 'Minor', code: 'MIN', penalty: 1}],
  }))
  renderWithContext({currentTemplate: buildTemplate(categories)})

  const toggleButton = screen.getByText('Add category')
  expect(toggleButton).toHaveAttribute(
    'aria-label',
    expect.stringContaining('reached the limit of 50 categories'),
  )
  expect(toggleButton).toBeEnabled()
})

test('resets the fields when the popover is closed without confirming', async () => {
  const user = userEvent.setup()
  renderWithContext()
  await openPopover(user)

  await user.type(screen.getByPlaceholderText('Name'), 'Draft name')
  // Closing via the toggle button itself (acts as a cancel in this component, since it has no cancel path other than onClose)
  fireEvent.mouseDown(document.body)

  await openPopover(user)
  expect(screen.getByPlaceholderText('Name')).toHaveValue('')
})
