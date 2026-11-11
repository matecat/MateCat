import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {ModifyCategory} from './ModifyCategory'
import {QualityFrameworkTabContext} from './QualityFrameworkTabContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'

const buildTemplate = () => ({
  categories: [
    {id: 1, label: 'Accuracy (Grammar errors)', code: 'ACC', severities: []},
    {id: 2, label: 'Fluency', code: 'FLU', severities: []},
  ],
})

const renderWithContext = ({
  category = {id: 1, label: 'Accuracy (Grammar errors)', code: 'ACC'},
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
        <ModifyCategory
          target={target}
          category={category}
          setIsEditingName={setIsEditingName}
        />
      </QualityFrameworkTabContext.Provider>
    </SettingsPanelContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, setIsEditingName, target}
}

test('pre-fills the name and description from the category label', () => {
  renderWithContext()

  expect(screen.getByPlaceholderText('Name')).toHaveValue('Accuracy')
  expect(screen.getByPlaceholderText('Description')).toHaveValue(
    'Grammar errors',
  )
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

test('shows a validation error for a name already used by another category', async () => {
  const user = userEvent.setup()
  renderWithContext()

  const nameInput = screen.getByPlaceholderText('Name')
  await user.clear(nameInput)
  await user.type(nameInput, 'Fluency')

  expect(
    screen.getByText('Name already in use for another category.'),
  ).toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('confirming updates only the matching category, recombining name and description with a space', async () => {
  const user = userEvent.setup()
  const {modifyingCurrentTemplate, currentTemplate, setIsEditingName} =
    renderWithContext()

  const nameInput = screen.getByPlaceholderText('Name')
  await user.clear(nameInput)
  await user.type(nameInput, 'Precision')

  await user.click(screen.getByText('Confirm'))

  expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  const result = updater(currentTemplate)

  expect(result.categories[0]).toMatchObject({
    id: 1,
    label: 'Precision (Grammar errors)',
    code: 'PRE',
  })
  expect(result.categories[1]).toEqual(currentTemplate.categories[1])
  expect(setIsEditingName).toHaveBeenCalledWith(false)
})
