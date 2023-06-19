import AppDispatcher from '../stores/AppDispatcher'
import NewProjectConstants from '../constants/NewProjectConstants'

const CreateProjectActions = {
  updateProjectParams: function (data) {
    AppDispatcher.dispatch({
      actionType: NewProjectConstants.UPDATE_PROJECT_DATA,
      data,
    })
  },
  hideErrors: function () {
    AppDispatcher.dispatch({
      actionType: NewProjectConstants.HIDE_ERROR_WARNING,
    })
  },
  showError: function (message) {
    AppDispatcher.dispatch({
      actionType: NewProjectConstants.SHOW_ERROR,
      message,
    })
  },
  createKeyFromTMXFile: function (message) {
    AppDispatcher.dispatch({
      actionType: NewProjectConstants.CREATE_KEY_FROM_TMX_FILE,
      message,
    })
  },
}

export default CreateProjectActions
