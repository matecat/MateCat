import {MODAL_KEY} from '../../constants/ModalKeys'
import ConfirmMessageModal from './ConfirmMessageModal'
import SplitJobModal from './SplitJob'
import {CreateTeam} from './CreateTeam'
import {ModifyTeam} from './ModifyTeam'
import PreferencesModal from './PreferencesModal'
import SuccessModal from './SuccessModal'
import OnBoarding from '../onBoarding/OnBoarding'
import {DownloadAlertModal} from './DownloadAlertModal'
import AlertModal from './AlertModal'
import RevisionFeedbackModal from './RevisionFeedbackModal'
import CopySourceModal from './CopySourceModal'
import {UnlockAllSegmentsModal} from './UnlockAllSegmentsModal'

const modalRegistry = {
  [MODAL_KEY.CONFIRM_MESSAGE]: ConfirmMessageModal,
  [MODAL_KEY.SPLIT_JOB]: SplitJobModal,
  [MODAL_KEY.CREATE_TEAM]: CreateTeam,
  [MODAL_KEY.MODIFY_TEAM]: ModifyTeam,
  [MODAL_KEY.PREFERENCES]: PreferencesModal,
  [MODAL_KEY.SUCCESS]: SuccessModal,
  [MODAL_KEY.ONBOARDING]: OnBoarding,
  [MODAL_KEY.DOWNLOAD_ALERT]: DownloadAlertModal,
  [MODAL_KEY.ALERT]: AlertModal,
  [MODAL_KEY.REVISION_FEEDBACK]: RevisionFeedbackModal,
  [MODAL_KEY.COPY_SOURCE]: CopySourceModal,
  [MODAL_KEY.UNLOCK_ALL_SEGMENTS]: UnlockAllSegmentsModal,
}

export const resolveModal = (componentOrKey) => {
  if (typeof componentOrKey === 'string') {
    const resolved = modalRegistry[componentOrKey]
    if (!resolved) {
      console.error(`Unknown modal key: "${componentOrKey}"`)
    }
    return resolved
  }
  return componentOrKey
}
