import React, {useContext} from 'react'
import {LARA_STYLES_OPTIONS} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import Palette from '../../../icons/Palette'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentStore from '../../../../stores/SegmentStore'
import CommonUtils from '../../../../utils/commonUtils'
import PropTypes from 'prop-types'

export const LaraStyles = ({sid, isIconsBundled}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const segment = SegmentStore.getSegmentByIdToJS(sid)
  const contributions = segment?.contributions

  const openTab = () => {
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
        className={isIconsBundled ? 'segment-target-toolbar-icon-bundled' : ''}
        size={isIconsBundled ? BUTTON_SIZE.SMALL : BUTTON_SIZE.ICON_SMALL}
        mode={isIconsBundled ? BUTTON_MODE.GHOST : BUTTON_MODE.OUTLINE}
        title={
          isDisabled
            ? 'Lara styles - Available for unconfirmed segments only'
            : 'Lara styles - Click to see translations in different styles'
        }
        onClick={openTab}
        disabled={isDisabled}
      >
        <Palette size={isIconsBundled ? 18 : 16} />
        {isIconsBundled && 'Lara styles'}
      </Button>
    )
  )
}

LaraStyles.propTypes = {
  sid: PropTypes.string.isRequired,
  isIconsBundled: PropTypes.bool,
}
