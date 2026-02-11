import React, {useContext} from 'react'
import {LARA_STYLES_OPTIONS} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import Palette from '../../../icons/Palette'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentStore from '../../../../stores/SegmentStore'
import CommonUtils from '../../../../utils/commonUtils'

export const LaraStyles = ({sid}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const segment = SegmentStore.getSegmentByIdToJS(sid)
  const contributions = segment?.contributions

  const openTabStyles = () => {
    const styles = LARA_STYLES_OPTIONS.map((style) =>
      style.id !== CatToolStore.getJobMetadata().project.mt_extra.lara_style
        ? style
        : {...style, isDefault: true},
    )

    SegmentActions.laraStylesTab({
      sid,
      styles,
    })

    //Track Event
    const message = {
      user: userInfo.user.uid,
      jobId: config.id_job,
      segmentId: sid,
      style: styles
        .filter(({isDefault}) => !isDefault)
        .reduce(
          (acc, cur, index) => `${acc}${index > 0 ? ',' : ''}${cur.name}`,
          '',
        ),
    }
    CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  const isDisabled =
    !contributions || (segment.status !== 'NEW' && segment.status !== 'DRAFT')

  return (
    !config.isReview && (
      <Button
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        title={
          isDisabled
            ? 'Lara styles - Available for unconfirmed segments only'
            : 'Lara styles - Click to see translations in different styles'
        }
        onClick={openTabStyles}
        disabled={isDisabled}
      >
        <Palette size={16} />
      </Button>
    )
  )
}
