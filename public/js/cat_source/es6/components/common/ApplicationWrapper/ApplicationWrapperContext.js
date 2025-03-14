import {createContext} from 'react'
// Custom event handler class: allows namespaced events
class EventHandlerClass {
  constructor() {
    this.functionMap = {}
  }

  addEventListener(event, func) {
    this.functionMap[event] = func
    document.addEventListener(event.split('.')[0], this.functionMap[event])
  }

  removeEventListener(event) {
    document.removeEventListener(event.split('.')[0], this.functionMap[event])
    delete this.functionMap[event]
  }
}
window.eventHandler = new EventHandlerClass()
export const ApplicationWrapperContext = createContext({})
