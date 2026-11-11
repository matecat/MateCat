import React, {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from 'react'

import CatToolConstants from '../../../constants/CatToolConstants'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentSelectionPanel from './bulk_selection_bar/BulkSelectionBar'
import SegmentsFilter from './segment_filter/SegmentsFilter'
import SegmentFilterUtils from './segment_filter/segment_filter'
import Search from './search/Search'
import SearchUtils from './search/searchUtils'
import QaComponent from './QAComponent'
import useResizeObserver from '../../../hooks/useResizeObserver'

// Drives one header-bar slot's open/close height animation and deferred unmount: the slot
// animates its own height closed, and unmounting its content immediately (instead of once
// that transition finishes) would leave nothing for it to collapse, so it'd snap shut
// instead of animating. `activeValue` identifies what (if anything) the slot should show —
// a string key for a slot with several mutually-exclusive things to show (only one can be
// truthy at a time), or a plain boolean for a slot with just one thing to show. Falsy means
// closed. Each slot gets its own instance of this, so one slot closing never depends on, or
// is blocked by, another slot's own open/closed state — see SubHeaderContainer below for why
// that independence matters.
const useAnimatedSlot = (activeValue) => {
  const isOpen = Boolean(activeValue)
  const [mountedValue, setMountedValue] = useState(activeValue)
  const wrapperRef = useRef()
  const contentRef = useRef()
  const {height: contentHeight} = useResizeObserver(contentRef)

  useEffect(() => {
    if (activeValue) setMountedValue(activeValue)
  }, [activeValue])

  useEffect(() => {
    const wrapper = wrapperRef.current
    if (!wrapper) return

    const handleTransitionEnd = (event) => {
      if (event.target !== wrapper || event.propertyName !== 'height') return
      if (isOpen) {
        // Only let content overflow (e.g. the filter's own Data sample popup) once the
        // open transition has actually finished — doing this any earlier would let that
        // overflowing content escape the box while it's still mid-resize.
        wrapper.style.overflow = 'visible'
      } else {
        setMountedValue(null)
      }
    }

    wrapper.addEventListener('transitionend', handleTransitionEnd)
    return () =>
      wrapper.removeEventListener('transitionend', handleTransitionEnd)
  }, [isOpen])

  // The slot's height is driven imperatively from the measured content height (rather than
  // a CSS height:auto class toggle) specifically so that switching directly between two
  // mutually-exclusive values in the same slot — where the slot's open/closed state never
  // changes — still produces a real "old height -> new height" transition instead of
  // snapping straight to the new content's size. Reading scrollHeight directly (rather than
  // relying only on the ResizeObserver-reported contentHeight) matters for that same switch
  // case: this effect also re-runs the instant mountedValue swaps the rendered content, and
  // useLayoutEffect runs synchronously right after that DOM mutation, before the browser
  // paints — so there's no frame where the slot is still clamped to the outgoing content's
  // height while the incoming (taller or shorter) content is already in the DOM.
  // contentHeight stays as a dependency to catch the mounted content changing size on its
  // own later (e.g. a growing search-results list, or the selected-segments count) while it
  // stays mounted.
  const transitionKeyRef = useRef(`${isOpen}:${mountedValue}`)

  useLayoutEffect(() => {
    if (!wrapperRef.current || !contentRef.current) return
    // Only re-clip when open/closed state or what's mounted actually changes — not on every
    // height change. The same content quietly growing/shrinking while it stays mounted must
    // NOT re-clip: doing that would flip a settled-open, overflow:visible slot back to
    // hidden and then back to visible once that resize settles too, flickering anything
    // currently overflowing (e.g. an open Data sample popup) off and back on for no reason,
    // since nothing about WHAT is showing actually changed.
    const transitionKey = `${isOpen}:${mountedValue}`
    if (transitionKey !== transitionKeyRef.current) {
      transitionKeyRef.current = transitionKey
      wrapperRef.current.style.overflow = 'hidden'
    }
    wrapperRef.current.style.height =
      isOpen && contentRef.current.scrollHeight > 0
        ? `${contentRef.current.scrollHeight}px`
        : '0px'
  }, [isOpen, contentHeight, mountedValue])

  return {wrapperRef, contentRef, mountedValue}
}

const SubHeaderContainer = ({filtersEnabled, userInfo}) => {
  const [state, setState] = useState({
    search: false,
    segmentFilter: false,
    qaComponent: false,
  })

  // SearchButton/SegmentsFilterButton each decide whether a click should open or close
  // their panel by reading SearchUtils.searchOpen / SegmentFilterUtils.open — but those
  // flags are only ever set from within the search/filter code's own open/close paths, so
  // closing one panel by opening the *other* (switching) never clears the dismissed one's
  // flag. The next click on that panel's button then sees a stale "already open" and fires
  // a close action instead of an open one, which (via Search's handleCancelClick) closes
  // every panel. This is the single place that knows the real state, so keep both flags
  // synced to it directly rather than letting each panel's own code track it independently.
  useEffect(() => {
    SearchUtils.searchOpen = state.search
    SegmentFilterUtils.open = state.segmentFilter
  }, [state.search, state.segmentFilter])

  const showContainer = useCallback((container) => {
    switch (container) {
      case 'search':
        setState((prev) => ({
          ...prev,
          search: true,
          segmentFilter: false,
          qaComponent: false,
        }))
        break
      case 'segmentFilter':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: true,
          qaComponent: false,
        }))
        break
      case 'qaComponent':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: false,
          qaComponent: true,
        }))
        break
    }
  }, [])

  const toggleContainer = useCallback((container) => {
    switch (container) {
      case 'search':
        setState((prev) => ({
          ...prev,
          search: !prev.search,
          segmentFilter: false,
          qaComponent: false,
        }))
        break
      case 'segmentFilter':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: !prev.segmentFilter,
          qaComponent: false,
        }))
        break
      case 'qaComponent':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: false,
          qaComponent: !prev.qaComponent,
        }))
        break
    }
  }, [])

  const closeSubHeader = useCallback(() => {
    setState((prev) => ({
      ...prev,
      search: false,
      segmentFilter: false,
      qaComponent: false,
    }))
  }, [])

  useEffect(() => {
    CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, showContainer)
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, toggleContainer)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeSubHeader)

    return () => {
      CatToolStore.removeListener(
        CatToolConstants.SHOW_CONTAINER,
        showContainer,
      )
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_CONTAINER,
        toggleContainer,
      )
      CatToolStore.removeListener(
        CatToolConstants.CLOSE_SUBHEADER,
        closeSubHeader,
      )
    }
  }, [])

  // Search/SegmentsFilter/QaComponent are mutually exclusive (showContainer/
  // toggleContainer/closeSubHeader already treat all three that way), so they share one
  // slot that animates between whichever of them is active.
  const activePanel = state.search
    ? 'search'
    : state.segmentFilter
      ? 'segmentFilter'
      : state.qaComponent
        ? 'qaComponent'
        : null
  const {
    wrapperRef: panelWrapperRef,
    contentRef: panelContentRef,
    mountedValue: mountedPanel,
  } = useAnimatedSlot(activePanel)

  // SegmentSelectionPanel (the bulk-selection bar) is NOT part of that mutual-exclusion
  // switch — it's driven by whether segments are bulk-selected, not by a header button, and
  // can be shown alongside whichever of search/segmentFilter/qaComponent is open (or none).
  // It gets its own slot, with its own independent open/close height animation: sharing the
  // panel group's slot would mean closing the panel group while the bulk bar stays open (or
  // vice versa) doesn't change that shared slot's total height at all, since the other side
  // is still contributing its height — so no CSS transition would ever fire to unmount the
  // side that closed, and it'd stay stuck open.
  const [hasBulkSelection, setHasBulkSelection] = useState(false)
  const {
    wrapperRef: selectionWrapperRef,
    contentRef: selectionContentRef,
    mountedValue: mountedSelectionBar,
  } = useAnimatedSlot(hasBulkSelection)

  return (
    <div id="header-bars-wrapper">
      <div
        className="header-bars-slot"
        data-testid="panel-slot"
        ref={panelWrapperRef}
      >
        <div ref={panelContentRef}>
          <Search
            active={mountedPanel === 'search'}
            isReview={config.isReview}
            searchable_statuses={config.searchable_statuses}
            userInfo={userInfo}
          />
          {filtersEnabled ? (
            <SegmentsFilter
              active={mountedPanel === 'segmentFilter'}
              isReview={config.isReview}
            />
          ) : null}
          <QaComponent
            active={mountedPanel === 'qaComponent'}
            isReview={config.isReview}
          />
        </div>
      </div>
      <div
        className="header-bars-slot"
        data-testid="selection-slot"
        ref={selectionWrapperRef}
      >
        <div ref={selectionContentRef}>
          <SegmentSelectionPanel
            active={Boolean(mountedSelectionBar)}
            onActiveChange={setHasBulkSelection}
            isReview={config.isReview}
          />
        </div>
      </div>
    </div>
  )
}

export default SubHeaderContainer
