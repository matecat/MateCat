import {renderHook, act} from '@testing-library/react'
import {useGDrivePicker} from './useGDrivePicker'
import UserStore from '../../../stores/UserStore'
import ModalsActions from '../../../actions/ModalsActions'
import {getUserConnectedService} from '../../../api/getUserConnectedService'

jest.mock('../../../stores/UserStore', () => ({
  getDefaultConnectedService: jest.fn(),
  updateConnectedService: jest.fn(),
}))
jest.mock('../../../actions/ModalsActions', () => ({
  openPreferencesModal: jest.fn(),
}))
jest.mock('../../../api/getUserConnectedService', () => ({
  getUserConnectedService: jest.fn(),
}))

const baseProps = () => ({
  setIsGDriveEnabled: jest.fn(),
  onFilesPicked: jest.fn(),
})

const buildPickerBuilder = (setVisible) =>
  jest.fn(() => ({
    addView: jest.fn().mockReturnThis(),
    setAppId: jest.fn().mockReturnThis(),
    setOAuthToken: jest.fn().mockReturnThis(),
    setDeveloperKey: jest.fn().mockReturnThis(),
    setCallback: jest.fn().mockReturnThis(),
    enableFeature: jest.fn().mockReturnThis(),
    build: jest.fn(() => ({setVisible})),
  }))

describe('useGDrivePicker', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    // `gapi.load('auth', {callback: setAuthApiLoaded(true)})` in the real
    // source invokes the state setter while building the argument object,
    // *before* gapi.load itself is ever called - so a plain jest.fn() (which
    // does nothing) is enough for both loaded flags to flip true. It must
    // NOT try to invoke `callback` itself: real code never assigns a
    // function there (it assigns the return value of the setter, i.e.
    // undefined), so calling it would throw.
    global.gapi = {load: jest.fn()}
    global.google = {
      picker: {
        PickerBuilder: buildPickerBuilder(jest.fn()),
        ViewId: {
          DOCUMENTS: 'docs',
          PRESENTATIONS: 'pres',
          SPREADSHEETS: 'sheets',
        },
        Feature: {MINE_ONLY: 'mine', MULTISELECT_ENABLED: 'multi'},
      },
    }
  })

  test('calls setIsGDriveEnabled(false) when gapi is not declared at all', () => {
    delete global.gapi
    const props = baseProps()
    renderHook(() => useGDrivePicker(props))
    expect(props.setIsGDriveEnabled).toHaveBeenCalledWith(false)
  })

  test('openPicker does nothing while the gapi auth/picker apis have not loaded yet', () => {
    // Assigned-but-falsy (as opposed to deleted): `if (gapi)` is false, so
    // the effect body never runs and neither loaded flag is ever set.
    global.gapi = null
    const props = baseProps()
    const {result} = renderHook(() => useGDrivePicker(props))
    act(() => {
      result.current.openPicker()
    })
    expect(props.setIsGDriveEnabled).not.toHaveBeenCalled()
    expect(UserStore.getDefaultConnectedService).not.toHaveBeenCalled()
  })

  test('openPicker shows preferences message when there is no default connected service', () => {
    UserStore.getDefaultConnectedService.mockReturnValue(null)
    const props = baseProps()
    const {result} = renderHook(() => useGDrivePicker(props))
    act(() => {
      result.current.openPicker()
    })
    expect(ModalsActions.openPreferencesModal).toHaveBeenCalledWith({
      showGDriveMessage: true,
    })
  })

  test('openPicker fetches the connected service and builds a picker on success', async () => {
    const service = {
      id: 42,
      oauth_access_token: JSON.stringify({access_token: 'tok'}),
    }
    // getDefaultConnectedService is read twice by production code (once to
    // find the id to verify, once again after the store update to build the
    // picker) - it's fully mocked here, so both calls must resolve to a
    // service object that already carries the oauth token, or the second
    // (real) JSON.parse(service.oauth_access_token) call throws.
    UserStore.getDefaultConnectedService.mockReturnValue(service)
    getUserConnectedService.mockResolvedValue({connected_service: service})
    const setVisible = jest.fn()
    global.google.picker.PickerBuilder = buildPickerBuilder(setVisible)
    const props = baseProps()
    const {result} = renderHook(() => useGDrivePicker(props))
    await act(async () => {
      result.current.openPicker()
    })
    expect(getUserConnectedService).toHaveBeenCalledWith(42)
    expect(UserStore.updateConnectedService).toHaveBeenCalledWith(service)
    expect(setVisible).toHaveBeenCalledWith(true)
    expect(ModalsActions.openPreferencesModal).not.toHaveBeenCalled()
  })

  test('openPicker shows preferences message when getUserConnectedService rejects', async () => {
    UserStore.getDefaultConnectedService.mockReturnValue({id: 42})
    getUserConnectedService.mockRejectedValue(new Error('fail'))
    const props = baseProps()
    const {result} = renderHook(() => useGDrivePicker(props))
    await act(async () => {
      result.current.openPicker()
    })
    expect(UserStore.updateConnectedService).toHaveBeenCalledWith(
      expect.objectContaining({is_default: false}),
    )
    expect(ModalsActions.openPreferencesModal).toHaveBeenCalledWith({
      showGDriveMessage: true,
    })
  })

  test('openPicker marks the service non-default and reopens preferences when the picker fails to show', async () => {
    const service = {
      id: 42,
      oauth_access_token: JSON.stringify({access_token: 'tok'}),
    }
    UserStore.getDefaultConnectedService.mockReturnValue(service)
    getUserConnectedService.mockResolvedValue({connected_service: service})
    const setVisible = jest.fn(() => {
      throw new Error('boom')
    })
    global.google.picker.PickerBuilder = buildPickerBuilder(setVisible)
    const props = baseProps()
    const {result} = renderHook(() => useGDrivePicker(props))
    await act(async () => {
      result.current.openPicker()
    })
    expect(setVisible).toHaveBeenCalledWith(true)
    expect(UserStore.updateConnectedService).toHaveBeenCalledWith(
      expect.objectContaining({is_default: false}),
    )
    expect(ModalsActions.openPreferencesModal).toHaveBeenCalledWith({
      showGDriveMessage: true,
    })
  })
})
