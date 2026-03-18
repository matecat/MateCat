import React, {useState} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import ReviseLockIcon from '../../../img/icons/ReviseLockIcon'
import QualityReportIcon from '../../../img/icons/QualityReportIcon'
import {
  DROPDOWN_MENU_TRIGGER_MODE,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import UpperCaseIcon from '../../../img/icons/UpperCaseIcon'
import LowerCaseIcon from '../../../img/icons/LowerCaseIcon'
import CapitalizeIcon from '../../../img/icons/CapitalizeIcon'
import {Shortcuts} from '../../utils/shortcuts'
import RemoveTagsIcon from '../../../img/icons/RemoveTagsIcon'
import IconDown from '../icons/IconDown'
import {LaraStyles} from './ToolbarFeatures/Ai/LaraStyles'
import {UseHotKeysComponent} from '../../hooks/UseHotKeysComponent'
import AddTagsIcon from '../../../img/icons/AddTagsIcon'
import {AiAlternatives} from './ToolbarFeatures/Ai/AiAlternatives'
import {AiFeedback} from './ToolbarFeatures/Ai/AiFeedback'

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
  addMissingSourceTagsToTarget,
}) => {
  const [isIconsBundled, setIsIconsBundled] = useState(false)

  const getIconButton = (props) => {
    const {children, ...rest} = props

    return (
      <Button
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        {...rest}
      >
        {children}
      </Button>
    )
  }

  const items = [
    ...(config.active_engine?.engine_type === 'Lara'
      ? [
          {
            group: 0,
            component: <AiFeedback {...{key: 'aifeedback', sid}} />,
          },
          {
            group: 0,
            component: <LaraStyles {...{key: 'larastyle', sid}} />,
          },
          {
            group: 0,
            component: (
              <AiAlternatives {...{key: 'aialternatives', sid, editArea}} />
            ),
          },
        ]
      : []),
    ...(config.isReview
      ? [
          {
            component: getIconButton({
              key: 'reviselock',
              title: 'Highlight text and assign an issue to the selected text.',
              children: <ReviseLockIcon />,
              onClick: lockEditArea,
            }),
          },
        ]
      : []),
    ...(issuesLength > 0 || config.isReview
      ? [
          {
            component: getIconButton({
              key: 'segmentquality',
              title: 'Segment Quality Report.',
              children: <QualityReportIcon />,
              target: '_blank',
              onClick: () => window.open(qrLink, '_blank'),
            }),
          },
        ]
      : []),
    ...(textHasTags
      ? [
          {
            component: (
              <>
                <UseHotKeysComponent
                  shortcut={
                    Shortcuts.cattol.events.removeTags.keystrokes[
                      Shortcuts.shortCutsKeyType
                    ]
                  }
                  callback={removeTagsFromText}
                />
                {getIconButton({
                  key: 'removealltags',
                  title: `Remove all tags (${Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`,
                  children: <RemoveTagsIcon />,
                  onClick: removeTagsFromText,
                })}
              </>
            ),
          },
        ]
      : []),
    ...(missingTagsInTarget && missingTagsInTarget.length > 0 && editArea
      ? [
          {
            component: (
              <>
                <UseHotKeysComponent
                  shortcut={
                    Shortcuts.cattol.events.addTags.keystrokes[
                      Shortcuts.shortCutsKeyType
                    ]
                  }
                  callback={addMissingSourceTagsToTarget}
                />
                {getIconButton({
                  key: 'copymissingtags',
                  title: `Copy missing tags from source to target (${Shortcuts.cattol.events.addTags.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`,
                  children: <AddTagsIcon />,
                  onClick: addMissingSourceTagsToTarget,
                })}
              </>
            ),
          },
        ]
      : []),
    ...(showFormatMenu
      ? [
          {
            component: (
              <DropdownMenu
                key="formatmenu"
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
                        UPPERCASE
                      </>
                    ),
                    onClick: () => editArea.formatSelection('uppercase'),
                  },
                  {
                    label: (
                      <>
                        <LowerCaseIcon />
                        lowercase
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

  const buttons = items /* items.reduce((acc, cur, index, arr) => {
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
  }, []) */

  return (
    <div className="segment-target-toolbar">
      {buttons.map((button) => {
        if (button.dropdownGroup) return button.dropdownGroup
        return button.component
      })}
    </div>
  )
}

SegmentTargetToolbar.propTypes = {
  sid: PropTypes.string.isRequired,
  editArea: PropTypes.object,
  lockEditArea: PropTypes.func,
  qrLink: PropTypes.string,
  issuesLength: PropTypes.number,
  showFormatMenu: PropTypes.bool,
  textHasTags: PropTypes.bool,
  removeTagsFromText: PropTypes.func,
  missingTagsInTarget: PropTypes.array,
  addMissingSourceTagsToTarget: PropTypes.func,
}
