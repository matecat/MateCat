import {useCallback, useContext, useRef} from 'react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {SETTINGS_PANEL_TABS} from '../settingsPanel/SettingsPanel'
import {showReleaseAnnouncement} from './releaseAnnouncements'

/**
 * Returns a stable `show(key)` bound to the current user, so a caller can list
 * it in an effect's dependencies without the effect running again every time
 * the user metadata changes.
 */
export const useReleaseAnnouncement = () => {
  const {isUserLogged, userInfo, setUserMetadataKey} = useContext(
    ApplicationWrapperContext,
  )
  const user = useRef()
  user.current = {isUserLogged, userInfo, setUserMetadataKey}

  return useCallback((key) => showReleaseAnnouncement(key, user.current), [])
}

const ANNOUNCEMENT_BY_SETTINGS_TAB = {
  [SETTINGS_PANEL_TABS.translationMemoryGlossary]: 'tmSettings',
  [SETTINGS_PANEL_TABS.fileImport]: 'fileImportSettings',
}

/**
 * Returns a stable callback for the settings panel `onTabOpened`, showing the
 * announcement of the tab the user opened, if it has one.
 */
export const useSettingsTabAnnouncement = () => {
  const show = useReleaseAnnouncement()
  return useCallback(
    (tabId) => {
      const key = ANNOUNCEMENT_BY_SETTINGS_TAB[tabId]
      if (key) show(key)
    },
    [show],
  )
}
