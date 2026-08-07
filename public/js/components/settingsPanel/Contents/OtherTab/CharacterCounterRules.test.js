import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {CharacterCounterRules} from './CharacterCounterRules'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CHARS_SIZE_COUNTER_TYPES} from '../../../../utils/charsSizeCounterUtil'

jest.mock('../../../common/Switch', () => ({
  __esModule: true,
  default: ({active, onChange, disabled}) => (
    <button
      data-testid="switch-chars-counter"
      data-active={String(active)}
      data-disabled={String(!!disabled)}
      onClick={() => onChange(!active)}
    >
      switch
    </button>
  ),
}))

jest.mock('../../../common/Select', () => ({
  Select: ({onSelect, activeOption, options, isDisabled}) => (
    <div>
      <div data-testid="active-option">{activeOption?.id ?? ''}</div>
      <div data-testid="select-is-disabled">{String(isDisabled)}</div>
      {options?.map((opt) => (
        <button
          key={opt.id}
          data-testid={`option-${opt.id}`}
          onClick={() => onSelect(opt)}
        >
          {opt.id}
        </button>
      ))}
    </div>
  ),
}))

const renderCharacterCounterRules = (
  currentProjectTemplate,
  modifyingCurrentTemplate = jest.fn(),
) =>
  render(
    <SettingsPanelContext.Provider
      value={{currentProjectTemplate, modifyingCurrentTemplate}}
    >
      <CharacterCounterRules />
    </SettingsPanelContext.Provider>,
  )

describe('CharacterCounterRules', () => {
  beforeEach(() => {
    global.config.is_cattool = false
    global.config.ownerIsMe = true
  })

  test('renders headings and descriptions', () => {
    renderCharacterCounterRules({})
    expect(screen.getByText('Character counter rule')).toBeInTheDocument()
    expect(screen.getByText('Count characters in tags')).toBeInTheDocument()
  })

  test('defaults counter rule to GOOGLE_ADS when characterCounterMode is unset', () => {
    renderCharacterCounterRules({})
    expect(screen.getByTestId('active-option').textContent).toBe(
      CHARS_SIZE_COUNTER_TYPES.GOOGLE_ADS,
    )
  })

  test('shows the configured characterCounterMode as active option', () => {
    renderCharacterCounterRules({
      characterCounterMode: CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK,
    })
    expect(screen.getByTestId('active-option').textContent).toBe(
      CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK,
    )
  })

  test('selecting a counter rule calls modifyingCurrentTemplate with characterCounterMode updater', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderCharacterCounterRules({}, modifyingCurrentTemplate)

    fireEvent.click(
      screen.getByTestId(`option-${CHARS_SIZE_COUNTER_TYPES.ALL_ONE}`),
    )

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(updater({characterCounterMode: 'x'}).characterCounterMode).toBe(
      CHARS_SIZE_COUNTER_TYPES.ALL_ONE,
    )
  })

  test('passes characterCounterCountTags as Switch active state', () => {
    renderCharacterCounterRules({characterCounterCountTags: true})
    expect(screen.getByTestId('switch-chars-counter')).toHaveAttribute(
      'data-active',
      'true',
    )
  })

  test('toggling the switch calls modifyingCurrentTemplate with characterCounterCountTags updater', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderCharacterCounterRules(
      {characterCounterCountTags: false},
      modifyingCurrentTemplate,
    )

    fireEvent.click(screen.getByTestId('switch-chars-counter'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(
      updater({characterCounterCountTags: false}).characterCounterCountTags,
    ).toBe(true)
  })

  test('components are enabled when not in cattool', () => {
    global.config.is_cattool = false
    renderCharacterCounterRules({})
    expect(screen.getByTestId('select-is-disabled').textContent).toBe('false')
    expect(screen.getByTestId('switch-chars-counter')).toHaveAttribute(
      'data-disabled',
      'false',
    )
  })

  test('components are disabled in cattool when the current user is not the owner', () => {
    global.config.is_cattool = true
    global.config.ownerIsMe = false
    renderCharacterCounterRules({})
    expect(screen.getByTestId('select-is-disabled').textContent).toBe('true')
    expect(screen.getByTestId('switch-chars-counter')).toHaveAttribute(
      'data-disabled',
      'true',
    )
  })

  test('components are enabled in cattool when the current user is the owner', () => {
    global.config.is_cattool = true
    global.config.ownerIsMe = true
    renderCharacterCounterRules({})
    expect(screen.getByTestId('select-is-disabled').textContent).toBe('false')
    expect(screen.getByTestId('switch-chars-counter')).toHaveAttribute(
      'data-disabled',
      'false',
    )
  })
})
