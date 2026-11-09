import {useCallback, useContext, useRef} from 'react'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
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
