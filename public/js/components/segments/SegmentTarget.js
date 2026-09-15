import React, {
  forwardRef,
  useContext,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react'
import $ from 'jquery'
import {isEmpty, isUndefined} from 'lodash'

import EditArea from './Editarea'
import CursorUtils from '../../utils/cursorUtils'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentStore from '../../stores/SegmentStore'
import SegmentButtons from './SegmentButtons'
import SegmentWarnings from './SegmentWarnings'
import SegmentActions from '../../actions/SegmentActions'
import {SegmentContext} from './SegmentContext'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {
  removeTagsFromText as removeTagsFromTextUtil,
  textHasTags,
} from './utils/DraftMatecatUtils/tagUtils'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../common/Button/Button'
import ReviseLockIcon from '../../../img/icons/ReviseLockIcon'
import OfflineUtils from '../../utils/offlineUtils'
import SegmentUtils from '../../utils/segmentUtils'
import CatToolStore from '../../stores/CatToolStore'
import {SegmentTargetToolbar} from './SegmentTargetToolbar'

const SegmentTarget = forwardRef((props, ref) => {
  const context = useContext(SegmentContext)

  const [showFormatMenu, setShowFormatMenu] = useState(false)
  const [charactersCounter, setCharactersCounter] = useState(0)
  const [segmentCharacters, setSegmentCharacters] = useState(0)
  const [charactersCounterLimit, setCharactersCounterLimit] =
    useState(undefined)

  const issuesHighlightAreaRef = useRef(null)
  const editAreaRef = useRef(null)
  const targetRef = useRef(null)
  const tmOutShowFormatMenuRef = useRef()

  // Always holds the CURRENT props read live inside stable callbacks, mirroring
  // `this.props` never being a stale closure inside updateCounter/autoFillTagsInTarget,
  // which are registered once (by reference) and must keep a stable identity across
  // renders while still reading the CURRENT segment prop.
  const liveRef = useRef({segment: props.segment})
  liveRef.current.segment = props.segment

  // Created once via useRef so its identity never changes across renders — required
  // because updateCounter is passed down as a long-lived prop to <EditArea> and is also
  // invoked directly through the imperative ref bridge, exactly like the class field's
  // single stable per-instance identity.
  const updateCounterRef = useRef((value) => {
    const {segmentCharacters, unitCharacters} =
      SegmentUtils.getRelativeTransUnitCharactersCounter({
        sid: liveRef.current.segment.sid,
        charactersCounter: value,
        shouldCountTagsAsChars:
          CatToolStore.getCurrentProjectTemplate().characterCounterCountTags,
      })

    setCharactersCounter(unitCharacters)
    setSegmentCharacters(segmentCharacters)
  })
  const updateCounter = updateCounterRef.current

  // Created once via useRef for the same stable-identity reason as updateCounter — it is
  // passed down as a long-lived prop to <EditArea> and invoked through the ref bridge.
  const toggleFormatMenuRef = useRef((show) => {
    // Show/Hide Edit Toolbar
    clearTimeout(tmOutShowFormatMenuRef.current)

    if (!show) {
      tmOutShowFormatMenuRef.current = setTimeout(() => {
        setShowFormatMenu(false)
      }, 200)
    } else {
      setShowFormatMenu(show)
    }
  })
  const toggleFormatMenu = toggleFormatMenuRef.current

  // Created once via useRef so it stays in sync (by reference) with
  // SegmentStore.addListener/removeListener, exactly like the class's single
  // this.autoFillTagsInTarget.bind(this) in the constructor. The setTimeout callback
  // re-reads liveRef.current.segment at fire time (not a locally captured variable) so
  // it always reads the freshest props 100ms later, matching `this.props.segment` being
  // dereferenced live off the instance in the original class.
  const autoFillTagsInTargetRef = useRef((sid) => {
    const {segment} = liveRef.current
    if (isUndefined(sid) || sid === segment.sid) {
      const newTranslation = DraftMatecatUtils.autoFillTagsInTarget(segment)
      //lock tags and run again getWarnings
      setTimeout(() => {
        SegmentActions.replaceEditAreaTextContent(
          liveRef.current.segment.sid,
          newTranslation,
        )
        SegmentActions.getSegmentsQa(liveRef.current.segment)
      }, 100)
      // TODO: Change code with this (?)
      // editAreaRef.current.addMissingSourceTagsToTarget()
    }
  })
  const autoFillTagsInTarget = autoFillTagsInTargetRef.current

  const selectIssueText = (event) => {
    let selection = document.getSelection()
    const container = $(issuesHighlightAreaRef.current).find(
      '.errorTaggingArea',
    )
    if (textSelectedInsideSelectionArea(selection, container)) {
      event.preventDefault()
      event.stopPropagation()
      selection = CursorUtils.getSelectionData(selection, container)
      SegmentActions.openIssuesPanel(
        {sid: props.segment.sid, selection: selection},
        true,
      )
      setTimeout(() => {
        SegmentActions.showIssuesMessage(props.segment.sid, 2)
      })
    } else {
      context.removeSelection()
      setTimeout(() => {
        SegmentActions.showIssuesMessage(props.segment.sid, 0)
      })
    }
  }

  const textSelectedInsideSelectionArea = (selection, container) => {
    return (
      container.contents().text().indexOf(selection.focusNode.textContent) >=
        0 &&
      container.contents().text().indexOf(selection.anchorNode.textContent) >=
        0 &&
      selection.toString().length > 0
    )
  }

  const lockEditArea = (event) => {
    event.preventDefault()
    if (!props.segment.edit_area_locked) {
      SegmentActions.showIssuesMessage(props.segment.sid, 0)
    }
    SegmentActions.lockEditArea(props.segment.sid, props.segment.fid)
  }

  const allowHTML = (string) => {
    return {__html: string}
  }

  const getAllIssues = () => {
    let issues = []
    if (props.segment.versions) {
      props.segment.versions.forEach(function (version) {
        if (!isEmpty(version.issues)) {
          issues = issues.concat(version.issues)
        }
      })
    }
    return issues
  }

  const removeTagsFromText = () => {
    const cleanText = removeTagsFromTextUtil(props.segment.translation)
    SegmentActions.replaceEditAreaTextContent(props.segment.sid, cleanText)
  }

  const getTargetArea = (translation) => {
    const {segment} = context

    const buttonsDisabled =
      !translation ||
      translation.trim().length === 0 ||
      OfflineUtils.offlineCacheRemaining <= 0

    var textAreaContainer = ''
    let issues = getAllIssues()
    if (props.segment.edit_area_locked) {
      const text =
        props.segment.versions && props.segment.versions[0].translation
          ? props.segment.versions[0].translation
          : translation
      let currentTranslationVersion = DraftMatecatUtils.transformTagsToHtml(
        text,
        config.isTargetRTL,
      )
      textAreaContainer = (
        <div
          className="segment-text-area-container"
          data-mount="segment_text_area_container"
        >
          <div
            className="textarea-container"
            onMouseUp={selectIssueText}
            ref={(div) => (issuesHighlightAreaRef.current = div)}
          >
            <div
              className="targetarea issuesHighlightArea errorTaggingArea"
              dangerouslySetInnerHTML={allowHTML(currentTranslationVersion)}
            />
          </div>
          <div className="segment-actions-container">
            <div className="segment-target-toolbar">
              {config.isReview ? (
                <Button
                  size={BUTTON_SIZE.ICON_SMALL}
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={lockEditArea}
                  title="Highlight text and assign an issue to the selected text."
                  className="revise-lock-editArea-active"
                >
                  <ReviseLockIcon />
                </Button>
              ) : null}
            </div>
            <SegmentButtons disabled={buttonsDisabled} {...context} />
          </div>
        </div>
      )
    } else {
      let s2tMicro = ''

      //Speeche2Text
      var s2t_enabled = context.speech2textEnabledFn()
      if (s2t_enabled) {
        s2tMicro = (
          <div
            className="micSpeech"
            title="Activate voice input"
            data-segment-id="{{originalId}}"
          >
            <div className="micBg"></div>
            <div className="micBg2">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                version="1.1"
                width="20"
                height="20"
                viewBox="0 0 20 20"
              >
                <g
                  className="svgMic"
                  transform="matrix(0.05555509,0,0,0.05555509,-3.1790007,-3.1109739)"
                  fill="#737373"
                >
                  <path d="m 290.991,240.991 c 0,26.392 -21.602,47.999 -48.002,47.999 l -11.529,0 c -26.4,0 -48.002,-21.607 -48.002,-47.999 l 0,-136.989 c 0,-26.4 21.602,-48.004 48.002,-48.004 l 11.529,0 c 26.4,0 48.002,21.604 48.002,48.004 l 0,136.989 z" />
                  <path d="m 342.381,209.85 -8.961,0 c -4.932,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,50.26 -37.109,91.001 -87.361,91.001 -50.26,0 -87.109,-40.741 -87.109,-91.001 l 0,-8.008 c 0,-4.927 -4.029,-8.961 -8.961,-8.961 l -8.961,0 c -4.924,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,58.862 40.229,107.625 96.07,116.362 l 0,36.966 -34.412,0 c -4.932,0 -8.961,4.039 -8.961,8.971 l 0,17.922 c 0,4.923 4.029,8.961 8.961,8.961 l 104.688,0 c 4.926,0 8.961,-4.038 8.961,-8.961 l 0,-17.922 c 0,-4.932 -4.035,-8.971 -8.961,-8.971 l -34.43,0 0,-36.966 c 55.889,-8.729 96.32,-57.5 96.32,-116.362 l 0,-8.008 c 0,-4.927 -4.039,-8.961 -8.961,-8.961 z" />
                </g>
              </svg>
            </div>
          </div>
        )
      }

      const qrLink =
        '/revise-summary/' +
        config.id_job +
        '-' +
        config.password +
        '?revision_type=' +
        (config.revisionNumber ? config.revisionNumber : 1) +
        '&id_segment=' +
        props.segment.sid

      //Text Area
      textAreaContainer = (
        <div className="textarea-container">
          <EditArea
            ref={(ref) => (editAreaRef.current = ref)}
            segment={props.segment}
            translation={translation}
            toggleFormatMenu={toggleFormatMenu}
            updateCounter={updateCounter}
          />
          {s2tMicro}
          <div className="segment-actions-container">
            <SegmentTargetToolbar
              {...{
                sid: props.segment.sid,
                segment: props.segment,
                editArea: editAreaRef.current,
                lockEditArea: lockEditArea,
                qrLink,
                issuesLength: issues.length,
                showFormatMenu,
                textHasTags: Boolean(textHasTags(translation)),
                removeTagsFromText: removeTagsFromText,
                missingTagsInTarget: segment.missingTagsInTarget,
                addMissingSourceTagsToTarget:
                  editAreaRef.current?.addMissingSourceTagsToTarget,
              }}
            />
            <SegmentButtons disabled={buttonsDisabled} {...context} />
          </div>
        </div>
      )
    }
    return textAreaContainer
  }

  useEffect(() => {
    SegmentStore.addListener(
      SegmentConstants.FILL_TAGS_IN_TARGET,
      autoFillTagsInTarget,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.FILL_TAGS_IN_TARGET,
        autoFillTagsInTarget,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const isFirstRenderRef = useRef(true)
  const prevValuesRef = useRef({
    charactersCounterLimit: undefined,
    charactersCounter: 0,
    segmentCharacters: 0,
  })

  // No dependency array — runs after every render (except the first, skipped via
  // isFirstRenderRef), matching componentDidUpdate's unconditional per-update re-sync.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (isFirstRenderRef.current) {
      isFirstRenderRef.current = false
      return
    }

    const prevValues = prevValuesRef.current

    const newCharactersCounterLimit = props.segment.metadata.find(
      (meta) =>
        meta.meta_key === 'sizeRestriction' &&
        meta.id_segment.toString() === props.segment.sid,
    )?.meta_value

    if (
      newCharactersCounterLimit &&
      newCharactersCounterLimit !== prevValues.charactersCounterLimit
    ) {
      setCharactersCounterLimit(newCharactersCounterLimit)
    }

    // dispatch characterCounter action
    if (
      charactersCounterLimit !== prevValues.charactersCounterLimit ||
      charactersCounter !== prevValues.charactersCounter ||
      segmentCharacters !== prevValues.segmentCharacters
    ) {
      setTimeout(() => {
        SegmentActions.characterCounter({
          sid: props.segment.sid,
          counter: charactersCounter,
          segmentCharacters: segmentCharacters,
          limit: charactersCounterLimit,
        })
      })
    }

    prevValuesRef.current = {
      charactersCounterLimit,
      charactersCounter,
      segmentCharacters,
    }
  })

  const instanceRef = useRef({})
  instanceRef.current.state = {
    showFormatMenu,
    charactersCounter,
    segmentCharacters,
    charactersCounterLimit,
  }
  instanceRef.current.autoFillTagsInTarget = autoFillTagsInTarget
  instanceRef.current.lockEditArea = lockEditArea
  instanceRef.current.removeTagsFromText = removeTagsFromText
  instanceRef.current.toggleFormatMenu = toggleFormatMenu
  instanceRef.current.updateCounter = updateCounter

  useImperativeHandle(ref, () => instanceRef.current)

  let translation = props.segment.translation

  return (
    <div
      className={`target item target-${config.target_code}`}
      id={'segment-' + props.segment.sid + '-target'}
      ref={(target) => (targetRef.current = target)}
    >
      {getTargetArea(translation)}
      <p className="warnings" />

      {props.segment.warnings ? (
        <SegmentWarnings warnings={props.segment.warnings} />
      ) : null}
    </div>
  )
})

SegmentTarget.displayName = 'SegmentTarget'

export default SegmentTarget
