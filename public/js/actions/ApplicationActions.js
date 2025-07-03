import AppDispatcher from '../stores/AppDispatcher'
import ApplicationConstants from '../constants/ApplicationConstants'

const ApplicationActions = {
  setLanguages: (languages) => {
    AppDispatcher.dispatch({
      actionType: ApplicationConstants.SET_LANGUAGES,
      languages,
    })
  },
}
export default ApplicationActions
