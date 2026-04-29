import AppDispatcher from '../stores/AppDispatcher'
import CatToolConstants from '../constants/CatToolConstants'

export const addNotification = (notification) => {
  return AppDispatcher.dispatch({
    actionType: CatToolConstants.ADD_NOTIFICATION,
    notification,
  })
}

export const removeNotification = (notification) => {
  AppDispatcher.dispatch({
    actionType: CatToolConstants.REMOVE_NOTIFICATION,
    notification,
  })
}

export const removeAllNotifications = () => {
  AppDispatcher.dispatch({
    actionType: CatToolConstants.REMOVE_ALL_NOTIFICATION,
  })
}
