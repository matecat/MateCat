import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SubTemplateNameInput} from './SubTemplateNameInput'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplateContext'

const renderInput = ({
  templateName = 'name',
  setTemplateName = jest.fn(),
  templateModifier = SUBTEMPLATE_MODIFIERS.CREATE,
  updateNameBehaviour = {current: {confirm: jest.fn(), cancel: jest.fn()}},
  createTemplate = {current: jest.fn()},
} = {}) =>
  render(
    <SubTemplatesContext.Provider
      value={{
        templateName,
        setTemplateName,
        updateNameBehaviour,
        createTemplate,
        templateModifier,
      }}
    >
      <SubTemplateNameInput />
    </SubTemplatesContext.Provider>,
  )

describe('SubTemplateNameInput', () => {
  test('renders the input with the current templateName as its value', () => {
    renderInput({templateName: 'foo'})
    expect(screen.getByTestId('template-name-input')).toHaveValue('foo')
  })

  test('calls setTemplateName when typing', () => {
    const setTemplateName = jest.fn()
    renderInput({setTemplateName})

    fireEvent.change(screen.getByTestId('template-name-input'), {
      target: {value: 'new name'},
    })

    expect(setTemplateName).toHaveBeenCalledWith('new name')
  })

  test('pressing Enter in create mode calls createTemplate', () => {
    const createTemplate = {current: jest.fn()}
    renderInput({templateModifier: SUBTEMPLATE_MODIFIERS.CREATE, createTemplate})

    fireEvent.keyDown(screen.getByTestId('template-name-input'), {
      key: 'Enter',
    })

    expect(createTemplate.current).toHaveBeenCalledTimes(1)
  })

  test('pressing Enter in update mode calls updateNameBehaviour.confirm', () => {
    const updateNameBehaviour = {
      current: {confirm: jest.fn(), cancel: jest.fn()},
    }
    renderInput({
      templateModifier: SUBTEMPLATE_MODIFIERS.UPDATE,
      updateNameBehaviour,
    })

    fireEvent.keyDown(screen.getByTestId('template-name-input'), {
      key: 'Enter',
    })

    expect(updateNameBehaviour.current.confirm).toHaveBeenCalledTimes(1)
  })

  test('pressing Escape calls updateNameBehaviour.cancel', () => {
    const updateNameBehaviour = {
      current: {confirm: jest.fn(), cancel: jest.fn()},
    }
    renderInput({updateNameBehaviour})

    fireEvent.keyDown(screen.getByTestId('template-name-input'), {
      key: 'Escape',
    })

    expect(updateNameBehaviour.current.cancel).toHaveBeenCalledTimes(1)
  })

  test('does nothing on other key presses', () => {
    const createTemplate = {current: jest.fn()}
    const updateNameBehaviour = {
      current: {confirm: jest.fn(), cancel: jest.fn()},
    }
    renderInput({createTemplate, updateNameBehaviour})

    fireEvent.keyDown(screen.getByTestId('template-name-input'), {
      key: 'a',
    })

    expect(createTemplate.current).not.toHaveBeenCalled()
    expect(updateNameBehaviour.current.confirm).not.toHaveBeenCalled()
    expect(updateNameBehaviour.current.cancel).not.toHaveBeenCalled()
  })

  test('does not attach the keydown listener when templateName is empty', () => {
    const createTemplate = {current: jest.fn()}
    renderInput({templateName: '', createTemplate})

    fireEvent.keyDown(screen.getByTestId('template-name-input'), {
      key: 'Enter',
    })

    expect(createTemplate.current).not.toHaveBeenCalled()
  })

  test('removes the keydown listener on unmount', () => {
    const {unmount} = renderInput()
    const input = screen.getByTestId('template-name-input')
    const removeSpy = jest.spyOn(input, 'removeEventListener')

    unmount()

    expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function))
  })
})
