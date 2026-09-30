import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {TemplateNameInput} from './TemplateNameInput'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplateConstants'

const Wrapper = ({
  templateName = 'My Template',
  setTemplateName = jest.fn(),
  updateNameBehaviour = {current: {confirm: jest.fn(), cancel: jest.fn()}},
  createTemplate = {current: jest.fn()},
  templateModifier = TEMPLATE_MODIFIERS.UPDATE,
}) => (
  <ProjectTemplateContext.Provider
    value={{
      templateName,
      setTemplateName,
      updateNameBehaviour,
      createTemplate,
      templateModifier,
    }}
  >
    <TemplateNameInput />
  </ProjectTemplateContext.Provider>
)

test('typing updates the template name', async () => {
  const user = userEvent.setup()
  const setTemplateName = jest.fn()
  render(<Wrapper setTemplateName={setTemplateName} />)

  const input = screen.getByTestId('template-name-input')
  await act(async () => user.type(input, 'X'))

  expect(setTemplateName).toHaveBeenCalledWith('My TemplateX')
})

test('pressing Enter while updating calls updateNameBehaviour.confirm', () => {
  const confirm = jest.fn()
  const updateNameBehaviour = {current: {confirm, cancel: jest.fn()}}

  render(
    <Wrapper
      templateModifier={TEMPLATE_MODIFIERS.UPDATE}
      updateNameBehaviour={updateNameBehaviour}
    />,
  )

  const input = screen.getByTestId('template-name-input')
  act(() => {
    input.dispatchEvent(
      new KeyboardEvent('keydown', {key: 'Enter', bubbles: true}),
    )
  })

  expect(confirm).toHaveBeenCalled()
})

test('pressing Enter while creating calls createTemplate.current', () => {
  const create = jest.fn()
  const createTemplate = {current: create}

  render(
    <Wrapper
      templateModifier={TEMPLATE_MODIFIERS.CREATE}
      createTemplate={createTemplate}
    />,
  )

  const input = screen.getByTestId('template-name-input')
  act(() => {
    input.dispatchEvent(
      new KeyboardEvent('keydown', {key: 'Enter', bubbles: true}),
    )
  })

  expect(create).toHaveBeenCalled()
})

test('pressing Escape calls updateNameBehaviour.cancel', () => {
  const cancel = jest.fn()
  const updateNameBehaviour = {current: {confirm: jest.fn(), cancel}}

  render(<Wrapper updateNameBehaviour={updateNameBehaviour} />)

  const input = screen.getByTestId('template-name-input')
  act(() => {
    input.dispatchEvent(
      new KeyboardEvent('keydown', {key: 'Escape', bubbles: true, cancelable: true}),
    )
  })

  expect(cancel).toHaveBeenCalled()
})

test('does not attach the keydown handler when templateName is empty', () => {
  const confirm = jest.fn()
  const updateNameBehaviour = {current: {confirm, cancel: jest.fn()}}

  render(<Wrapper templateName="" updateNameBehaviour={updateNameBehaviour} />)

  const input = screen.getByTestId('template-name-input')
  act(() => {
    input.dispatchEvent(
      new KeyboardEvent('keydown', {key: 'Enter', bubbles: true}),
    )
  })

  expect(confirm).not.toHaveBeenCalled()
})
