import {useState, useEffect, useCallback} from 'react'
import UserStore from '../../../stores/UserStore'
import ModalsActions from '../../../actions/ModalsActions'
import {getUserConnectedService} from '../../../api/getUserConnectedService'

/**
 * Custom hook that manages Google Drive Picker API initialization,
 * authentication, and picker creation.
 */
export function useGDrivePicker({setIsGDriveEnabled, onFilesPicked}) {
  const [authApiLoaded, setAuthApiLoaded] = useState(false)
  const [pickerApiLoaded, setPickerApiLoaded] = useState(false)

  useEffect(() => {
    try {
      if (gapi) {
        gapi.load('auth', {callback: setAuthApiLoaded(true)})
        gapi.load('picker', {callback: setPickerApiLoaded(true)})
      }
    } catch (e) {
      console.error('Google API not loaded')
      setIsGDriveEnabled(false)
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const gdriveInitComplete = useCallback(
    () => pickerApiLoaded && authApiLoaded,
    [pickerApiLoaded, authApiLoaded],
  )

  const showPreferencesWithMessage = useCallback(() => {
    ModalsActions.openPreferencesModal({showGDriveMessage: true})
  }, [])

  const createPicker = useCallback(
    (service) => {
      const token = JSON.parse(service.oauth_access_token)
      const picker = new google.picker.PickerBuilder()
        .setAppId(window.clientId)
        .addView(google.picker.ViewId.DOCUMENTS)
        .addView(google.picker.ViewId.PRESENTATIONS)
        .addView(google.picker.ViewId.SPREADSHEETS)
        .setOAuthToken(token.access_token)
        .setDeveloperKey(window.developerKey)
        .setCallback(onFilesPicked)
        .enableFeature(google.picker.Feature.MINE_ONLY)
        .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
        .build()
      try {
        picker.setVisible(true)
      } catch (e) {
        UserStore.updateConnectedService({service, is_default: false})
        throw new Error('Picker Error')
      }
    },
    [onFilesPicked],
  )

  const openPicker = useCallback(() => {
    if (!gdriveInitComplete()) {
      console.log('gdriveInitComplete not complete')
      return
    }

    const defaultService = UserStore.getDefaultConnectedService()

    if (!defaultService) {
      showPreferencesWithMessage()
      return
    }

    getUserConnectedService(defaultService.id)
      .then((data) => {
        UserStore.updateConnectedService(data.connected_service)
        createPicker(UserStore.getDefaultConnectedService())
      })
      .catch(() => {
        UserStore.updateConnectedService({defaultService, is_default: false})
        showPreferencesWithMessage()
      })
  }, [gdriveInitComplete, showPreferencesWithMessage, createPicker])

  return {openPicker}
}
