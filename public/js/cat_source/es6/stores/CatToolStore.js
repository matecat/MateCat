import assign from 'object-assign'
import {EventEmitter} from 'events'
import {filter} from 'lodash'

import AppDispatcher from './AppDispatcher'
import CatToolConstants from '../constants/CatToolConstants'
import ModalsConstants from '../constants/ModalsConstants'
import CatToolActions from '../actions/CatToolActions'

EventEmitter.prototype.setMaxListeners(0)

let CatToolStore = assign({}, EventEmitter.prototype, {
  files: null,
  qr: null,
  languages: [],
  searchResults: {
    searchResults: [], // Array
    occurrencesList: [],
    searchResultsDictionary: {},
    featuredSearchResult: 0,
  },
  clientConnected: false,
  clientId: undefined,
  tmKeys: null,
  keysDomains: null,
  haveKeysGlossary: undefined,
  jobMetadata: undefined,
  _projectProgress: undefined,
  storeFilesInfo: function (files) {
    this.files = files
  },
  getJobFilesInfo: function () {
    return this.files
  },
  setProgress: function (stats) {
    stats.translationCompleted = stats.raw.draft === 0 && stats.raw.new === 0
    stats.revisionCompleted =
      stats.raw.translated === 0 && stats.translationCompleted
    stats.revision1Completed = stats.raw.approved === stats.raw.total
    stats.revision2Completed = stats.raw.approved2 === stats.raw.total
    stats.translate_todo = Math.round(
      stats.equivalent.draft + stats.equivalent.new,
    )
    stats.revise_todo = Math.round(
      stats.translate_todo + stats.equivalent.translated,
    )
    stats.revise2_todo = Math.round(
      stats.translate_todo +
        stats.equivalent.translated +
        stats.equivalent.approved,
    )

    stats.translate_todo_total = Math.round(stats.raw.draft + stats.raw.new)
    stats.revise_todo_total = Math.round(
      stats.translate_todo_total + stats.raw.translated,
    )
    stats.revise2_todo_total = Math.round(
      stats.translate_todo_total + stats.raw.translated + stats.raw.approved,
    )

    this._projectProgress = stats
  },
  updateQR: function (qr) {
    this.qr = qr
  },
  getQR: function (revisionNumber) {
    if (this.qr) {
      return filter(
        this.qr.chunk.reviews,
        (rev) => rev.revision_number === revisionNumber,
      )
    }
    return null
  },
  storeSearchResult: function (data) {
    this.searchResults = data
  },
  clientConnect: function (clientId) {
    this.clientConnected = !!clientId
    //TODO: remove
    config.id_client
    this.clientId = clientId
  },
  updateJobTmKeys: function (keys) {
    this.tmKeys = keys.map((key) => ({
      ...key,
      name: key.name ? key.name : `No name (${key.key})`,
      isMissingName: !key.name,
    }))
  },
  getJobTmKeys: function () {
    return this.tmKeys
  },
  isClientConnected: function () {
    return this.clientConnected
  },
  getClientId: function () {
    return this.clientId
  },
  updateKeysDomains: function (domains) {
    this.keysDomains = domains
  },
  getKeysDomains: function () {
    return this.keysDomains
  },
  getHaveKeysGlossary: function () {
    return this.haveKeysGlossary
  },
  getProgress: () => {
    return CatToolStore._projectProgress
  },
  setHaveKeysGlossary: function (value) {
    this.haveKeysGlossary = value
  },
  setLanguages: function (languages) {
    this.languages = languages
  },
  getLanguages: function () {
    return this.languages
  },
  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case CatToolConstants.SHOW_CONTAINER:
      CatToolStore.emitChange(CatToolConstants.SHOW_CONTAINER, action.container)
      break
    case CatToolConstants.CLOSE_SUBHEADER:
      CatToolStore.emitChange(CatToolConstants.CLOSE_SUBHEADER)
      break
    case CatToolConstants.CLOSE_SEARCH:
      CatToolStore.emitChange(CatToolConstants.CLOSE_SEARCH)
      break
    case CatToolConstants.TOGGLE_CONTAINER:
      CatToolStore.emitChange(
        CatToolConstants.TOGGLE_CONTAINER,
        action.container,
      )
      break
    case CatToolConstants.SET_SEGMENT_FILTER:
      CatToolStore.emitChange(
        CatToolConstants.SET_SEGMENT_FILTER,
        action.data,
        action.state,
      )
      break
    case CatToolConstants.RELOAD_SEGMENT_FILTER:
      CatToolStore.emitChange(CatToolConstants.RELOAD_SEGMENT_FILTER)
      break
    case CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP:
      CatToolStore.emitChange(CatToolConstants.SHOW_PROFILE_MESSAGE_TOOLTIP)
      break
    case CatToolConstants.STORE_FILES_INFO:
      CatToolStore.storeFilesInfo(action.files)
      CatToolStore.emitChange(CatToolConstants.STORE_FILES_INFO, action.files)
      break
    case CatToolConstants.SET_PROGRESS:
      CatToolStore.setProgress(action.stats)
      CatToolStore.emitChange(
        CatToolConstants.SET_PROGRESS,
        CatToolStore._projectProgress,
      )
      break
    case CatToolConstants.STORE_SEARCH_RESULT:
      CatToolStore.storeSearchResult(action.data)
      CatToolStore.emitChange(CatToolConstants.STORE_SEARCH_RESULT, action.data)
      break
    case CatToolConstants.UPDATE_QR:
      CatToolStore.updateQR(action.qr)
      CatToolStore.emitChange(
        action.actionType,
        action.is_pass,
        action.score,
        action.feedback,
      )
      break
    case CatToolConstants.RELOAD_QR:
      CatToolStore.emitChange(CatToolConstants.RELOAD_QR)
      break
    case CatToolConstants.CLIENT_CONNECT:
      CatToolStore.clientConnect(action.clientId)
      CatToolStore.emitChange(CatToolConstants.CLIENT_CONNECT, action.clientId)
      break
    case CatToolConstants.CLIENT_RECONNECTION:
      CatToolStore.emitChange(CatToolConstants.CLIENT_RECONNECTION)
      break
    case CatToolConstants.ADD_NOTIFICATION:
      CatToolStore.emitChange(
        CatToolConstants.ADD_NOTIFICATION,
        action.notification,
      )
      break
    case CatToolConstants.REMOVE_NOTIFICATION:
      CatToolStore.emitChange(
        CatToolConstants.REMOVE_NOTIFICATION,
        action.notification,
      )
      break
    case CatToolConstants.REMOVE_ALL_NOTIFICATION:
      CatToolStore.emitChange(CatToolConstants.REMOVE_ALL_NOTIFICATION)
      break
    case ModalsConstants.SHOW_MODAL: {
      const {
        component,
        props,
        title,
        style,
        onCloseCallback,
        isCloseButtonDisabled,
      } = action
      CatToolStore.emitChange(
        ModalsConstants.SHOW_MODAL,
        component,
        props,
        title,
        style,
        onCloseCallback,
        isCloseButtonDisabled,
      )
      break
    }
    case ModalsConstants.CLOSE_MODAL: {
      CatToolStore.emitChange(ModalsConstants.CLOSE_MODAL)
      break
    }
    case CatToolConstants.UPDATE_TM_KEYS: {
      CatToolStore.updateJobTmKeys(action.keys)
      CatToolStore.emitChange(
        CatToolConstants.UPDATE_TM_KEYS,
        CatToolStore.tmKeys,
      )
      break
    }
    case CatToolConstants.UPDATE_DOMAINS:
      CatToolStore.updateKeysDomains(action.entries)
      CatToolStore.emitChange(CatToolConstants.UPDATE_DOMAINS, {
        sid: action.sid,
        entries: action.entries,
      })
      break
    case CatToolConstants.ON_RENDER:
      CatToolStore.emitChange(CatToolConstants.ON_RENDER, {
        ...action,
      })
      break
    case CatToolConstants.ON_TM_KEYS_CHANGE_STATUS:
      CatToolActions.retrieveJobKeys(true)
      CatToolStore.emitChange(CatToolConstants.ON_TM_KEYS_CHANGE_STATUS, {
        ...action,
      })
      break
    case CatToolConstants.HAVE_KEYS_GLOSSARY:
      CatToolStore.setHaveKeysGlossary(action.value)
      CatToolStore.emitChange(CatToolConstants.HAVE_KEYS_GLOSSARY, {
        value: CatToolStore.haveKeysGlossary,
        wasAlreadyVerified: action.wasAlreadyVerified,
      })
      break
    case CatToolConstants.OPEN_SETTINGS_PANEL:
      CatToolStore.emitChange(CatToolConstants.OPEN_SETTINGS_PANEL, {
        ...action,
      })
      break
    case CatToolConstants.GET_JOB_METADATA:
      CatToolStore.emitChange(CatToolConstants.GET_JOB_METADATA, {
        ...action,
      })
      break
  }
})

export default CatToolStore
