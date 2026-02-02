import React, {useContext} from 'react'
import {LARA_STYLES} from '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions'
import {BUTTON_MODE} from '../../../common/Button/Button'
import Palette from '../../../icons/Palette'
import SegmentActions from '../../../../actions/SegmentActions'
import {SegmentContext} from '../../SegmentContext'
import {DropdownMenu} from '../../../common/DropdownMenu/DropdownMenu'
import CommonUtils from '../../../../utils/commonUtils'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'

export const LaraStyles = ({sid}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)
  const {multiMatchLangs, segImmutable} = useContext(SegmentContext)

  const isDisabled = typeof segImmutable.get('contributions') === 'undefined'

  const options = [
    {
      id: LARA_STYLES.FAITHFUL,
      label: 'Faithful',
      description:
        'Precise translation, maintaining original structure and meaning accurately.',
    },
    {
      id: LARA_STYLES.FLUID,
      label: 'Fluid',
      description:
        'Smooth translation, emphasizing readabiity and natural language flow. For general content.',
    },
    {
      id: LARA_STYLES.CREATIVE,
      label: 'Creative',
      description:
        'Imaginative translation, capturing essence with vivid and engaging language. For marketing, literature, etc.',
    },
  ]

  const setStyle = (style) => {
    SegmentActions.setLaraStyle({sid, style})
    SegmentActions.setSegmentContributions(sid)
    SegmentActions.getContribution(sid, multiMatchLangs)
    SegmentActions.setLaraStyle({sid, style: undefined}) // reset lara style from SegmentStore

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
    <DropdownMenu
      dropdownClassName="lara-styles-dropdown"
      toggleButtonProps={{
        title: 'Lara style',
        className: 'segment-target-toolbar-icon',
        mode: BUTTON_MODE.OUTLINE,
        children: <Palette size={16} />,
        disabled: isDisabled,
      }}
      items={options.map((option) => {
        return {
          label: (
            <div className="lara-styles-dropdown-item">
              <span>{option.label}</span>
              <p>{option.description}</p>
            </div>
          ),
          onClick: () => setStyle(option.id),
        }
      })}
    />
  )
}
