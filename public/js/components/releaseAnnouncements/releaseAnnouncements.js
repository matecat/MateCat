/**
 * Temporary notifications announcing the main changes of the 2026-11 release.
 * Delete this folder and its callers once SHOW_UNTIL has passed.
 *
 * An announcement stays hidden once the user closes it with the X. The choice is
 * kept in a cookie for this browser and, for a logged user, in the user
 * metadata, so it follows the account to other browsers.
 */
import React from 'react'
import Cookies from 'js-cookie'
import {addNotification} from '../../actions/notificationActions'

export const SHOW_UNTIL = new Date('2026-11-30T23:59:59Z')
const AUTO_DISMISS_MS = 30000

export const RELEASE_ANNOUNCEMENTS = {
  tmSettings: {
    id: 'release_202611_tm_settings',
    title: 'New advanced settings',
    position: 'br',
    text: (
      <>
        Looking for pre-confirmation and variant‑matching settings?
        <br />
        We moved them to the new Advanced subtab.
        <br />
        We also gave you more control: you can now include 101% matches in your
        pre-confirmation rules and choose the exact state they get in the
        editor.
      </>
    ),
  },
  fileImportSettings: {
    id: 'release_202611_file_import_settings',
    title: 'New XLIFF import settings',
    position: 'br',
    text: (
      <>
        Working with XLIFF files just got more flexible.
        <br />
        You can now decide whether pre-populated segments should be
        automatically locked in the editor or not.
        <br />
        Scroll down to the dedicated section to try it out.
      </>
    ),
  },
  qualityReport: {
    id: 'release_202611_quality_report',
    title: 'Dive into segment history',
    text: (
      <>
        Wondering how a translation evolved?
        <br />
        You can now track a segment&apos;s history directly from the QR page.
        <br />
        Just click the <b>Open history</b> button in the bottom right corner of
        a segment&apos;s details panel to see a list of every confirmation step
        and the exact text confirmed at each stage.
      </>
    ),
  },
}

const cookieName = (id) => 'msg-' + id

const isHidden = (id, userInfo) =>
  typeof Cookies.get(cookieName(id)) !== 'undefined' ||
  typeof userInfo?.metadata?.[id] !== 'undefined'

const hideFromNowOn = (id, {isUserLogged, setUserMetadataKey}) => {
  Cookies.set(cookieName(id), '', {expires: SHOW_UNTIL, secure: true})
  // A failed save only loses the cross-browser part: the cookie still hides
  // the announcement here, so there is nothing to tell the user.
  if (isUserLogged) setUserMetadataKey(id, '1').catch(() => {})
}

/**
 * @param {keyof RELEASE_ANNOUNCEMENTS} key
 * @param {{isUserLogged: boolean, userInfo: object, setUserMetadataKey: Function}} user
 */
export const showReleaseAnnouncement = (key, user) => {
  const {id, title, text, position = 'bl'} = RELEASE_ANNOUNCEMENTS[key]
  if (new Date() > SHOW_UNTIL || isHidden(id, user.userInfo)) return

  addNotification({
    uid: id,
    title,
    text,
    type: 'info',
    position,
    autoDismiss: true,
    timer: AUTO_DISMISS_MS,
    closeCallback: (closedByUser) => {
      if (closedByUser) hideFromNowOn(id, user)
    },
  })
}
