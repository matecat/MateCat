import React, {useContext, useMemo} from 'react'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import Alternatives from '../../../icons/Alternatives'
import {getSelectedTextWithTags} from '../../utils/DraftMatecatUtils/getSelectedTextWithTags'
import PropTypes from 'prop-types'

export const AiAlternatives = ({sid, editArea, isIconsBundled}) => {
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
    CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  const isDisabled =
    !selectedText || !editArea?.editAreaRef.contains(document.activeElement)

  return (
    !config.isReview && (
      <Button
        className={`last-ai-feature-button ${isIconsBundled ? 'segment-target-toolbar-icon-bundled' : ''}`}
        size={isIconsBundled ? BUTTON_SIZE.SMALL : BUTTON_SIZE.ICON_SMALL}
        mode={isIconsBundled ? BUTTON_MODE.GHOST : BUTTON_MODE.OUTLINE}
        title={
          isDisabled
            ? 'Alternative translations by Lara - Highlight parts of the target text to enable'
            : 'Alternative translations by Lara'
        }
        onClick={openTab}
        disabled={isDisabled}
      >
        <Alternatives size={isIconsBundled ? 18 : 16} />
        {isIconsBundled && 'Ai alternatives'}
      </Button>
    )
  )
}

AiAlternatives.propTypes = {
  sid: PropTypes.string.isRequired,
  editArea: PropTypes.object.isRequired,
  isIconsBundled: PropTypes.bool,
}
