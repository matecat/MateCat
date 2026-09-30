import AppDispatcher from './AppDispatcher'
import CatToolStore from './CatToolStore'
import CatToolConstants from '../constants/CatToolConstants'
import ModalsConstants from '../constants/ModalsConstants'

describe('CatToolStore', () => {
  beforeEach(() => {
    CatToolStore.files = null
    CatToolStore.qr = null
    CatToolStore.firstLoad = true
    CatToolStore.languages = []
    CatToolStore.searchResults = {
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: 0,
    }
    CatToolStore.clientConnected = false
    CatToolStore.clientId = undefined
    CatToolStore.tmKeys = null
    CatToolStore.keysDomains = null
    CatToolStore.haveKeysGlossary = undefined
    CatToolStore.jobMetadata = undefined
    CatToolStore._projectProgress = undefined
    CatToolStore._currentProjectTemplate = undefined
    jest.clearAllMocks()
  })

  test('getFirstLoad returns the current first load flag', () => {
    expect(CatToolStore.getFirstLoad()).toBe(true)
  })

  test('storeFilesInfo/getJobFilesInfo stores and returns files', () => {
    CatToolStore.storeFilesInfo([{id: 1}])

    expect(CatToolStore.getJobFilesInfo()).toEqual([{id: 1}])
  })

  test('setProgress computes derived progress stats', () => {
    CatToolStore.setProgress({
      raw: {
        draft: 0,
        new: 0,
        translated: 5,
        approved: 10,
        approved2: 10,
        total: 20,
      },
      equivalent: {draft: 0, new: 0, translated: 5, approved: 10},
    })

    expect(CatToolStore.getProgress()).toMatchObject({
      translationCompleted: true,
      revisionCompleted: false,
      revision1Completed: false,
      revision2Completed: false,
      translate_todo: 0,
      revise_todo: 5,
      revise2_todo: 15,
      translate_todo_total: 0,
      revise_todo_total: 5,
      revise2_todo_total: 15,
    })
  })

  test('updateQR/getQR filters reviews by revision number', () => {
    CatToolStore.updateQR({
      chunk: {
        reviews: [
          {revision_number: 1, score: 90},
          {revision_number: 2, score: 80},
        ],
      },
    })

    expect(CatToolStore.getQR(1)).toEqual([{revision_number: 1, score: 90}])
  })

  test('getQR returns null when no quality report is stored', () => {
    expect(CatToolStore.getQR(1)).toBeNull()
  })

  test('storeSearchResult stores the search result payload', () => {
    CatToolStore.storeSearchResult({searchResults: [1, 2]})

    expect(CatToolStore.searchResults).toEqual({searchResults: [1, 2]})
  })

  test('clientConnect sets connected state and client id', () => {
    CatToolStore.clientConnect('abc')

    expect(CatToolStore.isClientConnected()).toBe(true)
    expect(CatToolStore.getClientId()).toBe('abc')
  })

  test('clientConnect with a falsy id marks the client as disconnected', () => {
    CatToolStore.clientConnect(undefined)

    expect(CatToolStore.isClientConnected()).toBe(false)
  })

  test('updateJobTmKeys/getJobTmKeys derives a display name for unnamed keys', () => {
    CatToolStore.updateJobTmKeys([
      {key: 'k1', name: 'Named'},
      {key: 'k2', name: ''},
    ])

    expect(CatToolStore.getJobTmKeys()).toEqual([
      {key: 'k1', name: 'Named', isMissingName: false},
      {key: 'k2', name: 'No name (k2)', isMissingName: true},
    ])
  })

  test('updateKeysDomains/getKeysDomains stores and returns domains', () => {
    CatToolStore.updateKeysDomains([{id: 1}])

    expect(CatToolStore.getKeysDomains()).toEqual([{id: 1}])
  })

  test('getHaveKeysGlossary returns the stored flag', () => {
    CatToolStore.setHaveKeysGlossary(true)

    expect(CatToolStore.getHaveKeysGlossary()).toBe(true)
  })

  test('setLanguages/getLanguages stores and returns languages', () => {
    CatToolStore.setLanguages([{code: 'en-US'}])

    expect(CatToolStore.getLanguages()).toEqual([{code: 'en-US'}])
  })

  test('setCurrentProjectTemplate/getCurrentProjectTemplate stores template', () => {
    CatToolStore.setCurrentProjectTemplate({id: 1})

    expect(CatToolStore.getCurrentProjectTemplate()).toEqual({id: 1})
  })

  test('setJobMetadata/getJobMetadata stores metadata', () => {
    CatToolStore.setJobMetadata({sid: 1})

    expect(CatToolStore.getJobMetadata()).toEqual({sid: 1})
  })

  test('SHOW_CONTAINER action emits the container name', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.SHOW_CONTAINER,
      container: 'notes',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.SHOW_CONTAINER,
      'notes',
    )
  })

  test('SET_FIRST_LOAD action updates the first load flag', () => {
    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_FIRST_LOAD,
      value: false,
    })

    expect(CatToolStore.getFirstLoad()).toBe(false)
  })

  test('CLOSE_SUBHEADER action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: CatToolConstants.CLOSE_SUBHEADER})

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.CLOSE_SUBHEADER)
  })

  test('CLOSE_SEARCH action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: CatToolConstants.CLOSE_SEARCH})

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.CLOSE_SEARCH)
  })

  test('TOGGLE_CONTAINER action emits the container name', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.TOGGLE_CONTAINER,
      container: 'filter',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.TOGGLE_CONTAINER,
      'filter',
    )
  })

  test('SET_SEGMENT_FILTER action emits data and state', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_SEGMENT_FILTER,
      data: {status: 'draft'},
      state: true,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.SET_SEGMENT_FILTER,
      {status: 'draft'},
      true,
    )
  })

  test('RELOAD_SEGMENT_FILTER action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: CatToolConstants.RELOAD_SEGMENT_FILTER})

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.RELOAD_SEGMENT_FILTER)
  })

  test('STORE_FILES_INFO action stores files and emits them', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.STORE_FILES_INFO,
      files: [{id: 1}],
    })

    expect(CatToolStore.getJobFilesInfo()).toEqual([{id: 1}])
    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.STORE_FILES_INFO, [
      {id: 1},
    ])
  })

  test('SET_PROGRESS action computes progress and emits it', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.SET_PROGRESS,
      stats: {
        raw: {
          draft: 0,
          new: 0,
          translated: 0,
          approved: 5,
          approved2: 5,
          total: 5,
        },
        equivalent: {draft: 0, new: 0, translated: 0, approved: 5},
      },
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.SET_PROGRESS,
      CatToolStore._projectProgress,
    )
  })

  test('STORE_SEARCH_RESULT action stores and emits the search result', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.STORE_SEARCH_RESULT,
      data: {searchResults: [1]},
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.STORE_SEARCH_RESULT, {
      searchResults: [1],
    })
  })

  test('UPDATE_QR action stores the report and emits pass/score/feedback', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.UPDATE_QR,
      qr: {chunk: {reviews: []}},
      is_pass: true,
      score: 100,
      feedback: 'ok',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.UPDATE_QR,
      true,
      100,
      'ok',
    )
  })

  test('RELOAD_QR action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: CatToolConstants.RELOAD_QR})

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.RELOAD_QR)
  })

  test('CLIENT_CONNECT action connects the client and emits its id', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.CLIENT_CONNECT,
      clientId: 'xyz',
    })

    expect(CatToolStore.getClientId()).toBe('xyz')
    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.CLIENT_CONNECT, 'xyz')
  })

  test('CLIENT_RECONNECTION action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: CatToolConstants.CLIENT_RECONNECTION})

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.CLIENT_RECONNECTION)
  })

  test('ADD_NOTIFICATION action emits the notification', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.ADD_NOTIFICATION,
      notification: {id: 1},
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.ADD_NOTIFICATION, {
      id: 1,
    })
  })

  test('REMOVE_NOTIFICATION action emits the notification', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.REMOVE_NOTIFICATION,
      notification: {id: 1},
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.REMOVE_NOTIFICATION, {
      id: 1,
    })
  })

  test('REMOVE_ALL_NOTIFICATION action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.REMOVE_ALL_NOTIFICATION,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.REMOVE_ALL_NOTIFICATION,
    )
  })

  test('ModalsConstants.SHOW_MODAL action emits the modal configuration', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ModalsConstants.SHOW_MODAL,
      component: 'MyModal',
      props: {foo: 'bar'},
      title: 'Title',
      style: {},
      onCloseCallback: undefined,
      isCloseButtonDisabled: false,
      showHeader: true,
      styleBody: {},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ModalsConstants.SHOW_MODAL,
      'MyModal',
      {foo: 'bar'},
      'Title',
      {},
      undefined,
      true,
      {},
      false,
    )
  })

  test('ModalsConstants.CLOSE_MODAL action emits the action type', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ModalsConstants.CLOSE_MODAL})

    expect(emitSpy).toHaveBeenCalledWith(ModalsConstants.CLOSE_MODAL)
  })

  test('UPDATE_TM_KEYS action updates tm keys and emits them', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.UPDATE_TM_KEYS,
      keys: [{key: 'k1', name: 'Named'}],
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.UPDATE_TM_KEYS,
      CatToolStore.tmKeys,
    )
  })

  test('UPDATE_DOMAINS action stores domains and emits them with the sid', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.UPDATE_DOMAINS,
      sid: 1,
      entries: [{id: 1}],
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.UPDATE_DOMAINS, {
      sid: 1,
      entries: [{id: 1}],
    })
  })

  test('ON_RENDER action emits the full action payload', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.ON_RENDER,
      foo: 'bar',
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.ON_RENDER, {
      actionType: CatToolConstants.ON_RENDER,
      foo: 'bar',
    })
  })

  test('HAVE_KEYS_GLOSSARY action stores the flag and emits verification state', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.HAVE_KEYS_GLOSSARY,
      value: true,
      wasAlreadyVerified: true,
    })

    expect(CatToolStore.getHaveKeysGlossary()).toBe(true)
    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.HAVE_KEYS_GLOSSARY, {
      value: true,
      wasAlreadyVerified: true,
    })
  })

  test('OPEN_SETTINGS_PANEL action emits the full action payload', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.OPEN_SETTINGS_PANEL,
      tab: 'general',
    })

    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.OPEN_SETTINGS_PANEL, {
      actionType: CatToolConstants.OPEN_SETTINGS_PANEL,
      tab: 'general',
    })
  })

  test('GET_JOB_METADATA action stores metadata and emits the full payload', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.GET_JOB_METADATA,
      jobMetadata: {sid: 1},
    })

    expect(CatToolStore.getJobMetadata()).toEqual({sid: 1})
    expect(emitSpy).toHaveBeenCalledWith(CatToolConstants.GET_JOB_METADATA, {
      actionType: CatToolConstants.GET_JOB_METADATA,
      jobMetadata: {sid: 1},
    })
  })

  test('SEGMENT_FILTER_ERROR action emits the full action payload', () => {
    const emitSpy = jest.spyOn(CatToolStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: CatToolConstants.SEGMENT_FILTER_ERROR,
      message: 'error',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      CatToolConstants.SEGMENT_FILTER_ERROR,
      {
        actionType: CatToolConstants.SEGMENT_FILTER_ERROR,
        message: 'error',
      },
    )
  })
})
