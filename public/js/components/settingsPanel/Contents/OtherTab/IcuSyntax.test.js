import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {IcuSyntax} from './IcuSyntax'
import {SettingsPanelContext} from '../../SettingsPanelContext'

jest.mock('../../../common/Switch', () => ({
  __esModule: true,
  default: ({active, onChange}) => (
    <button
      data-testid="switch-icu"
      data-active={String(active)}
      onClick={() => onChange(!active)}
    >
      switch
    </button>
  ),
}))

const renderIcuSyntax = (icuEnabled, modifyingCurrentTemplate = jest.fn()) =>
  render(
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate: {icuEnabled},
        modifyingCurrentTemplate,
      }}
    >
      <IcuSyntax />
    </SettingsPanelContext.Provider>,
  )

describe('IcuSyntax', () => {
  test('renders heading and description', () => {
    renderIcuSyntax(false)
    expect(screen.getByText('ICU detection')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Enable or disable ICU syntax detection and ICU-specific QA checks.',
      ),
    ).toBeInTheDocument()
  })

  test('passes active=true to Switch when icuEnabled is true', () => {
    renderIcuSyntax(true)
    expect(screen.getByTestId('switch-icu')).toHaveAttribute(
      'data-active',
      'true',
    )
  })

  test('passes active=false to Switch when icuEnabled is false', () => {
    renderIcuSyntax(false)
    expect(screen.getByTestId('switch-icu')).toHaveAttribute(
      'data-active',
      'false',
    )
  })

  test('toggling the switch calls modifyingCurrentTemplate with icuEnabled updater', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderIcuSyntax(false, modifyingCurrentTemplate)

    fireEvent.click(screen.getByTestId('switch-icu'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(updater({icuEnabled: false, otherField: 'x'})).toEqual({
      icuEnabled: true,
      otherField: 'x',
    })
  })
})
