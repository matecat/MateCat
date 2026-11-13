import CreateProjectActions from '../actions/CreateProjectActions'
import {projectCreationStatus} from '../api/projectCreationStatus'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import CommonUtils from './commonUtils'
import UserStore from '../stores/UserStore'

const CREATE_PROJECT_GENERIC_ERROR =
  'Sorry, an error occurred while creating the project, please try again after refreshing the page.'

/**
 * The message shown when the project creation fails.
 *
 * The backend message is shown as it is, except for -235. Code -230 has two meanings (a file that could not be
 * stored, a file hash that could not be computed), so it is never given a text of its own here.
 *
 * @param {Array<{code: number, message?: string}>|undefined} errors
 * @returns {string}
 */
export const getCreateProjectErrorMessage = (errors) => {
  if (!errors?.length || errors[0].code === -235) {
    return CREATE_PROJECT_GENERIC_ERROR
  }

  return errors[0].message || CREATE_PROJECT_GENERIC_ERROR
}

export const handleCreationStatus = (id_project, password) => {
  projectCreationStatus(id_project, password)
    .then(({data, status}) => {
      if (data.status == 202 || status == 202) {
        setTimeout(handleCreationStatus, 1000, id_project, password)
      } else {
        postProjectCreation(data)
      }
    })
    .catch(({errors}) => {
      postProjectCreation({errors})
    })
}

const postProjectCreation = (data) => {
  if (typeof data.errors != 'undefined' && data.errors.length) {
    CreateProjectActions.hideErrors()
    CreateProjectActions.showError(data.errors[0].message)
  } else {
    //A project now are never EMPTY, it is not created anymore
    if (data.status === 'EMPTY') {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'No text to translate in the file(s).<br />Perhaps it is a scanned file or an image?',
          buttonText: 'Continue',
        },
        'No text to translate',
      )
    } else {
      const userInfo = UserStore.getUser()

      const dataEvent = {
        event: 'analyze_click',
        userStatus: 'loggedUser',
        userId: userInfo.user.uid,
        idProject: data.id_project,
      }
      CommonUtils.dispatchAnalyticsEvents(dataEvent)
      location.href = data.analyze_url
    }
  }
}
