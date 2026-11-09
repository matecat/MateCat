import Cookies from 'js-cookie'
import {addNotification} from '../../actions/notificationActions'
import {
  RELEASE_ANNOUNCEMENTS,
  SHOW_UNTIL,
  showReleaseAnnouncement,
} from './releaseAnnouncements'

jest.mock('js-cookie', () => ({get: jest.fn(), set: jest.fn()}))
jest.mock('../../actions/notificationActions', () => ({
  addNotification: jest.fn(),
}))

const {id: qrId} = RELEASE_ANNOUNCEMENTS.qualityReport

const anonymous = () => ({isUserLogged: false, userInfo: undefined})
const loggedUser = (metadata = {}) => ({
  isUserLogged: true,
  userInfo: {metadata},
  setUserMetadataKey: jest.fn(() => Promise.resolve()),
})
const shownNotification = () => addNotification.mock.calls[0][0]

beforeEach(() => {
  jest.clearAllMocks()
  Cookies.get.mockReturnValue(undefined)
  jest.useFakeTimers().setSystemTime(new Date('2026-11-16T10:00:00Z'))
})

afterEach(() => jest.useRealTimers())

test('shows the announcement for 30 seconds', () => {
  showReleaseAnnouncement('qualityReport', anonymous())

  expect(addNotification).toHaveBeenCalledTimes(1)
  expect(shownNotification()).toMatchObject({
    uid: qrId,
    title: 'Dive into segment history',
    autoDismiss: true,
    timer: 30000,
  })
})

test('shows the TM settings one bottom right and the others bottom left', () => {
  showReleaseAnnouncement('tmSettings', anonymous())
  showReleaseAnnouncement('qualityReport', anonymous())

  expect(addNotification.mock.calls.map(([n]) => [n.uid, n.position])).toEqual([
    [RELEASE_ANNOUNCEMENTS.tmSettings.id, 'br'],
    [qrId, 'bl'],
  ])
})

test('does not show anything after the announcement period', () => {
  jest.setSystemTime(new Date(SHOW_UNTIL.getTime() + 1000))

  showReleaseAnnouncement('qualityReport', anonymous())

  expect(addNotification).not.toHaveBeenCalled()
})

test('stays hidden in a browser where the user already closed it', () => {
  Cookies.get.mockImplementation((name) =>
    name === 'msg-' + qrId ? '' : undefined,
  )

  showReleaseAnnouncement('qualityReport', anonymous())

  expect(addNotification).not.toHaveBeenCalled()
})

test('stays hidden for a logged user who closed it in another browser', () => {
  showReleaseAnnouncement('qualityReport', loggedUser({[qrId]: '1'}))

  expect(addNotification).not.toHaveBeenCalled()
})

test('closing it with the X hides it in this browser and for the account', () => {
  const user = loggedUser()
  showReleaseAnnouncement('qualityReport', user)

  shownNotification().closeCallback(true)

  expect(Cookies.set).toHaveBeenCalledWith('msg-' + qrId, '', {
    expires: SHOW_UNTIL,
    secure: true,
  })
  expect(user.setUserMetadataKey).toHaveBeenCalledWith(qrId, '1')
})

test('an anonymous user closing it is remembered by this browser only', () => {
  showReleaseAnnouncement('qualityReport', anonymous())

  shownNotification().closeCallback(true)

  expect(Cookies.set).toHaveBeenCalledTimes(1)
})

test('it shows again after timing out without the user closing it', () => {
  const user = loggedUser()
  showReleaseAnnouncement('qualityReport', user)

  shownNotification().closeCallback(false)

  expect(Cookies.set).not.toHaveBeenCalled()
  expect(user.setUserMetadataKey).not.toHaveBeenCalled()
})

test('a failed metadata save does not surface as an error', async () => {
  const user = loggedUser()
  user.setUserMetadataKey.mockReturnValue(Promise.reject(new Error('500')))
  showReleaseAnnouncement('qualityReport', user)

  expect(() => shownNotification().closeCallback(true)).not.toThrow()
  await Promise.resolve()
  expect(Cookies.set).toHaveBeenCalledTimes(1)
})
