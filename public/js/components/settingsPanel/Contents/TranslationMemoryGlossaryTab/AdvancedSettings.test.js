import React from 'react'
import {act, render, screen, within} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {AdvancedSettings} from './AdvancedSettings'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

const renderAdvancedSettings = ({
  pretranslate = {
    match_101: {enabled: true, status: 'APPROVED', lock: true},
    match_100: {enabled: false, status: 'TRANSLATED', lock: false},
  },
  dialectStrict = false,
  modifyingCurrentTemplate = jest.fn(),
  projectTemplates = [],
} = {}) =>
  render(
    <SettingsPanelContext.Provider
      value={{
        modifyingCurrentTemplate,
        currentProjectTemplate: {pretranslate, dialectStrict},
        projectTemplates,
      }}
    >
      <AdvancedSettings />
    </SettingsPanelContext.Provider>,
  )

test('Enabling a pre-confirm switch writes the default status and lock', async () => {
  const user = userEvent.setup()
  const modifyingCurrentTemplate = jest.fn()
  renderAdvancedSettings({modifyingCurrentTemplate})

  await act(async () =>
    user.click(screen.getByTestId('pretranslate-match_100-switch')),
  )

  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  expect(
    updater({
      pretranslate: {
        match_101: {enabled: true, status: 'APPROVED', lock: true},
        match_100: {enabled: false, status: 'TRANSLATED', lock: false},
      },
    }),
  ).toEqual({
    pretranslate: {
      match_101: {enabled: true, status: 'APPROVED', lock: true},
      match_100: {enabled: true, status: 'APPROVED', lock: true},
    },
  })
})

test('Pre-confirm switch reflects the template', () => {
  renderAdvancedSettings({
    pretranslate: {
      match_101: {enabled: false, status: 'APPROVED', lock: true},
      match_100: {enabled: true, status: 'APPROVED', lock: true},
    },
  })

  expect(screen.getByTestId('pretranslate-match_100-switch')).toBeChecked()
  expect(screen.getByTestId('pretranslate-match_101-switch')).not.toBeChecked()
})

test('Status select shows the default and is disabled while pre-confirm is off', async () => {
  const user = userEvent.setup()
  renderAdvancedSettings()

  const row101 = screen.getByTestId('pretranslate-match_101')
  const row100 = screen.getByTestId('pretranslate-match_100')

  expect(row101).toHaveTextContent('Approved (locked)')
  expect(row100).toHaveTextContent('Approved (locked)')
  expect(row100).not.toHaveTextContent('Translated')

  await act(async () =>
    user.click(within(row100).getByText('Approved (locked)')),
  )
  expect(screen.queryByText('Approved 2')).not.toBeInTheDocument()

  await act(async () =>
    user.click(within(row101).getByText('Approved (locked)')),
  )
  expect(screen.getByText('Approved 2')).toBeInTheDocument()
})

test('Switching pre-confirm off restores the saved status and lock', async () => {
  const user = userEvent.setup()
  const modifyingCurrentTemplate = jest.fn()
  const saved = {
    match_101: {enabled: true, status: 'APPROVED', lock: true},
    match_100: {enabled: false, status: 'TRANSLATED', lock: false},
  }
  renderAdvancedSettings({
    modifyingCurrentTemplate,
    pretranslate: {
      ...saved,
      match_100: {enabled: true, status: 'APPROVED', lock: true},
    },
    projectTemplates: [
      {isSelected: true, isTemporary: true, pretranslate: {}},
      {isSelected: true, isTemporary: false, pretranslate: saved},
    ],
  })

  await act(async () =>
    user.click(screen.getByTestId('pretranslate-match_100-switch')),
  )

  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  expect(
    updater({
      pretranslate: {
        ...saved,
        match_100: {enabled: true, status: 'APPROVED', lock: true},
      },
    }),
  ).toEqual({pretranslate: saved})
})

test('Selecting a pre-confirm status writes status and lock', async () => {
  const user = userEvent.setup()
  const modifyingCurrentTemplate = jest.fn()
  renderAdvancedSettings({modifyingCurrentTemplate})

  const row101 = screen.getByTestId('pretranslate-match_101')
  await act(async () =>
    user.click(within(row101).getByText('Approved (locked)')),
  )
  await act(async () => user.click(screen.getByText('Approved 2')))

  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  expect(
    updater({
      pretranslate: {
        match_101: {enabled: true, status: 'APPROVED', lock: true},
        match_100: {enabled: false, status: 'TRANSLATED', lock: false},
      },
    }),
  ).toEqual({
    pretranslate: {
      match_101: {enabled: true, status: 'APPROVED2', lock: false},
      match_100: {enabled: false, status: 'TRANSLATED', lock: false},
    },
  })
})

test('Variant matching radios reflect and write dialect strict', async () => {
  const user = userEvent.setup()
  const modifyingCurrentTemplate = jest.fn()
  renderAdvancedSettings({modifyingCurrentTemplate})

  expect(screen.getByTestId('variant-matching-all')).toBeChecked()
  expect(screen.getByTestId('variant-matching-exact')).not.toBeChecked()

  await act(async () => user.click(screen.getByLabelText('Exact variant only')))

  const updater = modifyingCurrentTemplate.mock.calls[0][0]
  expect(updater({dialectStrict: false})).toEqual({dialectStrict: true})
})

test('Exact variant only is checked when dialect strict is on', () => {
  renderAdvancedSettings({dialectStrict: true})

  expect(screen.getByTestId('variant-matching-exact')).toBeChecked()
  expect(screen.getByTestId('variant-matching-all')).not.toBeChecked()
})
