import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {ModifySeverity} from './ModifySeverity'
import {QualityFrameworkTabContext} from './QualityFrameworkTabContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'

// validateLabel() always reads currentTemplate.categories[0].severities,
// regardless of which category the edited severity column actually belongs
// to (columns are shared by position across every category), so the first
// category's severities are what matters here.
const buildTemplate = () => ({
  categories: [
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

const renderWithContext = ({
  label = 'Minor',
  code = 'MIN',
  index = 0,
  sort = 0,
  currentTemplate = buildTemplate(),
  modifyingCurrentTemplate = jest.fn(),
  setIsEditingName = jest.fn(),
} = {}) => {
  const target = document.createElement('div')
  render(
    <SettingsPanelContext.Provider value={{portalTarget: document.body}}>
      <QualityFrameworkTabContext.Provider
        value={{modifyingCurrentTemplate, currentTemplate}}
      >
        <ModifySeverity
          target={target}
          label={label}
          code={code}
          index={index}
          sort={sort}
          setIsEditingName={setIsEditingName}
        />
      </QualityFrameworkTabContext.Provider>
    </SettingsPanelContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, setIsEditingName}
}

test('pre-fills the name input with the current label', () => {
  renderWithContext({label: 'Minor'})

  expect(screen.getByPlaceholderText('Name')).toHaveValue('Minor')
})

test('cancel closes the editor without touching the template', async () => {
  const user = userEvent.setup()
  const {modifyingCurrentTemplate, setIsEditingName} = renderWithContext()

  await user.click(screen.getByText('Cancel'))

  expect(setIsEditingName).toHaveBeenCalledWith(false)
  expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
})

test('a mousedown outside the editor closes it, same as Cancel', () => {
  const {setIsEditingName} = renderWithContext()

  fireEvent.mouseDown(document.body)

  expect(setIsEditingName).toHaveBeenCalledWith(false)
})

test('shows a validation error for a name already used by another severity column', async () => {
  const user = userEvent.setup()
  // index: 0 is "Minor" itself, so the only other column at index 1 is "Major"
  renderWithContext({label: 'Minor', code: 'MIN', index: 0})

  const nameInput = screen.getByPlaceholderText('Name')
  await user.clear(nameInput)
  await user.type(nameInput, 'major')

  expect(
    screen.getByText('Name already in use for another severity.'),
  ).toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('confirming renames the matching severity in every category, leaving others untouched', async () => {
  const user = userEvent.setup()
  const {modifyingCurrentTemplate, currentTemplate, setIsEditingName} =
    renderWithContext({label: 'Minor', code: 'MIN', index: 0})

  const nameInput = screen.getByPlaceholderText('Name')
  await user.clear(nameInput)
  await user.type(nameInput, 'Slight')

  await user.click(screen.getByText('Confirm'))

  expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  const result = updater(currentTemplate)

  expect(result.categories[0].severities[0]).toMatchObject({
    id: 10,
    label: 'Slight',
    code: 'SLI',
  })
  expect(result.categories[0].severities[1]).toEqual(
    currentTemplate.categories[0].severities[1],
  )
  expect(result.categories[1].severities[0]).toMatchObject({
    id: 20,
    label: 'Slight',
    code: 'SLI',
  })
  expect(result.categories[1].severities[1]).toEqual(
    currentTemplate.categories[1].severities[1],
  )
  expect(setIsEditingName).toHaveBeenCalledWith(false)
})
