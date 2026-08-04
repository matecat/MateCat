import React, {createRef, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import ReviseLockIcon from '../../../img/icons/ReviseLockIcon'
import QualityReportIcon from '../../../img/icons/QualityReportIcon'
import {
  DROPDOWN_MENU_ITEM_TYPE,
  DROPDOWN_MENU_TRIGGER_MODE,
  DROPDOWN_SEPARATOR,
  DropdownMenu,
} from '../common/DropdownMenu/DropdownMenu'
import Tooltip from '../common/Tooltip'
import Switch from '../common/Switch'
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
import {hasCompressiblePhTags} from './utils/DraftMatecatUtils/pcTagUtils'
import CatToolStore from '../../stores/CatToolStore'
import CatToolActions from '../../actions/CatToolActions'
import CatToolConstants from '../../constants/CatToolConstants'

export const SegmentTargetToolbar = ({
  sid,
  segment,
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
  const [compressed, setCompressed] = useState(
    CatToolStore.isPhTagsCompressed(),
  )
  useEffect(() => {
    const handler = () => setCompressed(CatToolStore.isPhTagsCompressed())
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      handler,
    )
    return () =>
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
        handler,
      )
  }, [])

  const getIconButton = (props) => {
    const {children, key, ...rest} = props

    return (
      <Button
        key={key}
        className="segment-target-toolbar-icon"
        size={BUTTON_SIZE.ICON_SMALL}
        mode={BUTTON_MODE.OUTLINE}
        {...rest}
      >
        {children}
      </Button>
    )
  }

  const removeTagsShortcut =
    Shortcuts.cattol.events.removeTags.keystrokes[Shortcuts.shortCutsKeyType]
  const addTagsShortcut =
    Shortcuts.cattol.events.addTags.keystrokes[Shortcuts.shortCutsKeyType]

  const canRemoveTags = Boolean(textHasTags)
  const canCopyMissingTags = Boolean(
    missingTagsInTarget && missingTagsInTarget.length > 0 && editArea,
  )
  const canToggleTagsCompression = Boolean(
    hasCompressiblePhTags(segment?.segment) ||
    hasCompressiblePhTags(segment?.translation),
  )
  const tagMenuVisible =
    canRemoveTags || canCopyMissingTags || canToggleTagsCompression

  const buttons = [
    ...(config.active_engine?.engine_type === 'Lara'
      ? [
          {
            component: (
              <LaraStyles key="larastyle" sid={sid} segment={segment} />
            ),
          },
          {
            component: (
              <AiFeedback key="aifeedback" sid={sid} segment={segment} />
            ),
          },
          {
            component: (
              <AiAlternatives
                key="aialternatives"
                sid={sid}
                segment={segment}
                editArea={editArea}
              />
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
    ...(tagMenuVisible
      ? [
          {
            component: (
              <DropdownMenu
                key="tagmenu"
                toggleButtonProps={{
                  className: 'segment-target-toolbar-dropdown-trigger',
                  mode: BUTTON_MODE.OUTLINE,
                  size: BUTTON_SIZE.SMALL,
                  children: (
                    <>
                      <span>Tags</span>
                      <IconDown size={16} />
                    </>
                  ),
                }}
                items={[
                  {
                    label: (
                      <div className="segment-target-toolbar-menu-toggle-wrapper">
                        <Tooltip
                          content={
                            !canToggleTagsCompression
                              ? 'No expandable tags in this file'
                              : ''
                          }
                        >
                          <div
                            ref={createRef()}
                            className="segment-target-toolbar-menu-toggle"
                          >
                            Show full tags
                            <Switch
                              active={!compressed}
                              showText={false}
                              className="tags-switch"
                              tabIndex={-1}
                              testId="tags-compress-switch"
                            />
                          </div>
                        </Tooltip>
                      </div>
                    ),
                    onClick: () => CatToolActions.togglePhTagsCompressed(),
                    disabled: !canToggleTagsCompression,
                    selected: !compressed,
                    keepOpen: true,
                    testId: 'tags-menu-toggle-compression',
                  },
                  DROPDOWN_SEPARATOR,
                  {
                    label: (
                      <Tooltip
                        content={!canCopyMissingTags ? 'No tags to copy' : ''}
                      >
                        <div
                          ref={createRef()}
                          className="segment-target-toolbar-menu-item"
                        >
                          <AddTagsIcon />
                          Copy from source ({addTagsShortcut.toUpperCase()})
                        </div>
                      </Tooltip>
                    ),
                    onClick: addMissingSourceTagsToTarget,
                    disabled: !canCopyMissingTags,
                    testId: 'tags-menu-copy-from-source',
                  },
                  {
                    label: (
                      <Tooltip
                        content={!canRemoveTags ? 'No tags to remove' : ''}
                      >
                        <div
                          ref={createRef()}
                          className="segment-target-toolbar-menu-item"
                        >
                          <RemoveTagsIcon />
                          Remove all tags ({removeTagsShortcut.toUpperCase()})
                        </div>
                      </Tooltip>
                    ),
                    onClick: removeTagsFromText,
                    disabled: !canRemoveTags,
                    type: DROPDOWN_MENU_ITEM_TYPE.CRITICAL,
                    testId: 'tags-menu-remove-all',
                  },
                ]}
              />
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
                  size: BUTTON_SIZE.SMALL,
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

  return (
    <div className="segment-target-toolbar">
      {/*
        Shortcuts must be registered outside the dropdown: its content is
        mounted only while the menu is open, so hotkeys living inside the
        menu items would only work with the menu expanded.
      */}
      {canRemoveTags && removeTagsFromText && (
        <UseHotKeysComponent
          shortcut={removeTagsShortcut}
          callback={removeTagsFromText}
        />
      )}
      {canCopyMissingTags && addMissingSourceTagsToTarget && (
        <UseHotKeysComponent
          shortcut={addTagsShortcut}
          callback={addMissingSourceTagsToTarget}
        />
      )}
      {buttons.map((button, index) => (
        <React.Fragment key={`btn-${index}`}>{button.component}</React.Fragment>
      ))}
    </div>
  )
}

SegmentTargetToolbar.propTypes = {
  sid: PropTypes.string.isRequired,
  segment: PropTypes.object.isRequired,
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
