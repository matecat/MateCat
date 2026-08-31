import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SubTemplateCreateUpdateControl} from './SubTemplateCreateUpdateControl'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplateContext'

const renderControl = ({
  templateName = 'name',
  templateModifier = SUBTEMPLATE_MODIFIERS.CREATE,
  updateNameBehaviour = {current: {confirm: jest.fn(), cancel: jest.fn()}},
  createTemplate = {current: jest.fn()},
} = {}) =>
  render(
    <SubTemplatesContext.Provider
      value={{
        templateName,
        templateModifier,
        updateNameBehaviour,
        createTemplate,
      }}
    >
      <SubTemplateCreateUpdateControl />
    </SubTemplatesContext.Provider>,
  )

describe('SubTemplateCreateUpdateControl', () => {
  test('calls createTemplate when in create mode and confirm is clicked', () => {
    const createTemplate = {current: jest.fn()}
    renderControl({
      templateModifier: SUBTEMPLATE_MODIFIERS.CREATE,
      createTemplate,
    })

    fireEvent.click(screen.getByTestId('create-update-template'))

    expect(createTemplate.current).toHaveBeenCalledTimes(1)
  })

  test('calls updateNameBehaviour.confirm when in update mode and confirm is clicked', () => {
    const updateNameBehaviour = {
      current: {confirm: jest.fn(), cancel: jest.fn()},
    }
    renderControl({
      templateModifier: SUBTEMPLATE_MODIFIERS.UPDATE,
      updateNameBehaviour,
    })

    fireEvent.click(screen.getByTestId('create-update-template'))

    expect(updateNameBehaviour.current.confirm).toHaveBeenCalledTimes(1)
  })

  test('disables the confirm button when templateName is empty', () => {
    renderControl({templateName: ''})
    expect(screen.getByTestId('create-update-template')).toBeDisabled()
  })

  test('enables the confirm button when templateName is not empty', () => {
    renderControl({templateName: 'foo'})
    expect(screen.getByTestId('create-update-template')).not.toBeDisabled()
  })

  test('calls updateNameBehaviour.cancel when the cancel button is clicked', () => {
    const updateNameBehaviour = {
      current: {confirm: jest.fn(), cancel: jest.fn()},
    }
    const {container} = renderControl({updateNameBehaviour})

    const buttons = container.querySelectorAll('button')
    fireEvent.click(buttons[buttons.length - 1])

    expect(updateNameBehaviour.current.cancel).toHaveBeenCalledTimes(1)
  })
})
