/*
 * AppDispatcher
 *
 * A singleton that operates as the central hub for application updates.
 */
import {Dispatcher} from 'flux'

const dispatcher = new Dispatcher()
let actionQueue = []
let isProcessing = false

function queueAction(payload) {
  actionQueue.push(payload)
  if (!isProcessing) {
    startProcessing()
  }
}

function startProcessing() {
  isProcessing = true
  while (actionQueue.length > 0) {
    if (dispatcher.isDispatching()) {
      return setTimeout(startProcessing, 100) // Be safe; Avoid an Invariant error from Flux
    }
    var payload = actionQueue.shift()
    dispatcher.dispatch(payload)
  }
  isProcessing = false
}

const AppDispatcher = {
  isProcessing() {
    return isProcessing
  },

  dispatch(payload) {
    queueAction(payload)
  },

  register(callback) {
    return dispatcher.register(callback)
  },
}

export default AppDispatcher
