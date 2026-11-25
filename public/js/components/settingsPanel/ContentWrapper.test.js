import React from 'react'
import {render, screen} from '@testing-library/react'
import {SettingsPanelContext} from './SettingsPanelContext'
import {ContentWrapper} from './ContentWrapper'

const renderWithTabs = (tabs, isEnabledProjectTemplateComponent = true) =>
  render(
    <SettingsPanelContext.Provider
      value={{
        tabs,
        isEnabledProjectTemplateComponent,
        setTabs: jest.fn(),
        checkSpecificTemplatePropsAreModified: () => false,
        subtemplatesNotSaved: [],
      }}
    >
      <ContentWrapper />
    </SettingsPanelContext.Provider>,
  )

describe('ContentWrapper', () => {
  const tabs = [
    {
      id: 'mt',
      label: 'Machine Translation',
      description: 'Manage <b>MT</b> engines',
      isOpened: false,
      component: <div data-testid="mt-content">MT content</div>,
    },
    {
      id: 'tm',
      label: 'Translation Memory',
      description: 'Manage TM',
      isOpened: true,
      component: <div data-testid="tm-content">TM content</div>,
    },
  ]

  test('renders a Tab entry for each tab', () => {
    renderWithTabs(tabs)
    expect(screen.getByText('Machine Translation')).toBeInTheDocument()
    expect(screen.getAllByText('Translation Memory').length).toBeGreaterThan(0)
  })

  test('renders the active tab label and description', () => {
    renderWithTabs(tabs)
    expect(
      screen.getByRole('heading', {level: 3, name: 'Translation Memory'}),
    ).toBeInTheDocument()
    expect(screen.getByText('Manage TM')).toBeInTheDocument()
  })

  test('renders only the active tab content', () => {
    renderWithTabs(tabs)
    expect(screen.getByTestId('tm-content')).toBeInTheDocument()
    expect(screen.queryByTestId('mt-content')).not.toBeInTheDocument()
  })

  test('adds the "without project template control" class when disabled', () => {
    const {container} = renderWithTabs(tabs, false)
    expect(
      container.querySelector(
        '.settings-panel-contentwrapper-container-without-project-teamplate-control',
      ),
    ).toBeInTheDocument()
  })

  test('does not add the "without project template control" class when enabled', () => {
    const {container} = renderWithTabs(tabs, true)
    expect(
      container.querySelector(
        '.settings-panel-contentwrapper-container-without-project-teamplate-control',
      ),
    ).not.toBeInTheDocument()
  })
})
