import React, {useContext} from 'react'
import {
  LARA_STYLES,
  LARA_STYLES_OPTIONS,
} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import Palette from '../../../icons/Palette'
import SegmentActions from '../../../../actions/SegmentActions'
import {DropdownMenu} from '../../../common/DropdownMenu/DropdownMenu'
import CommonUtils from '../../../../utils/commonUtils'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CatToolStore from '../../../../stores/CatToolStore'

export const LaraStyles = ({sid}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const openTabStyles = (style) => {
    const styles = LARA_STYLES_OPTIONS.filter(
      ({id}) => id !== CatToolStore.getJobMetadata().project.lara_style,
    )

    SegmentActions.laraStylesTab({
      sid,
      styles,
    })

    console.log(
      styles.reduce(
        (acc, cur, index) => `${acc}${index > 0 ? ',' : ''}${cur.name}`,
        '',
      ),
    )
    return
    //Track Event
    const message = {
      user: userInfo.user.uid,
      jobId: config.id_job,
      segmentId: sid,
      style,
    }
    CommonUtils.dispatchTrackingEvents('LaraStyle', message)
  }

  return (
    <Button
      className="segment-target-toolbar-icon"
      size={BUTTON_SIZE.ICON_SMALL}
      mode={BUTTON_MODE.OUTLINE}
      title="Lara styles"
      onClick={openTabStyles}
    >
      <Palette size={16} />
    </Button>
  )
}
