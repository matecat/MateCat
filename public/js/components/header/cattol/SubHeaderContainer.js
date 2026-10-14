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

const SubHeaderContainer = ({filtersEnabled, userInfo}) => {
  const [state, setState] = useState({
    selectionBar: true,
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

  // header-bars-wrapper animates its height closed; unmounting the active panel's content
  // immediately (instead of once that transition finishes) leaves nothing for it to collapse,
  // so it snaps shut instead of animating. mountedPanel keeps the last-open panel's content
  // mounted through the close animation, clearing only once the transition actually ends.
  // qaComponent is a peer of search/segmentFilter here (showContainer/toggleContainer/
  // closeSubHeader already treat all three as mutually exclusive), so it goes through the
  // same measured-height/deferred-unmount treatment instead of sitting outside it — otherwise
  // opening only the QA panel (search and segmentFilter both closed) would leave the wrapper
  // collapsed to 0 height with QA's content invisible inside it.
  const isOpen = state.search || state.segmentFilter || state.qaComponent
  const activePanel = state.search
    ? 'search'
    : state.segmentFilter
      ? 'segmentFilter'
      : state.qaComponent
        ? 'qaComponent'
        : null
  const [mountedPanel, setMountedPanel] = useState(activePanel)
  const wrapperRef = useRef()
  const contentRef = useRef()
  const {height: contentHeight} = useResizeObserver(contentRef)

  useEffect(() => {
    if (activePanel) setMountedPanel(activePanel)
  }, [activePanel])

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
        setMountedPanel(null)
      }
    }

    wrapper.addEventListener('transitionend', handleTransitionEnd)
    return () =>
      wrapper.removeEventListener('transitionend', handleTransitionEnd)
  }, [isOpen])

  // The wrapper's height is driven imperatively from the measured content height (rather
  // than a CSS height:auto class toggle) specifically so that switching directly from
  // Search to Filters (or back) — where the wrapper's open/closed state never changes —
  // still produces a real "old height -> new height" transition instead of snapping
  // straight to the new panel's size. Reading scrollHeight directly (rather than relying
  // only on the ResizeObserver-reported contentHeight) matters for that same switch case:
  // this effect also re-runs the instant mountedPanel swaps the rendered content, and
  // useLayoutEffect runs synchronously right after that DOM mutation, before the browser
  // paints — so there's no frame where the wrapper is still clamped to the outgoing
  // panel's height while the incoming panel's (taller or shorter) content is already in
  // the DOM. contentHeight stays as a dependency to catch a panel's content changing size
  // on its own later (e.g. a growing search-results list) while it stays mounted.
  const transitionKeyRef = useRef(`${isOpen}:${mountedPanel}`)

  useLayoutEffect(() => {
    if (!wrapperRef.current || !contentRef.current) return
    // Only re-clip when open/closed state or the mounted panel itself actually changes
    // (open, close, or switching between panels) — not on every height change. The same
    // panel's content quietly growing/shrinking while it stays mounted (e.g. a search-results
    // list) must NOT re-clip: doing that would flip a settled-open, overflow:visible wrapper
    // back to hidden and then back to visible once that resize settles too, flickering
    // anything currently overflowing (e.g. an open Data sample popup) off and back on for no
    // reason, since nothing about WHAT is showing actually changed.
    const transitionKey = `${isOpen}:${mountedPanel}`
    if (transitionKey !== transitionKeyRef.current) {
      transitionKeyRef.current = transitionKey
      wrapperRef.current.style.overflow = 'hidden'
    }
    wrapperRef.current.style.height =
      isOpen && contentRef.current.scrollHeight > 0
        ? `${contentRef.current.scrollHeight}px`
        : '0px'
  }, [isOpen, contentHeight, mountedPanel])

  return (
    <div id="header-bars-wrapper" ref={wrapperRef}>
      <div ref={contentRef}>
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
      <SegmentSelectionPanel
        active={state.selectionBar}
        isReview={config.isReview}
      />
    </div>
  )
}

export default SubHeaderContainer
