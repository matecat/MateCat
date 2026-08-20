import React, {useCallback, useEffect, useRef, useState} from 'react'
import {debounce, find} from 'lodash'
import {tagSignatures, getTooltipTag} from '../utils/DraftMatecatUtils/tagModel'
import {classifyPcPhTag} from '../utils/DraftMatecatUtils/pcTagUtils'
import SegmentStore from '../../../stores/SegmentStore'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentConstants from '../../../constants/SegmentConstants'
import CatToolConstants from '../../../constants/CatToolConstants'
import EditAreaConstants from '../../../constants/EditAreaConstants'
import SegmentActions from '../../../actions/SegmentActions'
import SearchUtils from '../../header/cattol/search/searchUtils'
import Tooltip from '../../common/Tooltip'

// These were instance methods reading only `this.props`/`this.state`, so they
// become plain functions over their inputs. Keeping them outside the component
// lets the store handlers below hold a single stable identity without closing
// over anything that changes per render.

const selectCorrectStyle = (
  {entityKey, contentState, getUpdatedSegmentInfo, isRTL},
  clickedTagId = null,
  clickedTagText = null,
) => {
  const {segmentOpened} = getUpdatedSegmentInfo()
  const {
    data: {id: entityId, placeholder: entityPlaceholder, name: entityName},
  } = contentState.getEntity(entityKey)

  // Basic style accordin to language direction
  const baseStyle =
    tagSignatures[entityName] &&
    (isRTL && tagSignatures[entityName].styleRTL
      ? tagSignatures[entityName].styleRTL
      : tagSignatures[entityName].style)

  // Check if tag is in an active segment
  const tagInactive = !segmentOpened ? 'tag-inactive' : ''

  // Click
  const tagClicked =
    entityId &&
    clickedTagId &&
    clickedTagId === entityId &&
    clickedTagText &&
    clickedTagText === entityPlaceholder
      ? 'tag-clicked'
      : '' // green

  return `${baseStyle} ${tagInactive} ${tagClicked}`.trim()
}

export const highlightOnWarnings = ({
  getUpdatedSegmentInfo,
  contentState,
  entityKey,
  isTarget,
}) => {
  const {tagMismatch, segmentOpened, missingTagsInTarget} =
    getUpdatedSegmentInfo()
  const {data: entityData} = contentState.getEntity(entityKey) || {}

  if (!segmentOpened) return
  const {encodedText} = entityData

  // pc (compressible) tags: the QA endpoint can't tell missing closing tags
  // apart (every one converts to the same generic `</pc>` markup), so
  // matching by reported string content doesn't work here. missingTagsInTarget
  // is computed client-side by tag identity/numbering instead (see
  // checkForMissingTag.js) and is exact, since its entries are the source's
  // own tag objects.
  if (classifyPcPhTag(encodedText)) {
    if (isTarget) return ''
    const isMissingInTarget = (missingTagsInTarget || []).some(
      (tag) => tag?.data?.encodedText === encodedText,
    )
    return isMissingInTarget ? 'tag-mismatch-error' : ''
  }

  if (!tagMismatch) return
  let tagWarningStyle = ''
  if (tagMismatch.target && tagMismatch.target.length > 0 && isTarget) {
    // Todo: Check tag type and tag id instead of string
    tagMismatch.target.forEach((tagString) => {
      if (encodedText === tagString) {
        tagWarningStyle = 'tag-mismatch-error'
      }
    })
  } else if (tagMismatch.source && tagMismatch.source.length > 0 && !isTarget) {
    tagMismatch.source.forEach((tagString) => {
      if (encodedText === tagString) {
        tagWarningStyle = 'tag-mismatch-error'
      }
    })
  } else if (tagMismatch.order && isTarget) {
    tagMismatch.order.forEach((tagString) => {
      if (encodedText === tagString) {
        tagWarningStyle = 'tag-mismatch-warning'
      }
    })
  }
  return tagWarningStyle
}

const markSearch = (text, searchParams, {start, end}) => {
  let {
    active,
    currentActive,
    textToReplace,
    params,
    occurrences,
    currentInSearchIndex,
  } = searchParams
  let currentOccurrence = find(
    occurrences,
    (occ) => occ.searchProgressiveIndex === currentInSearchIndex,
  )
  let isCurrent =
    currentOccurrence &&
    currentOccurrence.matchPosition >= start &&
    currentOccurrence.matchPosition < end

  if (active && isCurrent) SegmentActions.setIsCurrentSearchOccurrenceTag(true)

  if (active) {
    let regex = SearchUtils.getSearchRegExp(
      textToReplace,
      params.ingnoreCase,
      params.exactMatch,
    )
    let parts = text.split(regex)
    for (let i = 1; i < parts.length; i += 2) {
      let color =
        currentActive && isCurrent ? 'rgb(255 210 14)' : 'rgb(255 255 0)'
      parts[i] = (
        <span key={i} style={{backgroundColor: color}}>
          {parts[i]}
        </span>
      )
    }
    return parts
  }
  return text
}

const getChildrenContent = (
  index,
  entityName,
  pcRole,
  {phTagsCompressed, children},
) => {
  const isPhTag = entityName === 'ph'

  if (isPhTag && index >= 0) {
    // A closing pc tag always shows only its number (never the equiv-text /
    // closing content). An opening tag shows its content unless compressed.
    const isPcClose = pcRole === 'close'
    return (
      <>
        <span className="index-counter">{index + 1}</span>
        {!phTagsCompressed && !isPcClose && children}
      </>
    )
  }

  return children
}

const measureShouldTooltipOnHover = (tagNode) => {
  const textSpanDisplayed =
    tagNode && tagNode.querySelector('span[data-text="true"]')
  return (
    textSpanDisplayed &&
    textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth
  )
}

const TagEntity = (props) => {
  const {children, entityKey, contentState, getUpdatedSegmentInfo} = props

  const [state, setState] = useState(() => {
    const {
      data: {name: entityName},
    } = props.contentState.getEntity(props.entityKey)

    return {
      tagStyle: selectCorrectStyle(props),
      tagWarningStyle: '',
      tooltipAvailable: getTooltipTag().includes(entityName),
      shouldTooltipOnHover: false,
      phTagsCompressed: CatToolStore.isPhTagsCompressed(),
      clicked: false,
      focused: false,
      searchParams: props.getSearchParams(),
      entityKey: props.entityKey,
    }
  })

  // Merge semantics of the class's this.setState, which every handler below
  // relied on to set one field without clearing the others.
  const patchState = useCallback((partial) => {
    setState((prev) => ({...prev, ...partial}))
  }, [])

  // The store handlers must keep one stable identity for the component's whole
  // lifetime, so the cleanup unregisters the exact references it registered.
  // They read props/state through this mirror, refreshed before any of them can
  // run.
  const liveRef = useRef({})
  liveRef.current = {props, state}

  const contentRef = useRef(null)
  const tagRef = useRef(null)
  const focusedState = useRef({})

  const addSearchParams = useCallback(
    (sid) => {
      const {props: live} = liveRef.current
      const {getSearchParams, isTarget} = live
      if (sid !== live.sid) return
      let searchParams = getSearchParams()
      if (
        searchParams.active &&
        ((searchParams.isTarget && isTarget) ||
          (!searchParams.isTarget && !isTarget))
      ) {
        patchState({
          searchParams,
        })
      }
    },
    [patchState],
  )

  const updateSearchParams = useCallback(
    (sid, currentInSearchIndex) => {
      const {props: live, state: liveState} = liveRef.current
      const {getSearchParams} = live
      if (
        sid !== live.sid ||
        (sid === live.sid && !liveState.searchParams.active)
      )
        return
      let searchParamsNew = getSearchParams()
      searchParamsNew.currentInSearchIndex = currentInSearchIndex
      patchState({
        searchParams: searchParamsNew,
      })
    },
    [patchState],
  )

  const removeSearchParams = useCallback(() => {
    const {props: live, state: liveState} = liveRef.current
    if (liveState.searchParams.active) {
      const {getSearchParams} = live
      let searchParams = getSearchParams()
      patchState({
        searchParams,
      })
    }
  }, [patchState])

  const onPhTagsCompressedToggle = useCallback(() => {
    patchState({phTagsCompressed: CatToolStore.isPhTagsCompressed()})
  }, [patchState])

  const highlightTags = useCallback(
    (tagId, tagPlaceholder, triggerEntityKey) => {
      const {props: live, state: liveState} = liveRef.current
      const {entityKey: liveEntityKey, contentState: liveContentState} = live
      const {clicked} = liveState
      const {
        data: {id: entityId, placeholder: entityPlaceholder},
      } = liveContentState.getEntity(liveEntityKey)
      // Turn OFF
      if (clicked && (!tagId || tagId !== entityId)) {
        patchState({
          tagStyle: selectCorrectStyle(live),
          clicked: false,
          entityKey: liveEntityKey,
        })
      } else if (liveEntityKey === triggerEntityKey) {
        patchState({
          tagStyle: selectCorrectStyle(live, tagId, tagPlaceholder, true),
          clicked: true,
          entityKey: liveEntityKey,
        })
      } else if (
        tagId === entityId &&
        entityPlaceholder === tagPlaceholder &&
        liveEntityKey !== triggerEntityKey
      ) {
        patchState({
          tagStyle: selectCorrectStyle(live, tagId, tagPlaceholder),
          clicked: true,
          entityKey: liveEntityKey,
        })
      }
    },
    [patchState],
  )

  const updateTagStyle = useCallback(
    (sid, isTarget) => {
      const {props: live, state: liveState} = liveRef.current
      if (!live.isTarget && isTarget) return
      const newStyle = selectCorrectStyle(live)
      if (newStyle !== liveState.tagStyle) {
        patchState({
          tagStyle: newStyle,
          entityKey: live.entityKey,
        })
      }
    },
    [patchState],
  )

  const updateTagWarningStyle = useCallback(() => {
    const {props: live, state: liveState} = liveRef.current
    const {tagWarningStyle: prevTagWarningStyle} = liveState
    const tagWarningStyle = highlightOnWarnings(live)
    if (prevTagWarningStyle !== tagWarningStyle) {
      patchState({tagWarningStyle})
    }
  }, [patchState])

  const focusTag = useCallback(
    ({tagsSelected}) => {
      const {skipTmOut, tmOut} = focusedState.current
      if (tmOut) clearTimeout(tmOut)
      focusedState.current = {}

      if (!tagsSelected?.length) {
        // reset
        patchState({
          focused: false,
        })
        return
      }

      const updateState = () => {
        patchState({
          focused: tagsSelected.some(
            ({entityKey}) => entityKey === liveRef.current.props.entityKey,
          ),
        })
      }

      if (!skipTmOut) {
        focusedState.current.tmOut = setTimeout(() => updateState(), 100)
      } else {
        updateState()
      }
    },
    [patchState],
  )

  // Seeded once: rebuilding a debounced wrapper on a later render would
  // silently drop whatever call was already pending inside it.
  const debouncedRef = useRef(null)
  if (!debouncedRef.current) {
    debouncedRef.current = {
      updateTagStyle: debounce(updateTagStyle, 500),
      updateTagWarningStyle: debounce(updateTagWarningStyle, 500),
    }
  }

  useEffect(() => {
    const {
      updateTagStyle: updateTagStyleDebounced,
      updateTagWarningStyle: updateTagWarningStyleDebounced,
    } = debouncedRef.current

    SegmentStore.addListener(
      SegmentConstants.SET_SEGMENT_WARNINGS,
      updateTagWarningStyleDebounced,
    )
    SegmentStore.addListener(SegmentConstants.HIGHLIGHT_TAGS, highlightTags)
    SegmentStore.addListener(
      EditAreaConstants.EDIT_AREA_CHANGED,
      updateTagStyleDebounced,
    )
    SegmentStore.addListener(
      SegmentConstants.ADD_SEARCH_RESULTS,
      addSearchParams,
    )
    SegmentStore.addListener(
      SegmentConstants.ADD_CURRENT_SEARCH,
      updateSearchParams,
    )
    SegmentStore.addListener(
      SegmentConstants.REMOVE_SEARCH_RESULTS,
      removeSearchParams,
    )
    SegmentStore.addListener(SegmentConstants.FOCUS_TAGS, focusTag)
    CatToolStore.addListener(
      CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
      onPhTagsCompressedToggle,
    )

    patchState({
      shouldTooltipOnHover: measureShouldTooltipOnHover(tagRef.current),
    })

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.SET_SEGMENT_WARNINGS,
        updateTagWarningStyleDebounced,
      )
      SegmentStore.removeListener(
        SegmentConstants.HIGHLIGHT_TAGS,
        highlightTags,
      )
      SegmentStore.removeListener(
        EditAreaConstants.EDIT_AREA_CHANGED,
        updateTagStyleDebounced,
      )
      SegmentStore.removeListener(
        SegmentConstants.ADD_SEARCH_RESULTS,
        addSearchParams,
      )
      SegmentStore.removeListener(
        SegmentConstants.ADD_CURRENT_SEARCH,
        updateSearchParams,
      )
      SegmentStore.removeListener(
        SegmentConstants.REMOVE_SEARCH_RESULTS,
        removeSearchParams,
      )
      SegmentStore.removeListener(SegmentConstants.FOCUS_TAGS, focusTag)
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_PH_TAGS_COMPRESSED,
        onPhTagsCompressedToggle,
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // componentDidUpdate: no dependency array, so it runs after every committed
  // render but not on mount, and re-measures the overflow the same way.
  const prevPropsRef = useRef(null)
  useEffect(() => {
    const prevProps = prevPropsRef.current
    prevPropsRef.current = props
    if (!prevProps) return

    if (prevProps.entityKey !== props.entityKey) {
      const shouldTooltipOnHover = measureShouldTooltipOnHover(tagRef.current)
      if (shouldTooltipOnHover !== liveRef.current.state.shouldTooltipOnHover) {
        patchState({shouldTooltipOnHover})
      }
    }
  })

  const onClickBound = () => {
    const {start, end, onClick: onClickAction} = props
    const {
      data: {name: entityName},
    } = contentState.getEntity(entityKey)
    onClickAction(start, end, entityName)
  }

  const {
    tagStyle,
    tagWarningStyle,
    tooltipAvailable,
    shouldTooltipOnHover,
    searchParams,
    focused,
    phTagsCompressed,
  } = state

  const style =
    props.entityKey === state.entityKey ? tagStyle : selectCorrectStyle(props)
  const {openSplit} = getUpdatedSegmentInfo()
  const {
    data: {
      id: entityId,
      placeholder: entityPlaceholder,
      index,
      name: entityName,
      pcRole,
    },
  } = contentState.getEntity(entityKey)
  const decoratedText = Array.isArray(children)
    ? children[0].props.text
    : children.props.decoratedText

  const isCompressedPh = entityName === 'ph' && phTagsCompressed && index >= 0
  const pcRoleClass = entityName === 'ph' && pcRole ? ` tag-pc-${pcRole}` : ''
  // A closing pc tag shows only its number, so it never needs a content tooltip.
  const isPcClose = entityName === 'ph' && pcRole === 'close'
  const showTooltip =
    ((shouldTooltipOnHover && tooltipAvailable) || isCompressedPh) && !isPcClose

  return (
    <Tooltip
      stylePointerElement={{display: 'inline-block', position: 'relative'}}
      content={
        showTooltip && (
          <span className={`tag ${style}`}>
            <span>{entityPlaceholder}</span>
          </span>
        )
      }
    >
      <div ref={contentRef} className={'tag-container'}>
        <span
          ref={tagRef}
          className={`tag ${style}${focused ? ' tag-focused' : ''}${
            isCompressedPh ? ' tag-compressed' : ''
          }${pcRoleClass} ${tagWarningStyle}`}
          data-offset-key={props.offsetkey}
          unselectable="on"
          suppressContentEditableWarning={true}
          onClick={(e) => {
            e.stopPropagation()
            onClickBound()
            !openSplit &&
              setTimeout(() => {
                SegmentActions.highlightTags(
                  entityId,
                  entityPlaceholder,
                  entityKey,
                )
              })
            focusedState.current = {
              skipTmOut: true,
            }
          }}
        >
          {searchParams.active &&
            markSearch(decoratedText, searchParams, props)}
          {searchParams.active ? (
            <span style={{display: 'none'}}>
              {getChildrenContent(index, entityName, pcRole, {
                phTagsCompressed,
                children,
              })}
            </span>
          ) : (
            getChildrenContent(index, entityName, pcRole, {
              phTagsCompressed,
              children,
            })
          )}
        </span>
      </div>
    </Tooltip>
  )
}

export default TagEntity
