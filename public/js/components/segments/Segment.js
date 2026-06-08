import {forEach, isUndefined} from 'lodash'
import React, {
  useState,
  useRef,
  useContext,
  useEffect,
  useCallback,
} from 'react'
import {union} from 'lodash/array'
import $ from 'jquery'
import SegmentCommentsContainer from './SegmentCommentsContainer'
import SegmentsCommentsIcon from './SegmentsCommentsIcon'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentHeader from './SegmentHeader'
import SegmentFooter from './SegmentFooter'
import ReviewExtendedPanel from '../review_extended/ReviewExtendedPanel'
import SegmentUtils from '../../utils/segmentUtils'
import SegmentFilter from '../header/cattol/segment_filter/segment_filter'
import SegmentFilterUtils from '../header/cattol/segment_filter/segment_filter'
import Speech2Text from '../../utils/speech2text'
import ConfirmMessageModal from '../modals/ConfirmMessageModal'
import SegmentBody from './SegmentBody'
import TranslationIssuesSideButton from '../review/TranslationIssuesSideButton'
import ModalsActions from '../../actions/ModalsActions'
import {SegmentContext} from './SegmentContext'
import CatToolConstants from '../../constants/CatToolConstants'
import CatToolStore from '../../stores/CatToolStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import CommentsStore from '../../stores/CommentsStore'
import {SEGMENTS_STATUS} from '../../constants/Constants'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import {Shortcuts} from '../../utils/shortcuts'
import SearchUtils from '../header/cattol/search/searchUtils'
import {SegmentQAIcon} from './SegmentQAIcon'

const SegmentComponent = ({
  segment,
  segImmutable,
  fid,
  isReview,
  guessTagActive,
  sideOpen,
  clientConnected,
  clientId,
  speechToTextActive,
  files,
  speech2textEnabledFn,
  multiMatchLangs,
  setBulkSelection,
  setLastSelectedSegment,
}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  // DOM refs
  const sectionRef = useRef(null)
  const $sectionRef = useRef(null)
  const timeoutScrollRef = useRef(null)

  // Value refs — keep latest values for stable store-listener callbacks
  const segmentRef = useRef(segment)
  segmentRef.current = segment
  const fidRef = useRef(fid)
  fidRef.current = fid
  const isReviewRef = useRef(isReview)
  isReviewRef.current = isReview
  const clientConnectedRef = useRef(clientConnected)
  clientConnectedRef.current = clientConnected
  const multiMatchLangsRef = useRef(multiMatchLangs)
  multiMatchLangsRef.current = multiMatchLangs

  // Derived values (recomputed each render)
  const secondPassLocked = SegmentUtils.isSecondPassLockedSegment(segment)
  const readonly = SegmentUtils.isReadonlySegment(segment)
  const tagProjectionEnabled =
    guessTagActive &&
    segment &&
    segment.status &&
    (segment.status.toLowerCase() === 'draft' ||
      segment.status.toLowerCase() === 'new') &&
    !DraftMatecatUtils.checkXliffTagsInText(segment.translation) &&
    DraftMatecatUtils.removeTagsFromText(segment.segment) !== ''
  const dataAttrTagged =
    tagProjectionEnabled && !segment.tagged ? 'nottagged' : 'tagged'

  // State
  const [segmentClasses, setSegmentClasses] = useState([])
  const [autopropagated, setAutopropagated] = useState(
    segment.autopropagated_from != 0,
  )
  const [selectedTextObj, setSelectedTextObj] = useState(null)
  const [, setForceUpdate] = useState(0)

  // ------------------------------------------------------------------
  // Helpers (stable callbacks that use refs to read current values)
  // ------------------------------------------------------------------

  const checkIfCanOpenSegment = useCallback(() => {
    const seg = segmentRef.current
    return (
      (isReviewRef.current &&
        !(seg.status.toUpperCase() == SEGMENTS_STATUS.NEW) &&
        !(seg.status.toUpperCase() == SEGMENTS_STATUS.DRAFT)) ||
      !isReviewRef.current
    )
  }, [])

  const checkOpenSegmentComment = useCallback(() => {
    const seg = segmentRef.current
    if (
      CommentsStore.db.getCommentsCountBySegment &&
      SegmentStore.getCurrentSegmentId() === seg.sid
    ) {
      const comments_obj = CommentsStore.db.getCommentsCountBySegment(seg.sid)
      const panelClosed =
        localStorage.getItem(SegmentActions.localStorageCommentsClosed) ===
        'true'
      if (comments_obj.active > 0 && !panelClosed) {
        SegmentActions.openSegmentComment(seg.sid)
        SegmentActions.scrollToSegment(seg.sid)
      }
    }
  }, [])

  const alertNotTranslatedYet = useCallback((sid) => {
    setTimeout(() =>
      ModalsActions.showModalComponent(ConfirmMessageModal, {
        cancelText: 'Close',
        successCallback: () => SegmentActions.gotoNextTranslatedSegment(sid),
        successText: 'Open next translated segment',
        text: 'This segment is not translated yet.<br /> Only translated segments can be revised.',
      }),
    )
  }, [])

  const alertNoTranslatedSegments = useCallback(() => {
    const props = {
      text: 'There are no translated segments to revise in this job.',
      successText: 'Ok',
      successCallback: function () {
        ModalsActions.onCloseModal()
      },
    }
    setTimeout(() =>
      ModalsActions.showModalComponent(ConfirmMessageModal, props, 'Warning'),
    )
  }, [])

  const openSegment = useCallback(
    (wasOriginatedFromBrowserHistory) => {
      const seg = segmentRef.current

      if (!$sectionRef.current || !$sectionRef.current.length) return

      if (!checkIfCanOpenSegment()) {
        const progress = CatToolStore.getProgress()
        if (progress && progress.raw.translated === 0) {
          alertNoTranslatedSegments()
        } else {
          alertNotTranslatedYet(seg.sid)
        }
      } else {
        if (seg.translation?.length !== 0) {
          SegmentActions.getSegmentsQa(seg)
        }

        SegmentActions.setCurrentSegmentId(seg.sid)
        $('html').trigger('open')

        setTimeout(() => {
          const segmentId = segmentRef.current.original_sid
          if (SegmentFilterUtils.enabled()) {
            SegmentFilterUtils.setStoredState({lastSegmentId: segmentId})
          }
          if (config.isReview) {
            const panelClosed =
              localStorage.getItem(
                SegmentActions.localStorageReviewPanelClosed,
              ) === 'true'
            if (!panelClosed) {
              SegmentActions.openIssuesPanel({sid: segmentId}, false)
            }
            SegmentActions.getSegmentVersionsIssues(segmentId)
          }
        })

        checkOpenSegmentComment()

        if (clientConnectedRef.current) {
          SegmentActions.getGlossaryForSegment({
            sid: seg.sid,
            fid: fidRef.current,
            text: seg.segment,
          })
        }

        const hashUrl = document.location.pathname + '#' + seg.sid
        if (wasOriginatedFromBrowserHistory) {
          history.replaceState(null, null, hashUrl)
        } else {
          history.pushState(null, null, hashUrl)
        }
        var historyChangeStateEvent = new Event('historyChangeState')
        window.dispatchEvent(historyChangeStateEvent)

        document.title = `${document.title?.split('#')[0]} #${seg.sid}`
      }
    },
    [
      checkIfCanOpenSegment,
      alertNoTranslatedSegments,
      alertNotTranslatedYet,
      checkOpenSegmentComment,
    ],
  )

  // Keep a ref so store listeners always call the latest version
  const openSegmentRef = useRef(openSegment)
  openSegmentRef.current = openSegment

  const removeSelection = useCallback(() => {
    const selection = document.getSelection()
    if (
      sectionRef.current &&
      sectionRef.current.contains(selection.anchorNode)
    ) {
      selection.removeAllRanges()
    }
    setSelectedTextObj(null)
  }, [])

  // ------------------------------------------------------------------
  // Store-listener callbacks (stable — use refs for current values)
  // ------------------------------------------------------------------

  const addClass = useCallback((sid, newClass) => {
    const seg = segmentRef.current
    if (seg.sid == sid || sid === -1 || sid.split('-')[0] == seg.sid) {
      setSegmentClasses((prev) => {
        let classes = prev.slice()
        if (newClass.indexOf(' ') > 0) {
          forEach(newClass.split(' '), function (item) {
            if (classes.indexOf(item) < 0) classes.push(item)
          })
        } else {
          if (classes.indexOf(newClass) < 0) classes.push(newClass)
        }
        return classes
      })
    }
  }, [])

  const removeClass = useCallback((sid, className) => {
    const seg = segmentRef.current
    if (seg.sid == sid || sid === -1 || sid.indexOf(seg.sid) !== -1) {
      setSegmentClasses((prev) => {
        let classes = prev.slice()
        const removeFn = function (item) {
          let index = classes.indexOf(item)
          if (index > -1) classes.splice(index, 1)
        }
        if (className.indexOf(' ') > 0) {
          forEach(className.split(' '), function (item) {
            removeFn(item)
          })
        } else {
          removeFn(className)
        }
        return classes
      })
    }
  }, [])

  const setAsAutopropagated = useCallback((sid, propagation) => {
    if (segmentRef.current.sid == sid) {
      setAutopropagated(propagation)
    }
  }, [])

  const setSegmentStatus = useCallback((sid) => {
    if (segmentRef.current.sid == sid) {
      setSegmentClasses((prev) => {
        let classes = prev.slice(0)
        let index = classes.findIndex(function (item) {
          return item.indexOf('status-') > -1
        })
        if (index >= 0) classes.splice(index, 1)
        return classes
      })
    }
  }, [])

  const openSegmentFromAction = useCallback(
    (sid, wasOriginatedFromBrowserHistory) => {
      sid = sid + ''
      const seg = segmentRef.current
      if (
        (sid === seg.sid || (seg.original_sid === sid && seg.firstOfSplit)) &&
        !seg.opened
      ) {
        openSegmentRef.current(wasOriginatedFromBrowserHistory)
      }
    },
    [],
  )

  const openRevisionPanel = useCallback((data) => {
    const seg = segmentRef.current
    if (
      parseInt(data.sid) === parseInt(seg.sid) &&
      (!SegmentUtils.isIceSegment(seg) ||
        (SegmentUtils.isIceSegment(seg) && seg.unlocked))
    ) {
      setSelectedTextObj(data.selection)
    } else {
      setSelectedTextObj(null)
    }
  }, [])

  const forceUpdateSegment = useCallback((sid) => {
    if (segmentRef.current.sid === sid) {
      setForceUpdate((c) => c + 1)
    }
  }, [])

  const clientReconnection = useCallback(() => {
    const seg = segmentRef.current
    if (seg.opened) {
      SegmentActions.getGlossaryForSegment({
        sid: seg.sid,
        fid: fidRef.current,
        text: seg.segment,
      })
      SegmentActions.getContributions(seg.sid, multiMatchLangsRef.current)
    }
  }, [])

  const handleKeyDown = useCallback((event) => {
    const seg = segmentRef.current
    if (event.code === 'Escape' && !config.targetIsCJK) {
      if (
        seg.opened &&
        !seg.openComments &&
        !seg.openIssues &&
        !SearchUtils.searchOpen
      ) {
        if (!seg.openSplit) {
          SegmentActions.closeSegment(seg.sid)
        } else {
          SegmentActions.closeSplitSegment()
        }
      } else if (seg.openComments) {
        SegmentActions.closeSegmentComment()
      } else if (seg.openIssues) {
        SegmentActions.closeIssuesPanel()
      }
    }
  }, [])

  // ------------------------------------------------------------------
  // Effects
  // ------------------------------------------------------------------

  // Mount / Unmount — register store listeners & keydown handler
  useEffect(() => {
    $sectionRef.current = $(sectionRef.current)

    document.addEventListener('keydown', handleKeyDown)
    SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_CLASS, addClass)
    SegmentStore.addListener(SegmentConstants.REMOVE_SEGMENT_CLASS, removeClass)
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_PROPAGATION,
      setAsAutopropagated,
    )
    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_STATUS,
      setSegmentStatus,
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_SEGMENT,
      openSegmentFromAction,
    )
    SegmentStore.addListener(
      SegmentConstants.FORCE_UPDATE_SEGMENT,
      forceUpdateSegment,
    )
    CatToolStore.addListener(
      CatToolConstants.CLIENT_RECONNECTION,
      clientReconnection,
    )
    SegmentStore.addListener(
      SegmentConstants.OPEN_ISSUES_PANEL,
      openRevisionPanel,
    )

    // If segment was already open on mount
    if (segmentRef.current.opened) {
      setTimeout(() => openSegmentRef.current())
      setTimeout(
        () => SegmentActions.setCurrentSegment(segmentRef.current.sid),
        0,
      )
    }

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
      SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_CLASS, addClass)
      SegmentStore.removeListener(
        SegmentConstants.REMOVE_SEGMENT_CLASS,
        removeClass,
      )
      SegmentStore.removeListener(
        SegmentConstants.SET_SEGMENT_PROPAGATION,
        setAsAutopropagated,
      )
      SegmentStore.removeListener(
        SegmentConstants.SET_SEGMENT_STATUS,
        setSegmentStatus,
      )
      SegmentStore.removeListener(
        SegmentConstants.OPEN_SEGMENT,
        openSegmentFromAction,
      )
      SegmentStore.removeListener(
        SegmentConstants.FORCE_UPDATE_SEGMENT,
        forceUpdateSegment,
      )
      CatToolStore.removeListener(
        CatToolConstants.CLIENT_RECONNECTION,
        clientReconnection,
      )
      SegmentStore.removeListener(
        SegmentConstants.OPEN_ISSUES_PANEL,
        openRevisionPanel,
      )
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Replaces getSnapshotBeforeUpdate — react to open/close transitions
  const prevOpenedRef = useRef(segment.opened)
  const prevSpeechToTextRef = useRef(speechToTextActive)

  useEffect(() => {
    const wasOpened = prevOpenedRef.current
    const wasSpeechActive = prevSpeechToTextRef.current

    if (!wasOpened && segment.opened) {
      timeoutScrollRef.current = setTimeout(() => {
        SegmentActions.scrollToSegment(segmentRef.current.sid)
      }, 200)
      setTimeout(() => {
        SegmentActions.setCurrentSegment(segmentRef.current.sid)
      }, 0)
      setTimeout(() => {
        const seg = segmentRef.current
        if (
          seg.opened &&
          !config.isReview &&
          !SegmentStore.segmentHasIssues(seg)
        ) {
          SegmentActions.closeSegmentIssuePanel(seg.sid)
        }
        if (seg.opened && !seg.openComments) {
          SegmentActions.closeSegmentComment(seg.sid)
        }
      })
    } else if (wasOpened && !segment.opened) {
      clearTimeout(timeoutScrollRef.current)
      setTimeout(() => {
        SegmentActions.saveSegmentBeforeClose(segmentRef.current)
      })
    }

    if (
      Speech2Text.enabled() &&
      ((!wasSpeechActive && speechToTextActive) ||
        (!wasOpened && segment.opened))
    ) {
      setTimeout(() => Speech2Text.enableMicrophone($sectionRef.current))
    }

    prevOpenedRef.current = segment.opened
    prevSpeechToTextRef.current = speechToTextActive
  }, [segment.opened, speechToTextActive])

  // ------------------------------------------------------------------
  // Render helpers
  // ------------------------------------------------------------------

  const createSegmentClasses = () => {
    let classes = []
    let splitGroup = segment.split_group || []

    if (readonly) classes.push('readonly')

    if ((SegmentUtils.isIceSegment(segment) && !readonly) || secondPassLocked) {
      if (segment.unlocked) {
        classes.push('ice-unlocked')
      } else {
        classes.push('readonly')
        classes.push('ice-locked')
      }
    }

    if (segment.status) {
      classes.push('status-' + segment.status.toLowerCase())
    } else {
      classes.push('status-new')
    }

    if (segment.sid == splitGroup[0]) {
      classes.push('splitStart')
    } else if (segment.sid == splitGroup[splitGroup.length - 1]) {
      classes.push('splitEnd')
    } else if (splitGroup.length) {
      classes.push('splitInner')
    }

    if (tagProjectionEnabled && !segment.tagged) {
      classes.push('enableTP')
    }
    if (segment.edit_area_locked) classes.push('editAreaLocked')
    if (segment.inBulk) classes.push('segment-selected-inBulk')
    if (segment.muted) classes.push('muted')
    if (segment.opened && checkIfCanOpenSegment()) {
      classes.push('editor')
      classes.push('opened')
    }
    if (segment.modified || segment.autopropagated_from !== 0) {
      classes.push('modified')
    }
    if (sideOpen) classes.push('slide-right')
    if (segment.openSplit) classes.push('split-action')
    if (segment.selected) classes.push('segment-selected')

    return classes
  }

  const checkSegmentStatus = (classes) => {
    if (classes.length === 0) return classes
    let statusMatches = classes.join(' ').match(/status-/g)
    if (statusMatches && statusMatches.length > 1) {
      let index = classes.findIndex(function (item) {
        return item.indexOf('status-new') > -1
      })
      if (index >= 0) classes.splice(index, 1)
    }
    return classes
  }

  const checkSegmentClasses = () => {
    let classes = segmentClasses.slice()
    classes = union(classes, createSegmentClasses())
    classes = checkSegmentStatus(classes)
    if (classes.indexOf('muted') > -1 && classes.indexOf('editor') > -1) {
      let indexEditor = classes.indexOf('editor')
      classes.splice(indexEditor, 1)
      let indexOpened = classes.indexOf('opened')
      classes.splice(indexOpened, 1)
    }
    return classes
  }

  const isSplitted = () => !isUndefined(segment.split_group)

  const isFirstOfSplit = () =>
    !isUndefined(segment.split_group) &&
    segment.split_group.indexOf(segment.sid) === 0

  const getTranslationIssues = () => {
    if (
      ((sideOpen && (!segment.opened || !segment.openIssues)) || !sideOpen) &&
      !segment.readonly &&
      (!isSplitted() || (isSplitted() && isFirstOfSplit())) &&
      segment.sid
    ) {
      return (
        <TranslationIssuesSideButton
          sid={segment.splitted ? segment.sid.split('-')[0] : segment.sid}
          segment={segment}
          open={segment.openIssues}
        />
      )
    }
    return null
  }

  const lockUnlockSegment = (event) => {
    event.preventDefault()
    event.stopPropagation()
    if (!segment.unlocked && SegmentUtils.isSecondPassLockedSegment(segment)) {
      const props = {
        text: 'You are about to edit a segment that has been approved in the 2nd pass review. The project owner and 2nd pass reviser will be notified.',
        successText: 'Ok',
        successCallback: function () {
          ModalsActions.onCloseModal()
        },
      }
      ModalsActions.showModalComponent(
        ConfirmMessageModal,
        props,
        'Modify locked and approved segment ',
      )
    }
    SegmentActions.setSegmentLocked(segment, fid, !segment.unlocked)
  }

  const handleChangeBulk = (event) => {
    event.stopPropagation()
    if (event.shiftKey) {
      setBulkSelection(segment.sid, fid)
    } else {
      SegmentActions.toggleSegmentOnBulk(segment.sid, fid)
      setLastSelectedSegment(segment.sid, fid)
    }
  }

  const onClickEvent = () => {
    if (readonly || (!segment.unlocked && SegmentUtils.isIceSegment(segment))) {
      SegmentActions.handleClickOnReadOnly(segment)
    } else if (segment.muted) {
      return
    } else if (!segment.opened) {
      openSegmentRef.current()
      if (checkIfCanOpenSegment()) {
        SegmentActions.setOpenSegment(segment.sid, fid)
      }
    }
  }

  // ------------------------------------------------------------------
  // Render
  // ------------------------------------------------------------------

  const showLockIcon = SegmentUtils.isIceSegment(segment) || secondPassLocked
  const segment_classes = checkSegmentClasses()
  const split_group = segment.split_group || []
  const autoPropagable = segment.repetitions_in_chunk !== 1
  const originalId = segment.original_sid
  const translationIssues = getTranslationIssues()
  const locked =
    !segment.unlocked &&
    (SegmentUtils.isIceSegment(segment) || secondPassLocked)
  const segmentHasIssues = SegmentStore.segmentHasIssues(segment)

  const contextValue = {
    enableTagProjection: guessTagActive && !segment.tagged,
    isReview,
    segImmutable,
    segment,
    files,
    speech2textEnabledFn,
    readonly,
    locked,
    removeSelection,
    openSegment,
    clientConnected,
    clientId,
    multiMatchLangs,
    userInfo,
  }

  return (
    <SegmentContext.Provider value={contextValue}>
      <section
        ref={sectionRef}
        id={'segment-' + segment.sid}
        className={`${segment_classes.join(' ')} source-${config.source_code} target-${config.target_code} ${config.isSourceRTL ? 'rtl-source' : ''} ${config.isTargetRTL ? 'rtl-target' : ''}`}
        data-autopropagated={autopropagated}
        data-split-group={split_group}
        data-split-original-id={originalId}
        data-tagmode="crunched"
        data-tagprojection={dataAttrTagged}
        data-fid={segment.id_file}
        data-modified={segment.modified}
      >
        <div className="sid" title={segment.sid}>
          <div className="txt">{segment.sid}</div>

          {showLockIcon ? (
            !readonly ? (
              segment.unlocked ? (
                <div className="ice-locked-icon" onClick={lockUnlockSegment}>
                  <button className="unlock-button unlocked icon-unlocked3" />
                </div>
              ) : (
                <div className="ice-locked-icon" onClick={lockUnlockSegment}>
                  <button className="icon-lock unlock-button locked" />
                </div>
              )
            ) : null
          ) : null}

          <div className="txt segment-add-inBulk">
            <input
              type="checkbox"
              checked={segment.inBulk}
              onClick={handleChangeBulk}
            />
          </div>

          {!segment.ice_locked &&
          config.splitSegmentEnabled &&
          segment.opened ? (
            !segment.openSplit ? (
              <div className="actions">
                <button
                  className="split"
                  title={`Click to split segment (${Shortcuts.cattol.events.splitSegment.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase()})`}
                  onClick={() => SegmentActions.openSplitSegment(segment.sid)}
                >
                  <i className="icon-split" />
                </button>
                <p className="split-shortcut">CTRL + S</p>
              </div>
            ) : (
              <div className="actions">
                <button
                  className="split cancel"
                  title="Click to close split segment"
                  onClick={() => SegmentActions.closeSplitSegment()}
                >
                  <i className="icon-split" />
                </button>
              </div>
            )
          ) : null}
        </div>

        <div className="body">
          <SegmentHeader
            sid={segment.sid}
            autopropagated={autopropagated}
            segmentOpened={segment.opened}
            repetition={autoPropagable}
            splitted={segment.splitted}
            saving={segment.saving}
          />
          <SegmentBody onClick={onClickEvent} />
          {SegmentFilter && SegmentFilter.enabled() ? (
            <div className="edit-distance">
              Edit Distance: {segment.edit_distance}
            </div>
          ) : null}

          {segment.opened ? <SegmentFooter /> : null}
        </div>

        <div className="segment-side-buttons">
          {config.comments_enabled &&
          (!segment.openComments || !segment.opened) ? (
            <SegmentsCommentsIcon />
          ) : null}
          <SegmentQAIcon sid={segment.sid} />

          {isReview && (
            <div
              data-mount="translation-issues-button"
              className="translation-issues-button"
              data-sid={segment.sid}
            >
              {translationIssues}
            </div>
          )}
        </div>
        <div className="segment-side-container">
          {config.comments_enabled && segment.openComments ? (
            <SegmentCommentsContainer />
          ) : null}
          {config.isReview &&
          segment.openIssues &&
          segment.opened &&
          (config.isReview || (!config.isReview && segmentHasIssues)) ? (
            <div className="review-balloon-container">
              {!segment.versions ? null : (
                <ReviewExtendedPanel
                  segment={segment}
                  isReview={config.isReview}
                  selectionObj={selectedTextObj}
                />
              )}
            </div>
          ) : null}
        </div>
      </section>
    </SegmentContext.Provider>
  )
}

const Segment = React.memo(
  SegmentComponent,
  (prevProps, nextProps) =>
    nextProps.segImmutable.equals(prevProps.segImmutable) &&
    nextProps.sideOpen === prevProps.sideOpen &&
    nextProps.clientConnected === prevProps.clientConnected &&
    nextProps.speechToTextActive === prevProps.speechToTextActive,
)

Segment.displayName = 'Segment'

export default Segment
