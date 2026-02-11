import React, {useContext, useMemo} from 'react'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import Alternatives from '../../../icons/Alternatives'
import {getSelectedTextWithTags} from '../../utils/DraftMatecatUtils/getSelectedTextWithTags'

export const AiAlternatives = ({sid, editArea}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const selectedText = useMemo(() => {
    return editArea?.state?.editorState
      ? getSelectedTextWithTags(editArea.state.editorState).reduce(
          (acc, {value}) => `${acc}${value}`,
          '',
        )
      : ''
  }, [editArea?.state?.editorState])

  const openTab = () => {
    SegmentActions.aiAlternativeTab({
      sid,
      text: selectedText,
    })

    //Track Event
    const message = {
      user: userInfo.user.uid,
      jobId: config.id_job,
      segmentId: sid,
      selectedText,
    }
    // CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  const isDisabled =
    !selectedText || !editArea?.editAreaRef.contains(document.activeElement)

  return (
    !config.isReview && (
      <Button
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        title="Ai alternatives"
        onClick={openTab}
        disabled={isDisabled}
      >
        <Alternatives size={16} />
      </Button>
    )
  )
}
