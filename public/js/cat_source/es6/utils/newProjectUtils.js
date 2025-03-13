import Cookies from 'js-cookie'
import CreateProjectActions from '../actions/CreateProjectActions'
import {projectCreationStatus} from '../api/projectCreationStatus'
import ModalsActions from '../actions/ModalsActions'
import AlertModal from '../components/modals/AlertModal'
import CommonUtils from './commonUtils'
import UserStore from '../stores/UserStore'

export const checkGDriveEvents = () => {
  const cookieFilesGdrive = 'gdrive_files_to_be_listed'
  const cookieGdriveResponse = 'gdrive_files_outcome'
  const cookie = Cookies.get(cookieGdriveResponse)
  if (cookie) {
    const gdriveResponse = JSON.parse(Cookies.get(cookieGdriveResponse))
    if (gdriveResponse.success) {
      APP.tryListGDriveFiles()
    } else if (gdriveResponse.error_msg) {
      CreateProjectActions.showError(gdriveResponse.error_msg)
    }
    Cookies.remove(cookieFilesGdrive, {
      path: '',
      domain: '.' + location.hostname,
    })
    Cookies.remove(cookieGdriveResponse, {
      path: '',
      domain: '.' + location.hostname,
    })
  }
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
