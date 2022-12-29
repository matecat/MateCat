import AppDispatcher from '../stores/AppDispatcher'
import NewProjectConstants from '../constants/NewProjectConstants'

const CreateProjectActions = {
  updateProjectParams: function (data) {
    AppDispatcher.dispatch({
      actionType: NewProjectConstants.UPDATE_PROJECT_DATA,
      data,
    })
  },
}

export default CreateProjectActions
