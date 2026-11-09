import Cookies from 'js-cookie'
import {addNotification} from '../../actions/notificationActions'
import {SETTINGS_PANEL_TABS} from '../settingsPanel/SettingsPanel'
import {
  RELEASE_ANNOUNCEMENTS,
  SHOW_UNTIL,
  announceSettingsTab,
  showReleaseAnnouncement,
} from './releaseAnnouncements'

jest.mock('js-cookie', () => ({get: jest.fn(), set: jest.fn()}))
jest.mock('../../actions/notificationActions', () => ({
  addNotification: jest.fn(),
}))

const {id: qrId} = RELEASE_ANNOUNCEMENTS.qualityReport
const shownNotification = () => addNotification.mock.calls[0][0]

beforeEach(() => {
  jest.clearAllMocks()
  Cookies.get.mockReturnValue(undefined)
  jest.useFakeTimers().setSystemTime(new Date('2026-11-16T10:00:00Z'))
})

afterEach(() => jest.useRealTimers())

test('shows the announcement for 30 seconds', () => {
  showReleaseAnnouncement('qualityReport')

  expect(addNotification).toHaveBeenCalledTimes(1)
  expect(shownNotification()).toMatchObject({
    uid: qrId,
    title: 'Dive into segment history',
    autoDismiss: true,
    timer: 30000,
  })
})

test('shows the TM settings one bottom right and the others bottom left', () => {
  showReleaseAnnouncement('tmSettings')
  showReleaseAnnouncement('qualityReport')

  expect(addNotification.mock.calls.map(([n]) => [n.uid, n.position])).toEqual([
    [RELEASE_ANNOUNCEMENTS.tmSettings.id, 'br'],
    [qrId, 'bl'],
  ])
})

test('does not show anything after the announcement period', () => {
  jest.setSystemTime(new Date(SHOW_UNTIL.getTime() + 1000))

  showReleaseAnnouncement('qualityReport')

  expect(addNotification).not.toHaveBeenCalled()
})

test('stays hidden in a browser where the user already closed it', () => {
  Cookies.get.mockImplementation((name) =>
    name === 'msg-' + qrId ? '' : undefined,
  )

  showReleaseAnnouncement('qualityReport')
  showReleaseAnnouncement('tmSettings')

  expect(addNotification.mock.calls.map(([n]) => n.uid)).toEqual([
    RELEASE_ANNOUNCEMENTS.tmSettings.id,
  ])
})

test('closing it with the X hides it in this browser until the period ends', () => {
  showReleaseAnnouncement('qualityReport')

  shownNotification().closeCallback(true)

  expect(Cookies.set).toHaveBeenCalledWith('msg-' + qrId, '', {
    expires: SHOW_UNTIL,
    secure: true,
  })
})

test('it shows again after timing out without the user closing it', () => {
  showReleaseAnnouncement('qualityReport')

  shownNotification().closeCallback(false)

  expect(Cookies.set).not.toHaveBeenCalled()
})

test('the settings panel shows the announcement of the tab the user opens', () => {
  announceSettingsTab(SETTINGS_PANEL_TABS.translationMemoryGlossary)
  announceSettingsTab(SETTINGS_PANEL_TABS.machineTranslation)
  announceSettingsTab(SETTINGS_PANEL_TABS.fileImport)

  expect(addNotification.mock.calls.map(([n]) => n.uid)).toEqual([
    RELEASE_ANNOUNCEMENTS.tmSettings.id,
    RELEASE_ANNOUNCEMENTS.fileImportSettings.id,
  ])
})
