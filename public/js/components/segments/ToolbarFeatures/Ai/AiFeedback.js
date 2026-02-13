import React, {useContext} from 'react'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import Feedback from '../../../icons/Feedback'

export const AiFeedback = ({sid}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const openTab = () => {
    SegmentActions.aiFeedbackTab({
      sid,
    })

    //Track Event
    const message = {
      user: userInfo.user.uid,
      jobId: config.id_job,
      segmentId: sid,
    }
    // CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  return (
    !config.isReview && (
      <Button
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        title="Ai feedback"
        onClick={openTab}
      >
        <Feedback size={16} />
      </Button>
    )
  )
}
