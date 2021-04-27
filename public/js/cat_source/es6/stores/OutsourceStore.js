import AppDispatcher from './AppDispatcher'
import {EventEmitter} from 'events'
import OutsourceConstants from '../constants/OutsourceConstants'
import assign from 'object-assign'

EventEmitter.prototype.setMaxListeners(0)

let OutsourceStore = assign({}, EventEmitter.prototype, {
  emitChange: function (event, args) {
    this.emit.apply(this, arguments)
  },
})

// Register callback to handle all updates
AppDispatcher.register(function (action) {
  switch (action.actionType) {
    case OutsourceConstants.GET_OUTSOURCE_QUOTE:
      OutsourceStore.emitChange(action.actionType)
      break
    case OutsourceConstants.CLOSE_TRANSLATOR:
      OutsourceStore.emitChange(action.actionType)
      break
  }
})

module.exports = OutsourceStore
