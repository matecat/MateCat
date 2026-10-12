import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {TmPrioritization} from './TmPrioritization'

const WrapperComponent = (contextProps) => (
  <SettingsPanelContext.Provider value={contextProps}>
    <TmPrioritization />
  </SettingsPanelContext.Provider>
)

beforeEach(() => {
  global.config = {
    ...config,
    ownerIsMe: true,
  }
})

test('renders active state and toggles it off on change', async () => {
  const user = userEvent.setup()
  const modifyingCurrentTemplate = jest.fn()

  render(
    <WrapperComponent
      currentProjectTemplate={{tmPrioritization: true}}
      modifyingCurrentTemplate={modifyingCurrentTemplate}
    />,
  )

  const checkbox = screen.getByRole('checkbox')
  expect(checkbox).toBeChecked()

  await user.click(checkbox)

  expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  expect(updater({tmPrioritization: true, other: 'value'})).toEqual({
    tmPrioritization: false,
    other: 'value',
  })
})

test('renders inactive/unchecked state', () => {
  render(
    <WrapperComponent
      currentProjectTemplate={{tmPrioritization: false}}
      modifyingCurrentTemplate={jest.fn()}
    />,
  )

  expect(screen.getByRole('checkbox')).not.toBeChecked()
})

test('disables the switch when the current user is not the owner', () => {
  global.config = {...config, ownerIsMe: false}

  render(
    <WrapperComponent
      currentProjectTemplate={{tmPrioritization: false}}
      modifyingCurrentTemplate={jest.fn()}
    />,
  )

  expect(screen.getByRole('checkbox')).toBeDisabled()
})

test('renders the informational copy and link', () => {
  render(
    <WrapperComponent
      currentProjectTemplate={{tmPrioritization: false}}
      modifyingCurrentTemplate={jest.fn()}
    />,
  )

  expect(screen.getByText('TM prioritization')).toBeInTheDocument()
  const link = screen.getByText('More details')
  expect(link).toHaveAttribute('href', 'https://guides.matecat.com/activ')
})
