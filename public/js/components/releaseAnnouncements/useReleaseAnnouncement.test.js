import React from 'react'
import {renderHook} from '@testing-library/react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {SETTINGS_PANEL_TABS} from '../settingsPanel/SettingsPanel'
import {useSettingsTabAnnouncement} from './useReleaseAnnouncement'
import {showReleaseAnnouncement} from './releaseAnnouncements'

jest.mock('./releaseAnnouncements', () => ({
  showReleaseAnnouncement: jest.fn(),
}))

const user = {isUserLogged: true, userInfo: {metadata: {}}}
const wrapper = ({children}) => (
  <ApplicationWrapperContext.Provider value={user}>
    {children}
  </ApplicationWrapperContext.Provider>
)

beforeEach(() => jest.clearAllMocks())

test('shows the announcement of the settings tab the user opens', () => {
  const {result} = renderHook(() => useSettingsTabAnnouncement(), {wrapper})

  result.current(SETTINGS_PANEL_TABS.translationMemoryGlossary)
  result.current(SETTINGS_PANEL_TABS.machineTranslation)
  result.current(SETTINGS_PANEL_TABS.fileImport)

  expect(showReleaseAnnouncement.mock.calls.map(([key]) => key)).toEqual([
    'tmSettings',
    'fileImportSettings',
  ])
  expect(showReleaseAnnouncement).toHaveBeenLastCalledWith(
    'fileImportSettings',
    expect.objectContaining({isUserLogged: true}),
  )
})

test('keeps the same callback across renders', () => {
  const {result, rerender} = renderHook(() => useSettingsTabAnnouncement(), {
    wrapper,
  })
  const first = result.current

  rerender()

  expect(result.current).toBe(first)
})
