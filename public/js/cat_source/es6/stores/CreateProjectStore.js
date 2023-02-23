/*
 * Projects Store
 */

import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import NewProjectConstants from '../constants/NewProjectConstants'
import assign from 'object-assign'

EventEmitter.prototype.setMaxListeners(0)

let CreateProjectStore = assign({}, EventEmitter.prototype, {
  projectData: {
    sourceLang: undefined,
    targetLang: undefined,
    selectedTeam: undefined,
  },
  updateProject: function (data) {
    this.projectData = {
      ...this.projectData,
      ...data,
    }
  },
  emitChange: function () {
    this.emit.apply(this, arguments)
  },
  getSourceLang: function () {
    return this.projectData.sourceLang
      ? this.projectData.sourceLang.id
      : undefined
  },
  getTargetLangs: function () {
    return this.projectData.targetLangs
      ? this.projectData.targetLangs.map((item) => item.id).join()
      : undefined
  },
  getSourceLangName: function () {
    return this.projectData.sourceLang
      ? this.projectData.sourceLang.name
      : undefined
  },
  getTargetLangsNames: function () {
    return this.projectData.targetLangs
      ? this.projectData.targetLangs.map((item) => item.name).join()
      : undefined
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case NewProjectConstants.UPDATE_PROJECT_DATA:
      CreateProjectStore.updateProject(action.data)
      CreateProjectStore.emitChange(
        action.actionType,
        CreateProjectStore.projectData,
      )
      break
    case NewProjectConstants.HIDE_ERROR_WARNING:
      CreateProjectStore.emitChange(action.actionType)
      break
    case NewProjectConstants.SHOW_ERROR:
    case NewProjectConstants.SHOW_WARNING:
      CreateProjectStore.updateProject(action.data)
      CreateProjectStore.emitChange(action.actionType, action.message)
      break
  }
})
module.exports = CreateProjectStore
