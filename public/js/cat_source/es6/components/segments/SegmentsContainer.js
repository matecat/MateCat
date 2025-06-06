import React, {
  createRef,
  useCallback,
  useContext,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import {fromJS} from 'immutable'
import ReactDOMServer from 'react-dom/server'
import {useHotkeys} from 'react-hotkeys-hook'
import {Shortcuts} from '../../utils/shortcuts'
import VirtualList from '../common/VirtualList/VirtualList'
import RowSegment from '../common/VirtualList/Rows/RowSegment'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import CatToolConstants from '../../constants/CatToolConstants'
import CommentsConstants from '../../constants/CommentsConstants'
import CatToolStore from '../../stores/CatToolStore'
import Speech2Text from '../../utils/speech2text'
import SegmentActions from '../../actions/SegmentActions'
import {isUndefined} from 'lodash'
import SegmentUtils from '../../utils/segmentUtils'
import CommentsStore from '../../stores/CommentsStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

const ROW_MARGIN = 3
const ROW_HEIGHT = 90
const OVERSCAN = 5
const COMMENTS_PADDING_TOP = [
  {empty: 110, filled: 270},
  {empty: 40, filled: 140},
  {filled: 50},
]
const SEARCH_BAR_OPENED_PADDING_TOP = 80

const listRef = createRef()

function SegmentsContainer({isReview, startSegmentId, firstJobSegment}) {
  useHotkeys(
    Shortcuts.cattol.events.copySource.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      SegmentActions.copySourceToTarget()
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.gotoCurrent.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      SegmentActions.scrollToCurrentSegment()
      SegmentActions.setFocusOnEditArea()
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.openPrevious.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      SegmentActions.selectPrevSegmentDebounced()
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.openNext.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      SegmentActions.selectNextSegmentDebounced()
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    'ctrl',
    () => {
      SegmentActions.openSelectedSegment()
    },
    {keyup: true, enableOnContentEditable: true},
  )
  useHotkeys(
    'meta',
    () => {
      SegmentActions.openSelectedSegment()
    },
    {keyup: true, enableOnContentEditable: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.openIssuesPanel.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => {
      const segment = SegmentStore.getCurrentSegment()
      if (segment && config.isReview) {
        SegmentActions.openIssuesPanel({sid: segment.sid})
        SegmentActions.scrollToSegment(segment.sid)
      }
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.copyContribution1.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => {
      SegmentActions.chooseContributionOnCurrentSegment(1)
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.copyContribution2.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => {
      SegmentActions.chooseContributionOnCurrentSegment(2)
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.copyContribution3.keystrokes[
      Shortcuts.shortCutsKeyType
    ],
    (e) => {
      SegmentActions.chooseContributionOnCurrentSegment(3)
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.splitSegment.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      const segment = SegmentStore.getCurrentSegment()
      if (segment) {
        SegmentActions.openSplitSegment(segment.sid)
      }
    },
    {enableOnContentEditable: true, preventDefault: true},
  )
  useHotkeys(
    Shortcuts.cattol.events.openComments.keystrokes[Shortcuts.shortCutsKeyType],
    (e) => {
      e.stopPropagation()
      e.preventDefault()
      const current = SegmentStore.getCurrentSegmentId()
      if (current) SegmentActions.openSegmentComment(current)
    },
    {enableOnContentEditable: true, preventDefault: true},
  )

  const {userInfo} = useContext(ApplicationWrapperContext)

  const [segments, setSegments] = useState(fromJS([]))
  const [rows, setRows] = useState([])
  const [essentialRows, setEssentialRows] = useState([])
  const [hasCachedRows, setHasCachedRows] = useState(false)
  const [onUpdateRow, setOnUpdateRow] = useState(undefined)
  const [heightArea, setHeightArea] = useState(0)
  const [startIndex, setStartIndex] = useState(0)
  const [stopIndex, setStopIndex] = useState(0)
  const [isSideOpen, setIsSideOpen] = useState(false)
  const [scrollToSid, setScrollToSid] = useState(startSegmentId)
  const [scrollToSelected, setScrollToSelected] = useState(false)
  const [lastSelectedSegment, setLastSelectedSegment] = useState(undefined)
  const [files, setFiles] = useState(CatToolStore.getJobFilesInfo())
  const [addedComment, setAddedComment] = useState(undefined)
  const [scrollTopVisible, setScrollTopVisible] = useState(undefined)
  const [isSearchBarOpen, setIsSearchBarOpen] = useState(false)
  const [clientConnected, setClientConnected] = useState()
  const [clientId, setClientId] = useState()

  const persistenceVariables = useRef({
    lastScrolled: undefined,
    scrollDirectionTop: false,
    lastScrollTop: 0,
    segmentsWithCollectionType: [],
    haveBeenAddedSegmentsBefore: false,
    isUserDraggingCursor: false,
  })
  const rowsRenderedHeight = useRef(new Map())
  const cachedRowsHeightMap = useRef(new Map())
  const cachedSegmentsToJS = useRef(new Map())

  const {guess_tags: guessTagActive, dictation: speechToTextActive} =
    userInfo?.metadata ?? {}

  const onChangeRowHeight = useCallback(
    (id, newHeight) => {
      rowsRenderedHeight.current.set(
        id,
        getRowHeightWithMargin({
          id,
          height: newHeight,
        }),
      )
      setOnUpdateRow(Symbol())
    },
    [getRowHeightWithMargin],
  )

  const multiMatchLangs = useMemo(
    () => userInfo?.metadata?.cross_language_matches ?? [],
    [userInfo?.metadata],
  )

  const scrollToParams = useMemo(() => {
    const position = scrollToSelected ? 'auto' : 'start'
    const ROWS_LENGTH = rows.length
    if (scrollToSid && ROWS_LENGTH > 0) {
      const index = rows.findIndex(({id}) => {
        if (scrollToSid.toString().indexOf('-') === -1) {
          return parseInt(id) === parseInt(scrollToSid)
        } else {
          return id === scrollToSid
        }
      })

      const {current: persistence} = persistenceVariables
      let scrollTo
      if (scrollToSelected) {
        scrollTo =
          scrollToSid < persistence.lastScrolled ? index - 1 : index + 1
        scrollTo = index > ROWS_LENGTH - 2 || index === 0 ? index : scrollTo
        persistence.lastScrolled = scrollToSid
        return {scrollTo: scrollTo, position: position}
      }
      scrollTo = index >= 2 ? index - 2 : index === 0 ? 0 : index - 1
      scrollTo = index > ROWS_LENGTH - 8 ? index : scrollTo
      if (scrollTo > 0 || scrollTo < ROWS_LENGTH - 8) {
        //if the opened segments is too big for the view dont show the previous
        const scrollToHeight = rows[index]?.height ?? 0
        const segmentBefore1 = rows[index - 1]?.height ?? 0
        const segmentBefore2 = rows[index - 2]?.height ?? 0
        const totalHeight = segmentBefore1 + segmentBefore2 + scrollToHeight
        if (totalHeight > heightArea - 50) {
          if (scrollToHeight + segmentBefore1 < heightArea + 50) {
            return {scrollTo: index - 1, position: position}
          }
          return {scrollTo: index, position: position}
        }
      }
      return {scrollTo: scrollTo, position: position}
    }
    return {scrollTo: null, position: null}
  }, [rows, heightArea, scrollToSelected, scrollToSid])

  const renderedRange = useCallback((range) => {
    setStartIndex(range[0])
    setStopIndex(range[range.length - 1])
  }, [])

  const getSegmentRealHeight = useCallback(
    ({segment, previousSegment}) => {
      const container = document.createElement('div', {})
      const html = getSegmentStructure(segment.toJS(), isSideOpen)
      container.innerHTML = ReactDOMServer.renderToStaticMarkup(html)
      document.getElementById('outer').appendChild(container)
      const height = container.getElementsByTagName('section')[0].clientHeight
      container.parentNode.removeChild(container)

      const getBasicSize = ({segment, previousSegment}) => {
        let basicSize = 0
        // if is the first segment of a file, add the 43px of the file header
        const previousFileId = previousSegment
          ? previousSegment.get('id_file')
          : 0
        const isFirstSegment =
          files?.length &&
          parseInt(SegmentUtils.getSegmentFileId(segment.toJS())) ===
            parseInt(files[0].first_segment)
        const fileDivHeight = isFirstSegment ? 60 : 75
        const collectionDivHeight = isFirstSegment ? 35 : 50
        if (previousFileId !== SegmentUtils.getSegmentFileId(segment.toJS())) {
          basicSize += fileDivHeight
        }
        // if it's collection type add 42px of header
        if (
          persistenceVariables.current.segmentsWithCollectionType.indexOf(
            segment.get('sid'),
          ) !== -1
        ) {
          basicSize += collectionDivHeight
        }
        return basicSize
      }

      const realHeight = height + getBasicSize({segment, previousSegment})
      return realHeight > ROW_HEIGHT ? realHeight : ROW_HEIGHT
    },
    [isSideOpen, files],
  )

  const setBulkSelection = useCallback(
    (sid, fid) => {
      const lastSelectedSegmentSid = isUndefined(lastSelectedSegment)
        ? sid
        : lastSelectedSegment.sid

      const from = Math.min(sid, lastSelectedSegmentSid)
      const to = Math.max(sid, lastSelectedSegmentSid)

      SegmentActions.setBulkSelectionInterval(from, to, fid)
    },
    [lastSelectedSegment],
  )

  const onScroll = useCallback(() => {
    if (!listRef?.current || !essentialRows.length) return

    const scrollValue = listRef.current.scrollTop
    const scrollBottomValue =
      listRef.current.firstChild.offsetHeight -
      (scrollValue + listRef.current.offsetHeight)

    const {current: persistence} = persistenceVariables
    persistence.scrollDirectionTop = scrollValue < persistence.lastScrollTop
    if (scrollBottomValue < 700 && !persistence.scrollDirectionTop) {
      SegmentActions.getMoreSegments('after')
    } else if (
      scrollValue < 500 &&
      persistence.scrollDirectionTop &&
      essentialRows.length !==
        persistence.currentSegmentsNumberBeforeGetMoreSegments
    ) {
      SegmentActions.getMoreSegments('before')
      persistence.currentSegmentsNumberBeforeGetMoreSegments =
        essentialRows.length
    }
    persistence.lastScrollTop = scrollValue
    setScrollTopVisible(scrollValue > 400)
  }, [essentialRows])

  // segments details - ex. div collection type ecc.
  const segmentsDetails = useMemo(() => {
    const getCollectionType = (segment) => {
      let collectionType
      if (segment.notes) {
        segment.notes.forEach(function (item) {
          if (item.note && item.note !== '') {
            if (item.note.indexOf('Collection Name: ') !== -1) {
              let split = item.note.split(': ')
              if (split.length > 1) {
                collectionType = split[1]
              }
            }
          }
        })
      }
      return collectionType
    }

    //
    let currentFileId = 0
    const collectionsTypeArray = []

    return new Array(segments.size).fill({}).map((item, index) => {
      const segImmutable = segments.get(index)

      const cached = cachedSegmentsToJS.current.get(segImmutable.get('sid'))
      const segment =
        cached && segImmutable.equals(cached.segImmutable)
          ? cached.segment
          : segImmutable.toJS()

      cachedSegmentsToJS.current.set(segment.sid, {
        segImmutable,
        segment,
        previousSegmentId: segments.get(index - 1)?.get('sid'),
        nextSegmentId: segments.get(index + 1)?.get('sid'),
      })

      const collectionType = getCollectionType(segment)
      let collectionTypeSeparator
      if (
        collectionType &&
        collectionsTypeArray.indexOf(collectionType) === -1
      ) {
        let classes = isSideOpen ? 'slide-right' : ''
        const isFirstSegment =
          files?.length &&
          parseInt(segment.sid) === parseInt(files[0].first_segment)
        classes = isFirstSegment ? classes + ' first-segment' : classes
        collectionTypeSeparator = (
          <div
            className={'collection-type-separator ' + classes}
            key={collectionType + segment.sid + Math.random() * 10}
          >
            Collection Name: <b>{collectionType}</b>
          </div>
        )
        collectionsTypeArray.push(collectionType)
        const {segmentsWithCollectionType} = persistenceVariables.current
        if (segmentsWithCollectionType.indexOf(segment.sid) === -1) {
          segmentsWithCollectionType.push(segment.sid)
        }
      }
      const props = {
        sid: segImmutable.get('sid'),
        currentFileId,
        collectionTypeSeparator,
      }
      currentFileId = SegmentUtils.getSegmentFileId(segment)
      return props
    })
  }, [files, isSideOpen, segments])

  // return row height and checks if it have margin
  const getRowHeightWithMargin = useCallback(({id, height}) => {
    const {segment, nextSegmentId} = cachedSegmentsToJS.current.get(id)
    const {segment: nextSegment} =
      cachedSegmentsToJS.current.get(nextSegmentId) ?? {}

    return segment.internal_id !== nextSegment?.internal_id
      ? height + ROW_MARGIN
      : height
  }, [])

  // set width and height of area
  useEffect(() => {
    const onWindowResize = () => {
      const headerHeight =
        document.getElementsByTagName('header')[0].offsetHeight
      const footerHeight =
        document.getElementsByTagName('footer')[0].offsetHeight

      setHeightArea(window.innerHeight - (headerHeight + footerHeight))
    }

    onWindowResize()
    window.addEventListener('resize', onWindowResize)

    return () => window.removeEventListener('resize', onWindowResize)
  }, [])

  // add actions listener
  useEffect(() => {
    let wasRemovedAllSegments = false

    const renderSegments = (segments) => {
      if (!segments.size) return
      setSegments(segments)
      if (wasRemovedAllSegments) {
        wasRemovedAllSegments = false
        setRows([])
        setEssentialRows([])
      }
    }
    const removeAllSegments = () => (wasRemovedAllSegments = true)
    const scrollToSegment = (sid) => {
      persistenceVariables.current.lastScrolled = sid
      setScrollToSid(sid)
      setScrollToSelected(false)
    }
    const scrollToSelectedSegment = (sid) => {
      setScrollToSid(sid)
      setScrollToSelected(true)
    }
    const openSide = () => setIsSideOpen(true)
    const closeSide = () => setIsSideOpen(false)
    const storeJobInfo = (files) => setFiles(files)
    const onAddComment = (sid) => setAddedComment({sid})
    const toggleSearchBar = (container) =>
      container === 'search' && setIsSearchBarOpen((prevState) => !prevState)
    const closeSubHeader = () => setIsSearchBarOpen(false)

    const sseConnection = (clientId) => {
      setClientConnected(!!clientId)
      setClientId(clientId)
    }

    const mousedownHandler = () =>
      (persistenceVariables.current.isUserDraggingCursor = true)
    const mouseupHandler = () =>
      (persistenceVariables.current.isUserDraggingCursor = false)

    SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, renderSegments)
    SegmentStore.addListener(
      SegmentConstants.REMOVE_ALL_SEGMENTS,
      removeAllSegments,
    )
    SegmentStore.addListener(
      SegmentConstants.SCROLL_TO_SEGMENT,
      scrollToSegment,
    )
    SegmentStore.addListener(
      SegmentConstants.SCROLL_TO_SELECTED_SEGMENT,
      scrollToSelectedSegment,
    )
    SegmentStore.addListener(SegmentConstants.OPEN_SIDE, openSide)
    SegmentStore.addListener(SegmentConstants.CLOSE_SIDE, closeSide)
    CatToolStore.addListener(CatToolConstants.STORE_FILES_INFO, storeJobInfo)
    CommentsStore.addListener(CommentsConstants.ADD_COMMENT, onAddComment)
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, toggleSearchBar)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeSubHeader)
    CatToolStore.addListener(CatToolConstants.CLIENT_CONNECT, sseConnection)

    document.addEventListener('mousedown', mousedownHandler)
    document.addEventListener('mouseup', mouseupHandler)
    return () => {
      SegmentStore.removeListener(
        SegmentConstants.RENDER_SEGMENTS,
        renderSegments,
      )
      SegmentStore.removeListener(
        SegmentConstants.REMOVE_ALL_SEGMENTS,
        removeAllSegments,
      )
      SegmentStore.removeListener(
        SegmentConstants.SCROLL_TO_SEGMENT,
        scrollToSegment,
      )
      SegmentStore.removeListener(
        SegmentConstants.SCROLL_TO_SELECTED_SEGMENT,
        scrollToSelectedSegment,
      )
      SegmentStore.removeListener(SegmentConstants.OPEN_SIDE, openSide)
      SegmentStore.removeListener(SegmentConstants.CLOSE_SIDE, closeSide)
      CatToolStore.removeListener(
        CatToolConstants.STORE_FILES_INFO,
        storeJobInfo,
      )
      CommentsStore.removeListener(CommentsConstants.ADD_COMMENT, onAddComment)
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_CONTAINER,
        toggleSearchBar,
      )
      CatToolStore.removeListener(
        CatToolConstants.CLOSE_SUBHEADER,
        closeSubHeader,
      )
      CatToolStore.removeListener(
        CatToolConstants.CLIENT_CONNECT,
        sseConnection,
      )

      document.removeEventListener('mousedown', mousedownHandler)
      document.removeEventListener('mouseup', mouseupHandler)
    }
  }, [])

  // set list rows
  useEffect(() => {
    const haveSegmentsChanges = !!segments.find((segment, index) => {
      const previousSegment = rows[index]?.segImmutable
      return previousSegment?.get('opened') !== segment.get('opened')
    })

    if (!segments || !haveSegmentsChanges) return
    if (segments.size !== rows.length) setHasCachedRows(false)
    setRows(
      new Array(segments.size).fill({}).map((item, index) => {
        const newestSegment = segments.get(index)
        const newestSid = newestSegment.get('sid')
        const hasRendered = !!rowsRenderedHeight.current.get(newestSid)
        const cachedHeight = newestSegment.get('opened')
          ? rowsRenderedHeight.current.get(newestSid)
          : cachedRowsHeightMap.current.get(newestSid)
        const prevStateRow = cachedHeight
          ? {height: cachedHeight, hasRendered}
          : {
              height: getRowHeightWithMargin({
                id: newestSid,
                height: ROW_HEIGHT,
              }),
              hasRendered: false,
            }
        return {
          id: newestSid,
          height: prevStateRow?.height,
          hasRendered: prevStateRow?.hasRendered,
          segImmutable: newestSegment,
        }
      }),
    )
  }, [segments, rows, getRowHeightWithMargin])

  // cache rows before start index
  useEffect(() => {
    if (!rows.length || hasCachedRows) return
    const stopIndexFromStartSegmentId = rows.findIndex(
      ({id}) => id === startSegmentId,
    )

    const stopIndex = !essentialRows.length
      ? // At the first time i get index from the startSegmentId prop
        stopIndexFromStartSegmentId >= 0
        ? stopIndexFromStartSegmentId
        : 0
      : // Was added more segments before and i get index by first row of essentialRows
        essentialRows[0]?.id !== rows[0]?.id
        ? rows.findIndex(({id}) => id === essentialRows[0]?.id)
        : // Was added more segments before and i get index by essentialRows length
          essentialRows[essentialRows.length - 1]?.id !==
            rows[rows.length - 1]?.id
          ? essentialRows.length - 1
          : // default start index of virtual list component
            startIndex

    rows
      .filter(
        ({segImmutable}, index) =>
          index <= stopIndex || segImmutable.get('opened'),
      )
      .forEach((row, index) => {
        const cached = cachedRowsHeightMap.current.get(row.id)
        const previousSegment =
          index > 0 ? rows[index - 1].segImmutable : undefined
        const newHeight = !cached
          ? getRowHeightWithMargin({
              id: row.id,
              height: getSegmentRealHeight({
                segment: row.segImmutable,
                previousSegment,
              }),
            })
          : cached
        cachedRowsHeightMap.current.set(row.id, newHeight)
      })

    setHasCachedRows(true)
  }, [
    rows,
    essentialRows,
    startSegmentId,
    hasCachedRows,
    startIndex,
    getSegmentRealHeight,
    getRowHeightWithMargin,
  ])

  // adapt scroll when was added more segments before
  useLayoutEffect(() => {
    if (!rows.length || !essentialRows.length || !hasCachedRows) return
    const {current} = persistenceVariables

    const hasAddedSegmentsBefore =
      rows.length > essentialRows.length &&
      essentialRows[0]?.id !== rows[0]?.id &&
      rows[0]?.id !== config.first_job_segment
    if (!hasAddedSegmentsBefore || current.haveBeenAddedSegmentsBefore) return

    const stopIndex = rows.findIndex(({id}) => id === essentialRows[0].id)
    const additionalHeight = rows
      .filter((row, index) => index < stopIndex)
      .reduce((acc, {id}) => acc + cachedRowsHeightMap.current.get(id), 0)

    // set new height to content element
    const contentElement = listRef.current.firstChild
    contentElement.style.height = `${
      contentElement.offsetHeight + additionalHeight
    }px`

    const defaultRowHeight = getRowHeightWithMargin({
      id: essentialRows[0].id,
      height: ROW_HEIGHT,
    })

    cachedRowsHeightMap.current.set(essentialRows[0].id, defaultRowHeight)

    const difference = essentialRows[0].height - defaultRowHeight

    for (let i = 0; i < contentElement.children.length; i++) {
      const rowElement = contentElement.children[i]
      const translateY = parseFloat(
        rowElement.style.transform.substring(
          rowElement.style.transform.indexOf('(') + 1,
          rowElement.style.transform.indexOf('px'),
        ),
      )
      rowElement.style.transform = `translateY(${
        translateY + additionalHeight - (i > 0 ? difference : 0)
      }px)`
    }

    const scrollTop = additionalHeight
    listRef.current.scrollTop = scrollTop

    current.haveBeenAddedSegmentsBefore = true
  }, [rows, essentialRows, hasCachedRows, getRowHeightWithMargin])

  // updating rows height
  useEffect(() => {
    if (startIndex === undefined || !stopIndex || !hasCachedRows) return
    setRows((prevState) => {
      // update with new height
      const nextState = prevState.map((row, index) =>
        rowsRenderedHeight.current.get(row.id)
          ? {
              ...row,
              height:
                index < startIndex && !row.segImmutable.get('opened')
                  ? cachedRowsHeightMap.current.get(row.id)
                  : rowsRenderedHeight.current.get(row.id),
              hasRendered: true,
            }
          : {
              ...row,
              height: cachedRowsHeightMap.current.get(row.id) ?? row.height,
            },
      )
      // get rendered rows
      const rowsRendered = nextState.filter(
        (row, index) => index >= startIndex && index <= stopIndex,
      )
      rowsRendered.forEach(
        ({id, height, segImmutable}) =>
          !segImmutable.get('opened') &&
          cachedRowsHeightMap.current.set(id, height),
      )
      // }
      return nextState
    })
  }, [startIndex, stopIndex, hasCachedRows, onUpdateRow])

  // set essential rows of virtual list component
  useEffect(() => {
    const {haveBeenAddedSegmentsBefore, isUserDraggingCursor} =
      persistenceVariables.current
    if (
      !hasCachedRows ||
      (startIndex === 0 && haveBeenAddedSegmentsBefore && !isUserDraggingCursor)
    )
      return
    if (essentialRows.length !== rows.length) {
      setEssentialRows(
        rows.map(({id, height, hasRendered}) => ({
          id,
          height: rowsRenderedHeight.current.get(id)
            ? rowsRenderedHeight.current.get(id)
            : (cachedRowsHeightMap.current.get(id) ?? height),
          hasRendered,
        })),
      )
    }
    if (rows.length && essentialRows.length === rows.length) {
      const haveSameHeight = essentialRows.every(
        ({height}, index) => height === rows[index].height,
      )
      if (haveSameHeight) return

      const rowsRendered = rows.filter(
        (row, index) => index >= startIndex && index <= stopIndex,
      )
      const haveBeenRowsRendered =
        rowsRendered.every(({hasRendered}) => hasRendered) &&
        rowsRendered.length
      if (!haveBeenRowsRendered) return

      setEssentialRows(
        rows.map(({id, height, hasRendered}) => ({id, height, hasRendered})),
      )
    }
  }, [rows, essentialRows, hasCachedRows, startIndex, stopIndex])

  // set padding top to list ref (Comments padding or Search bar opened)
  useEffect(() => {
    if (!segments.size || !listRef?.current) return
    const getPadding = () => {
      if (isSideOpen) {
        const segment1 = segments.get(0)
        const segment2 = segments.get(1)
        const segment3 = segments.get(2)

        const [paddingSegment1, paddingSegment2, paddingSegment3] =
          COMMENTS_PADDING_TOP

        if (segment1.get('openComments')) {
          const comments = CommentsStore.getCommentsBySegment(
            segment1.get('original_sid'),
          )
          if (comments.length === 0) return paddingSegment1.empty
          else if (comments.length > 0) return paddingSegment1.filled
        } else if (segment2 && segment2.get('openComments')) {
          const comments = CommentsStore.getCommentsBySegment(
            segment2.get('original_sid'),
          )
          if (comments.length === 0) return paddingSegment2.empty
          else if (comments.length > 0) return paddingSegment2.filled
        } else if (segment3 && segment3.get('openComments')) {
          const comments = CommentsStore.getCommentsBySegment(
            segment3.get('original_sid'),
          )
          if (comments.length > 0) return paddingSegment3.filled
        }
      }
      return 0
    }
    // padding top when search bar is open
    const paddingTopSearchBarOpened = isSearchBarOpen
      ? SEARCH_BAR_OPENED_PADDING_TOP
      : 0
    // set inline style
    listRef.current.firstChild.style.marginTop = `${
      getPadding() + paddingTopSearchBarOpened
    }px`
  }, [isSideOpen, segments, addedComment, isSearchBarOpen])

  // reset scrollTo
  useEffect(() => {
    if (!rows.length || !hasCachedRows) return
    const rowsRendered = rows.filter(
      (row, index) => index >= startIndex && index <= stopIndex,
    )
    const haveBeenRowsRendered =
      rowsRendered.every(({hasRendered}) => hasRendered) && rowsRendered.length
    if (!haveBeenRowsRendered) return

    setScrollToSid(undefined)
    persistenceVariables.current.haveBeenAddedSegmentsBefore = false
  }, [rows, essentialRows, hasCachedRows, startIndex, stopIndex])

  // single segment props to move down RowSegment component
  const getSegmentPropsBySid = (sid) => {
    const details = segmentsDetails.find(
      ({sid: iteratedSid}) => iteratedSid === sid,
    )
    if (!details) return
    const {currentFileId, collectionTypeSeparator} = details
    const {segment, segImmutable, previousSegmentId, nextSegmentId} =
      cachedSegmentsToJS.current.get(sid)
    const {segment: previousSegment} =
      cachedSegmentsToJS.current.get(previousSegmentId) ?? {}
    const {segment: nextSegment} =
      cachedSegmentsToJS.current.get(nextSegmentId) ?? {}

    return {
      segment,
      segImmutable,
      isReview,
      speech2textEnabledFn: Speech2Text.enabled,
      setLastSelectedSegment: (sid) => setLastSelectedSegment({sid}),
      setBulkSelection,
      sideOpen: isSideOpen,
      files: files,
      currentFileId: currentFileId ? currentFileId.toString() : '0',
      collectionTypeSeparator,
      guessTagActive,
      speechToTextActive,
      multiMatchLangs,
      previousSegment,
      nextSegment,
    }
  }

  const goToFirstSegment = () => SegmentActions.scrollToSegment(firstJobSegment)

  return (
    <>
      <VirtualList
        ref={listRef}
        className="virtual-list"
        items={essentialRows}
        scrollToIndex={{
          value: scrollToParams.scrollTo,
          align: scrollToParams.position,
        }}
        overscan={OVERSCAN}
        height={heightArea}
        onRender={(index) => {
          const props = getSegmentPropsBySid(essentialRows[index].id)
          return (
            props && (
              <RowSegment
                {...{
                  onChangeRowHeight,
                  ...essentialRows[index],
                  ...props,
                  clientConnected,
                  clientId,
                  ...(index === essentialRows.length - 1 && {
                    isLastRow: true,
                  }),
                }}
              />
            )
          )
        }}
        onScroll={onScroll}
        itemStyle={(index) =>
          segments.get(index) &&
          segments.get(index).get('opened') && {zIndex: 1}
        }
        renderedRange={renderedRange}
      />
      {scrollTopVisible && (
        <div
          className={'pointer-first-segment'}
          title="Go to first segment"
          onClick={goToFirstSegment}
        ></div>
      )}
    </>
  )
}

SegmentsContainer.propTypes = {
  isReview: PropTypes.bool,
  startSegmentId: PropTypes.string,
  firstJobSegment: PropTypes.string,
}

export default SegmentsContainer

// Segment structure placeholder
const getSegmentStructure = (segment, sideOpen) => {
  let source = segment.segment
  let target = segment.translation
  if (SegmentUtils.checkCurrentSegmentTPEnabled(segment)) {
    source = DraftMatecatUtils.removeTagsFromText(source)
    target = DraftMatecatUtils.removeTagsFromText(target)
  }

  source = DraftMatecatUtils.transformTagsToHtml(source, config.isSourceRTL)
  target = DraftMatecatUtils.transformTagsToHtml(target, config.isTargetRTL)

  return (
    <section
      className={`status-draft ${sideOpen ? 'slide-right' : ''}`}
      ref={(section) => (this.section = section)}
    >
      <div className="sid">
        <div className="txt">0000000</div>
        <div className="txt segment-add-inBulk">
          <input type="checkbox" />
        </div>
        <div className="actions">
          <button className="split" title="Click to split segment">
            <i className="icon-split"> </i>
          </button>
          <p className="split-shortcut">CTRL + S</p>
        </div>
      </div>

      <div className="body">
        <div className="header toggle"></div>
        <div
          className="text segment-body-content"
          style={{boxSizing: 'content-box'}}
        >
          <div className="wrap">
            <div className="outersource">
              <div
                className="source item"
                tabIndex="0"
                dangerouslySetInnerHTML={{__html: source}}
              />
              <div className="copy" title="Copy source to target">
                <a href="#"> </a>
                <p>CTRL+I</p>
              </div>
              <div className="target item">
                <div className="textarea-container">
                  <div
                    className="targetarea editarea"
                    spellCheck="true"
                    dangerouslySetInnerHTML={{__html: target}}
                  />
                  <div className="toolbar">
                    <a
                      className="revise-qr-link"
                      title="Segment Quality Report."
                      target="_blank"
                      href="#"
                    >
                      QR
                    </a>
                    <a
                      href="#"
                      className="autofillTag"
                      title={`Copy missing tags from source to target (${Shortcuts.cattol.events.addTags.keystrokes[Shortcuts.shortCutsKeyType]})`}
                    >
                      {' '}
                    </a>
                    <ul className="editToolbar">
                      <li className="uppercase" title="Uppercase">
                        {' '}
                      </li>
                      <li className="lowercase" title="Lowercase">
                        {' '}
                      </li>
                      <li className="capitalize" title="Capitalized">
                        {' '}
                      </li>
                    </ul>
                  </div>
                </div>
                <p className="warnings"></p>
                <ul className="buttons toggle">
                  <li>
                    <a href="#" className="translated">
                      {' '}
                      Translated{' '}
                    </a>
                    <p>CTRL+ENTER</p>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div className="status-container">
            <a href="#" className="status no-hover">
              {' '}
            </a>
          </div>
        </div>
        <div className="edit-distance">Edit Distance:</div>
      </div>
      <div className="segment-side-buttons">
        <div
          data-mount="translation-issues-button"
          className="translation-issues-button"
        >
          {' '}
        </div>
      </div>
      <div className="segment-side-container"></div>
    </section>
  )
}
