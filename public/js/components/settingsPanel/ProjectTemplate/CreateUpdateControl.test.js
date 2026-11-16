import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {CreateUpdateControl} from './CreateUpdateControl'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplateConstants'

const Wrapper = ({
  templateName = 'My Template',
  updateNameBehaviour = {current: {confirm: jest.fn(), cancel: jest.fn()}},
  createTemplate = {current: jest.fn()},
  templateModifier = TEMPLATE_MODIFIERS.UPDATE,
}) => (
  <ProjectTemplateContext.Provider
    value={{
      templateName,
      updateNameBehaviour,
      createTemplate,
      templateModifier,
    }}
  >
    <CreateUpdateControl />
  </ProjectTemplateContext.Provider>
)

test('clicking Confirm calls createTemplate.current when creating', async () => {
  const user = userEvent.setup()
  const create = jest.fn()
  const createTemplate = {current: create}

  render(
    <Wrapper
      templateModifier={TEMPLATE_MODIFIERS.CREATE}
      createTemplate={createTemplate}
    />,
  )

  await user.click(screen.getByTestId('create-update-template'))

  expect(create).toHaveBeenCalledTimes(1)
})

test('clicking Confirm calls updateNameBehaviour.confirm when updating', async () => {
  const user = userEvent.setup()
  const confirm = jest.fn()
  const updateNameBehaviour = {current: {confirm, cancel: jest.fn()}}

  render(
    <Wrapper
      templateModifier={TEMPLATE_MODIFIERS.UPDATE}
      updateNameBehaviour={updateNameBehaviour}
    />,
  )

  await user.click(screen.getByTestId('create-update-template'))

  expect(confirm).toHaveBeenCalledTimes(1)
})

test('Confirm button is disabled when templateName is empty', () => {
  render(<Wrapper templateName="" />)

  expect(screen.getByTestId('create-update-template')).toBeDisabled()
})

test('Confirm button is enabled when templateName is set', () => {
  render(<Wrapper templateName="My Template" />)

  expect(screen.getByTestId('create-update-template')).toBeEnabled()
})

test('clicking the close button calls updateNameBehaviour.cancel', async () => {
  const user = userEvent.setup()
  const cancel = jest.fn()
  const updateNameBehaviour = {current: {confirm: jest.fn(), cancel}}

  render(<Wrapper updateNameBehaviour={updateNameBehaviour} />)

  const [, cancelButton] = screen.getAllByRole('button')
  await user.click(cancelButton)

  expect(cancel).toHaveBeenCalledTimes(1)
})
