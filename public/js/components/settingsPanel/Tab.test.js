import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SettingsPanelContext} from './SettingsPanelContext'
import {Tab} from './Tab'
import {SETTINGS_PANEL_TABS, TEMPLATE_PROPS_BY_TAB} from './SettingsPanelConstants'

const renderTab = ({id = SETTINGS_PANEL_TABS.machineTranslation, label = 'Machine Translation'} = {}, contextOverrides = {}) =>
  render(
    <SettingsPanelContext.Provider
      value={{
        tabs: [{id, label, isOpened: false}],
        setTabs: jest.fn(),
        checkSpecificTemplatePropsAreModified: () => false,
        isEnabledProjectTemplateComponent: true,
        subtemplatesNotSaved: [],
        ...contextOverrides,
      }}
    >
      <Tab id={id} label={label} />
    </SettingsPanelContext.Provider>,
  )

describe('Tab', () => {
  test('renders the label', () => {
    renderTab()
    expect(screen.getByText('Machine Translation')).toBeInTheDocument()
  })

  test('is not active when tab.isOpened is false', () => {
    renderTab()
    expect(screen.getByText('Machine Translation').closest('li')).not.toHaveClass(
      'settings-panel-tab-active',
    )
  })

  test('is active when tab.isOpened is true', () => {
    render(
      <SettingsPanelContext.Provider
        value={{
          tabs: [{id: SETTINGS_PANEL_TABS.machineTranslation, label: 'Machine Translation', isOpened: true}],
          setTabs: jest.fn(),
          checkSpecificTemplatePropsAreModified: () => false,
          isEnabledProjectTemplateComponent: true,
          subtemplatesNotSaved: [],
        }}
      >
        <Tab id={SETTINGS_PANEL_TABS.machineTranslation} label="Machine Translation" />
      </SettingsPanelContext.Provider>,
    )
    expect(screen.getByText('Machine Translation').closest('li')).toHaveClass(
      'settings-panel-tab-active',
    )
  })

  test('clicking calls setTabs, opening only the clicked tab', async () => {
    const setTabs = jest.fn()
    renderTab(undefined, {setTabs})
    await userEvent.click(screen.getByText('Machine Translation'))
    expect(setTabs).toHaveBeenCalledTimes(1)
    const updater = setTabs.mock.calls[0][0]
    const result = updater([
      {id: SETTINGS_PANEL_TABS.machineTranslation, isOpened: false},
      {id: SETTINGS_PANEL_TABS.other, isOpened: true},
    ])
    expect(result).toEqual([
      {id: SETTINGS_PANEL_TABS.machineTranslation, isOpened: true},
      {id: SETTINGS_PANEL_TABS.other, isOpened: false},
    ])
  })

  test('does not show the modifying icon when isEnabledProjectTemplateComponent is false', () => {
    renderTab(
      {id: SETTINGS_PANEL_TABS.machineTranslation, label: 'Machine Translation'},
      {
        isEnabledProjectTemplateComponent: false,
        checkSpecificTemplatePropsAreModified: () => true,
      },
    )
    expect(document.querySelector('.settings-panel-tab-modifyng-icon')).not.toBeInTheDocument()
  })

  test('shows the modifying icon when the template props for this tab are modified', () => {
    renderTab(
      {id: SETTINGS_PANEL_TABS.machineTranslation, label: 'Machine Translation'},
      {
        isEnabledProjectTemplateComponent: true,
        checkSpecificTemplatePropsAreModified: (props) =>
          isEqualToTemplatePropsByTab(props, SETTINGS_PANEL_TABS.machineTranslation),
      },
    )
    expect(document.querySelector('.settings-panel-tab-modifyng-icon')).toBeInTheDocument()
  })

  test('shows the modifying icon when a subtemplate for this tab is not saved', () => {
    renderTab(
      {id: SETTINGS_PANEL_TABS.other, label: 'Other'},
      {
        isEnabledProjectTemplateComponent: true,
        checkSpecificTemplatePropsAreModified: () => false,
        subtemplatesNotSaved: TEMPLATE_PROPS_BY_TAB[SETTINGS_PANEL_TABS.other],
      },
    )
    expect(document.querySelector('.settings-panel-tab-modifyng-icon')).toBeInTheDocument()
  })
})

function isEqualToTemplatePropsByTab(props, tabId) {
  return JSON.stringify(props) === JSON.stringify(TEMPLATE_PROPS_BY_TAB[tabId])
}
