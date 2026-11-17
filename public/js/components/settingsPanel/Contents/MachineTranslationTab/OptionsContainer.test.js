import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {OptionsContainer} from './OptionsContainer'

jest.mock('./MMTOptions', () => ({
  MMTOptions: (props) => (
    <div data-testid="mmt-options">{JSON.stringify(props)}</div>
  ),
}))
jest.mock('./LaraOptions', () => ({
  LaraOptions: (props) => (
    <div data-testid="lara-options">{JSON.stringify(props)}</div>
  ),
}))
jest.mock('./DeepLOptions', () => ({
  DeepLOptions: (props) => (
    <div data-testid="deepl-options">{JSON.stringify(props)}</div>
  ),
}))
jest.mock('./IntentoOptions', () => ({
  IntentoOptions: (props) => (
    <div data-testid="intento-options">{JSON.stringify(props)}</div>
  ),
}))
jest.mock('./BasicOptions', () => ({
  BasicOptions: (props) => (
    <div data-testid="basic-options">{JSON.stringify(props)}</div>
  ),
}))

const renderComponent = (props = {}) =>
  render(
    <OptionsContainer
      activeMTEngineData={{id: 42, engine_type: 'MMT'}}
      {...props}
    />,
  )

beforeEach(() => {
  global.config = {ownerIsMe: true}
})

describe('OptionsContainer', () => {
  test('renders collapsed by default, with no options content', () => {
    renderComponent()

    expect(screen.getByTitle('Glossary options')).not.toHaveClass('rotate')
    expect(screen.queryByTestId('mmt-options')).not.toBeInTheDocument()
  })

  test('renders the toggle button', () => {
    renderComponent()

    expect(
      screen.getByTitle('Glossary options', {selector: 'button'}),
    ).toBeInTheDocument()
    expect(screen.getByText('Options')).toBeInTheDocument()
  })

  test('disables the toggle button when the user does not own the project', () => {
    global.config.ownerIsMe = false
    renderComponent()

    expect(screen.getByTitle('Glossary options')).toBeDisabled()
  })

  test('enables the toggle button when the user owns the project', () => {
    global.config.ownerIsMe = true
    renderComponent()

    expect(screen.getByTitle('Glossary options')).toBeEnabled()
  })

  test('expands and shows the options content on click', () => {
    renderComponent()

    fireEvent.click(screen.getByTitle('Glossary options'))

    expect(screen.getByTitle('Glossary options')).toHaveClass('rotate')
    expect(screen.getByTestId('mmt-options')).toBeInTheDocument()
  })

  test('collapses and hides the options content on a second click', () => {
    renderComponent()
    const button = screen.getByTitle('Glossary options')

    fireEvent.click(button)
    fireEvent.click(button)

    expect(button).not.toHaveClass('rotate')
    expect(screen.queryByTestId('mmt-options')).not.toBeInTheDocument()
  })

  test.each([
    ['MMT', 'mmt-options'],
    ['Lara', 'lara-options'],
    ['DeepL', 'deepl-options'],
    ['Intento', 'intento-options'],
  ])('renders %s options for engine_type %s', (engineType, testId) => {
    renderComponent({activeMTEngineData: {id: 1, engine_type: engineType}})

    fireEvent.click(screen.getByTitle('Glossary options'))

    expect(screen.getByTestId(testId)).toBeInTheDocument()
  })

  test('falls back to BasicOptions for an unrecognized engine_type', () => {
    renderComponent({
      activeMTEngineData: {id: 1, engine_type: 'GoogleTranslate'},
    })

    fireEvent.click(screen.getByTitle('Glossary options'))

    expect(screen.getByTestId('basic-options')).toBeInTheDocument()
  })

  test('falls back to BasicOptions when engine_type is missing', () => {
    renderComponent({activeMTEngineData: {id: 1}})

    fireEvent.click(screen.getByTitle('Glossary options'))

    expect(screen.getByTestId('basic-options')).toBeInTheDocument()
  })

  test('forwards the engine id and isCattoolPage to the options content', () => {
    renderComponent({
      activeMTEngineData: {id: 7, engine_type: 'MMT'},
      isCattoolPage: true,
    })

    fireEvent.click(screen.getByTitle('Glossary options'))

    expect(screen.getByTestId('mmt-options')).toHaveTextContent(
      JSON.stringify({id: 7, isCattoolPage: true}),
    )
  })
})
