import Cookies from 'js-cookie'
import {addNotification} from '../../actions/notificationActions'
import {SETTINGS_PANEL_TABS} from '../settingsPanel/SettingsPanelConstants'
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
  showReleaseAnnouncement('qualityReport')
  showReleaseAnnouncement('tmSettings')
  jest.advanceTimersByTime(1000)

  expect(addNotification.mock.calls.map(([n]) => [n.uid, n.position])).toEqual([
    [qrId, 'bl'],
    [RELEASE_ANNOUNCEMENTS.tmSettings.id, 'br'],
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
  jest.advanceTimersByTime(1000)

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
  jest.advanceTimersByTime(1000)
  announceSettingsTab(SETTINGS_PANEL_TABS.machineTranslation)
  jest.advanceTimersByTime(1000)
  announceSettingsTab(SETTINGS_PANEL_TABS.fileImport)
  jest.advanceTimersByTime(1000)

  expect(addNotification.mock.calls.map(([n]) => n.uid)).toEqual([
    RELEASE_ANNOUNCEMENTS.tmSettings.id,
    RELEASE_ANNOUNCEMENTS.fileImportSettings.id,
  ])
})

test('a settings tab announcement waits a second for the tab content to settle', () => {
  announceSettingsTab(SETTINGS_PANEL_TABS.fileImport)

  jest.advanceTimersByTime(999)
  expect(addNotification).not.toHaveBeenCalled()

  jest.advanceTimersByTime(1)
  expect(addNotification).toHaveBeenCalledTimes(1)
})

test('switching tab while it waits drops the previous tab announcement', () => {
  announceSettingsTab(SETTINGS_PANEL_TABS.translationMemoryGlossary)
  jest.advanceTimersByTime(500)
  announceSettingsTab(SETTINGS_PANEL_TABS.machineTranslation)
  jest.advanceTimersByTime(2000)

  expect(addNotification).not.toHaveBeenCalled()
})

test('a waiting announcement closed meanwhile in another browser tab is not shown', () => {
  announceSettingsTab(SETTINGS_PANEL_TABS.fileImport)
  Cookies.get.mockReturnValue('')
  jest.advanceTimersByTime(1000)

  expect(addNotification).not.toHaveBeenCalled()
})
