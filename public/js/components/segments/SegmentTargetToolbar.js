import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import ReviseLockIcon from '../../../img/icons/ReviseLockIcon'
import QualityReportIcon from '../../../img/icons/QualityReportIcon'
import {
  DROPDOWN_MENU_TRIGGER_MODE,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import DotsHorizontal from '../../../img/icons/DotsHorizontal'
import UpperCaseIcon from '../../../img/icons/UpperCaseIcon'
import LowerCaseIcon from '../../../img/icons/LowerCaseIcon'
import CapitalizeIcon from '../../../img/icons/CapitalizeIcon'
import {Shortcuts} from '../../utils/shortcuts'
import RemoveTagsIcon from '../../../img/icons/RemoveTagsIcon'
import IconDown from '../icons/IconDown'
import {LaraStyles} from './ToolbarFeatures/Lara/LaraStyles'

export const SegmentTargetToolbar = ({
  sid,
  editArea,
  lockEditArea,
  qrLink,
  issuesLength,
  showFormatMenu,
  textHasTags,
  removeTagsFromText,
  missingTagsInTarget,
}) => {
  const [isIconsBundled, setIsIconsBundled] = useState(false)

  const items = [
    ...(config.active_engine?.engine_type === 'Lara'
      ? [
          {
            group: 0,
            component: <LaraStyles {...{sid}} />,
          },
        ]
      : []),
    ...(config.isReview
      ? [
          {
            title: 'Highlight text and assign an issue to the selected text.',
            label: <ReviseLockIcon />,
            onClick: lockEditArea,
          },
        ]
      : []),
    ...(issuesLength > 0 || config.isReview
      ? [
          {
            title: 'Segment Quality Report.',
            label: <QualityReportIcon />,
            target: '_blank',
            onClick: () => window.open(qrLink, '_blank'),
          },
        ]
      : []),
    ...(textHasTags
      ? [
          {
            title: `Remove all tags (${Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`,
            label: <RemoveTagsIcon />,
            onClick: removeTagsFromText,
          },
        ]
      : []),
    /* ...(missingTagsInTarget &&
        missingTagsInTarget.length > 0 && editArea ? 
        [
          {
            title: `Remove all tags (${Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`,
            label: <RemoveTagsIcon />,
            onClick: removeTagsFromText,
          }
        ] : []), */
    ...(showFormatMenu
      ? [
          {
            component: (
              <DropdownMenu
                triggerMode={DROPDOWN_MENU_TRIGGER_MODE.HOVER}
                toggleButtonProps={{
                  className: 'segment-target-toolbar-dropdown-trigger',
                  mode: BUTTON_MODE.OUTLINE,
                  children: (
                    <>
                      Tt
                      <IconDown size={16} />
                    </>
                  ),
                }}
                items={[
                  {
                    label: (
                      <>
                        <UpperCaseIcon />
                        Uppercase
                      </>
                    ),
                    onClick: () => editArea.formatSelection('uppercase'),
                  },
                  {
                    label: (
                      <>
                        <LowerCaseIcon />
                        Lowercase
                      </>
                    ),
                    onClick: () => editArea.formatSelection('lowercase'),
                  },
                  {
                    label: (
                      <>
                        <CapitalizeIcon />
                        Capitalize
                      </>
                    ),
                    onClick: () => editArea.formatSelection('capitalize'),
                  },
                ]}
              />
            ),
          },
        ]
      : []),
  ]

  const buttons = items.reduce((acc, cur, index, arr) => {
    if (
      typeof cur.group === 'number' &&
      acc.find(
        (item) =>
          item.group === cur.group && typeof item.dropdownGroup !== 'undefined',
      )
    )
      return acc

    if (typeof cur.group === 'number' && isIconsBundled && !cur.dropdownGroup) {
      const groups = arr.filter(({group}) => group === cur.group)
      return [
        ...acc,
        {
          group: cur.group,
          dropdownGroup: (
            <DropdownMenu
              toggleButtonProps={{
                children: <DotsHorizontal size={18} />,
              }}
              items={groups.map(({label, onClick}) => ({
                label,
                onClick,
              }))}
            />
          ),
        },
      ]
    } else {
      return [...acc, cur]
    }
  }, [])

  return (
    <div className="segment-target-toolbar">
      {buttons.map((button, index) => {
        if (button.dropdownGroup) return button.dropdownGroup
        if (button.component) return button.component

        const {label, ...props} = button
        return (
          <Button
            key={index}
            className="segment-target-toolbar-icon"
            size={BUTTON_SIZE.ICON_SMALL}
            mode={BUTTON_MODE.OUTLINE}
            {...props}
          >
            {label}
          </Button>
        )
      })}
    </div>
  )
}

SegmentTargetToolbar.propTypes = {
  sid: PropTypes.string.isRequired,
  editArea: PropTypes.object.isRequired,
  lockEditArea: PropTypes.func,
  qrLink: PropTypes.string,
  issuesLength: PropTypes.number,
  showFormatMenu: PropTypes.bool,
  textHasTags: PropTypes.bool,
  removeTagsFromText: PropTypes.func,
  missingTagsInTarget: PropTypes.array,
}
