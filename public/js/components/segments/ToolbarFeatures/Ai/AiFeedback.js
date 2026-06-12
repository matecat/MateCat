import React, {useContext} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import Feedback from '../../../icons/Feedback'

export const AiFeedback = ({sid, segment, isIconsBundled}) => {
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
    CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  const isDisabled =
    !segment.modified &&
    (segment.status === 'NEW' || segment.status === 'DRAFT')

  return (
    !config.isReview && (
      <Button
        className={isIconsBundled ? 'segment-target-toolbar-icon-bundled' : ''}
        size={isIconsBundled ? BUTTON_SIZE.SMALL : BUTTON_SIZE.ICON_SMALL}
        mode={isIconsBundled ? BUTTON_MODE.GHOST : BUTTON_MODE.OUTLINE}
        title={
          isDisabled
            ? 'Lara feedback - edit translation to enable'
            : 'Lara feedback'
        }
        onClick={openTab}
        disabled={isDisabled}
      >
        <Feedback size={isIconsBundled ? 18 : 16} />
        {isIconsBundled && 'Ai feedback'}
      </Button>
    )
  )
}

AiFeedback.propTypes = {
  sid: PropTypes.string.isRequired,
  isIconsBundled: PropTypes.bool,
}
