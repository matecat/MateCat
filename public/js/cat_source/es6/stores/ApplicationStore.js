import assign from 'object-assign'
import {EventEmitter} from 'events'

import AppDispatcher from './AppDispatcher'
import ApplicationConstants from '../constants/ApplicationConstants'

EventEmitter.prototype.setMaxListeners(0)

let ApplicationStore = assign({}, EventEmitter.prototype, {
  languages: [],
  setLanguages: function (languages) {
    this.languages = languages
  },
  getLanguages: function () {
    return this.languages
  },
  getLanguageNameFromLocale: function (code) {
    try {
      return this.languages.find((e) => e.code === code).name
    } catch (e) {
      //console.error('Unknown Language', e)
      return ''
    }
  },
  emitChange: function () {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case ApplicationConstants.SET_LANGUAGES:
      ApplicationStore.setLanguages(action.languages)
      ApplicationStore.emitChange(
        ApplicationConstants.SET_LANGUAGES,
        action.languages,
      )
      break
  }
})

export default ApplicationStore
