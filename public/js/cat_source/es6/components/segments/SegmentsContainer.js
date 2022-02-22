import React, {
  createContext,
  createRef,
  useCallback,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import Immutable from 'immutable'
import ReactDOMServer from 'react-dom/server'
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
import TagUtils from '../../utils/tagUtils'
import SegmentUtils from '../../utils/segmentUtils'
import CommentsStore from '../../stores/CommentsStore'

const ROW_HEIGHT = 90
const OVERSCAN = 5
const COMMENTS_PADDING_TOP = [
  {empty: 110, filled: 270},
  {empty: 40, filled: 140},
  {filled: 50},
]

export const SegmentsContext = createContext({})
const listRef = createRef()

function SegmentsContainer({
  isReview,
  isReviewExtended,
  reviewType,
  enableTagProjection,
  tagModesEnabled,
  startSegmentId,
  firstJobSegment,
}) {
  const [segments, setSegments] = useState(Immutable.fromJS([]))
  const [rows, setRows] = useState([])
  const [essentialRows, setEssentialRows] = useState([])
  const [hasCachedRows, setHasCachedRows] = useState(false)
  const [onUpdateRow, setOnUpdateRow] = useState(undefined)
  const [widthArea, setWidthArea] = useState(0)
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

  const persistenceVariables = useRef({
    lastScrolled: undefined,
    scrollDirectionTop: false,
    lastScrollTop: 0,
    segmentsWithCollectionType: [],
    previousWidthArea: widthArea,
    haveBeenAddedSegmentsBefore: false,
  })
  const rowsRenderedHeight = useRef(new Map())
  const cachedRowsHeightMap = useRef(new Map())

  const onChangeRowHeight = useCallback((id, newHeight) => {
    rowsRenderedHeight.current.set(id, newHeight)
    setOnUpdateRow(Symbol())
  }, [])

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

      const {current: persistance} = persistenceVariables
      let scrollTo
      if (scrollToSelected) {
        scrollTo =
          scrollToSid < persistance.lastScrolled ? index - 1 : index + 1
        scrollTo = index > ROWS_LENGTH - 2 || index === 0 ? index : scrollTo
        persistance.lastScrolled = scrollToSid
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
      // console.log('----> get height', segment.get('sid'))
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
          files && segment.get('sid') === files[0].first_segment
        const fileDivHeight = isFirstSegment ? 60 : 75
        const collectionDivHeight = isFirstSegment ? 35 : 50
        if (previousFileId !== segment.get('id_file')) {
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
    if (!listRef?.current || scrollToSid) return

    const scrollValue = listRef.current.scrollTop
    const scrollBottomValue =
      listRef.current.firstChild.offsetHeight -
      (scrollValue + listRef.current.offsetHeight)

    const {current: persistance} = persistenceVariables
    persistance.scrollDirectionTop = scrollValue < persistance.lastScrollTop
    if (scrollBottomValue < 700 && !persistance.scrollDirectionTop) {
      UI.getMoreSegments('after')
    } else if (scrollValue < 500 && persistance.scrollDirectionTop) {
      UI.getMoreSegments('before')
    }
    persistance.lastScrollTop = scrollValue
    setScrollTopVisible(scrollValue > 400)
  }, [scrollToSid])

  // segments props
  const segmentsProps = useMemo(() => {
    const getSegmentProps = (
      segment,
      segImmutable,
      currentFileId,
      collectionTypeSeparator,
    ) => {
      return {
        segment,
        segImmutable,
        timeToEdit: config.time_to_edit_enabled,
        isReview,
        isReviewExtended: !!isReviewExtended,
        reviewType,
        enableTagProjection,
        tagModesEnabled,
        speech2textEnabledFn: Speech2Text.enabled,
        setLastSelectedSegment: (sid) => setLastSelectedSegment({sid}),
        setBulkSelection,
        sideOpen: isSideOpen,
        files: files,
        currentFileId: currentFileId.toString(),
        collectionTypeSeparator,
      }
    }

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
      const segment = segImmutable.toJS()
      const collectionType = getCollectionType(segment)
      let collectionTypeSeparator
      if (
        collectionType &&
        collectionsTypeArray.indexOf(collectionType) === -1
      ) {
        let classes = isSideOpen ? 'slide-right' : ''
        const isFirstSegment = files && segment.sid === files[0].first_segment
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
      const segmentProps = getSegmentProps(
        segment,
        segImmutable,
        currentFileId,
        collectionTypeSeparator,
      )
      currentFileId = segment.id_file
      return segmentProps
    })
  }, [
    enableTagProjection,
    files,
    isReview,
    isReviewExtended,
    isSideOpen,
    reviewType,
    segments,
    setBulkSelection,
    tagModesEnabled,
  ])

  // set width and height of area
  useEffect(() => {
    const onWindowResize = () => {
      const headerHeight =
        document.getElementsByTagName('header')[0].offsetHeight
      const footerHeight =
        document.getElementsByTagName('footer')[0].offsetHeight

      setWidthArea(window.innerWidth)
      setHeightArea(window.innerHeight - (headerHeight + footerHeight))
    }

    onWindowResize()
    window.addEventListener('resize', onWindowResize)

    return () => window.removeEventListener('resize', onWindowResize)
  }, [])

  // add actions listener
  useEffect(() => {
    const renderSegments = (segments) => setSegments(segments)
    const updateAllSegments = () => {}
    const scrollToSegment = (sid) => {
      persistenceVariables.current.lastScrolled = sid
      setScrollToSid(sid)
      setScrollToSelected(false)
      // setTimeout(() => this.onScroll(), 500)
    }
    const scrollToSelectedSegment = (sid) => {
      setScrollToSid(sid)
      setScrollToSelected(true)
      // setTimeout(() => this.onScroll(), 500)
    }
    const openSide = () => setIsSideOpen(true)
    const closeSide = () => setIsSideOpen(false)
    const storeJobInfo = (files) => setFiles(files)
    const onAddComment = (sid) => setAddedComment({sid})

    SegmentStore.addListener(SegmentConstants.RENDER_SEGMENTS, renderSegments)
    SegmentStore.addListener(
      SegmentConstants.UPDATE_ALL_SEGMENTS,
      updateAllSegments,
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

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.RENDER_SEGMENTS,
        renderSegments,
      )
      SegmentStore.removeListener(
        SegmentConstants.UPDATE_ALL_SEGMENTS,
        updateAllSegments,
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
    }
  }, [])

  // set list rows
  useEffect(() => {
    if (!segments) return
    setHasCachedRows(false)
    setRows((prevState) =>
      new Array(segments.size).fill({}).map((item, index) => {
        const prevStateRow = prevState.find(
          ({id}) => id === segments.get(index).get('sid'),
        )
        return {
          id: segments.get(index).get('sid'),
          height: prevStateRow?.height ?? ROW_HEIGHT,
          hasRendered: prevStateRow?.hasRendered ?? false,
          segImmutable: segments.get(index),
        }
      }),
    )
  }, [segments])

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
      essentialRows[essentialRows.length - 1]?.id !== rows[rows.length - 1]?.id
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
        const newHeight = !cached
          ? getSegmentRealHeight({
              segment: row.segImmutable,
              previousSegment:
                index > 0 ? rows[index - 1].segImmutable : undefined,
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
  ])

  // adapt scroll when was added more segments before
  useLayoutEffect(() => {
    if (!rows.length || !essentialRows.length || !hasCachedRows) return
    const {current} = persistenceVariables

    const hasAddedSegmentsBefore =
      rows.length > essentialRows.length && essentialRows[0]?.id !== rows[0]?.id
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

    cachedRowsHeightMap.current.set(essentialRows[0].id, ROW_HEIGHT)

    const difference = essentialRows[0].height - ROW_HEIGHT

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
  }, [rows, essentialRows, hasCachedRows])

  // updating rows height
  useEffect(() => {
    if (startIndex === undefined || !stopIndex || !hasCachedRows) return
    // console.log('startIndex', startIndex, 'stopIndex', stopIndex)
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
    if (
      !hasCachedRows ||
      (startIndex === 0 &&
        persistenceVariables.current.haveBeenAddedSegmentsBefore)
    )
      return
    if (essentialRows.length !== rows.length) {
      setEssentialRows(
        rows.map(({id, height, hasRendered}) => ({
          id,
          height: rowsRenderedHeight.current.get(id)
            ? rowsRenderedHeight.current.get(id)
            : cachedRowsHeightMap.current.get(id) ?? height,
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

  // useEffect(() => {
  //   console.log('essentialRows', essentialRows)
  // }, [essentialRows])

  // set padding top to list ref (Comments padding)
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
    // set inline style
    listRef.current.style.paddingTop = `${getPadding()}px`
  }, [isSideOpen, segments, addedComment])

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

  // useEffect(() => {
  //   console.log('####', scrollToSid)
  // }, [scrollToSid])

  const goToFirstSegment = () => SegmentActions.scrollToSegment(firstJobSegment)

  return (
    <SegmentsContext.Provider
      value={{onChangeRowHeight, minRowHeight: ROW_HEIGHT}}
    >
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
          width={widthArea}
          height={heightArea}
          onRender={(index) => (
            <RowSegment
              {...{
                ...essentialRows[index],
                ...segmentsProps.find(
                  ({segment}) => segment.sid === essentialRows[index].id,
                ),
                ...(index === essentialRows.length - 1 && {isLastRow: true}),
              }}
            />
          )}
          onScroll={onScroll}
          itemStyle={(index) =>
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
    </SegmentsContext.Provider>
  )
}

SegmentsContainer.propTypes = {
  isReview: PropTypes.bool,
  isReviewExtended: PropTypes.bool,
  reviewType: PropTypes.string,
  enableTagProjection: PropTypes.any,
  tagModesEnabled: PropTypes.bool,
  startSegmentId: PropTypes.string,
  firstJobSegment: PropTypes.string,
}

export default SegmentsContainer

// Segment structure placeholder
const getSegmentStructure = (segment, sideOpen) => {
  let source = segment.segment
  let target = segment.translation
  if (SegmentUtils.checkCurrentSegmentTPEnabled(segment)) {
    source = TagUtils.removeAllTags(source)
    target = TagUtils.removeAllTags(target)
  }

  source = TagUtils.matchTag(
    TagUtils.decodeHtmlInTag(
      TagUtils.decodePlaceholdersToTextSimple(source),
      config.isSourceRTL,
    ),
  )
  target = TagUtils.matchTag(
    TagUtils.decodeHtmlInTag(
      TagUtils.decodePlaceholdersToTextSimple(target),
      config.isSourceRTL,
    ),
  )

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
        <div className="header toggle"> </div>
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
                      className="tagModeToggle "
                      title="Display full/short tags"
                    >
                      <span className="icon-chevron-left"> </span>
                      <span className="icon-tag-expand"> </span>
                      <span className="icon-chevron-right"> </span>
                    </a>
                    <a
                      href="#"
                      className="autofillTag"
                      title="Copy missing tags from source to target"
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
                <p className="warnings"> </p>
                <ul className="buttons toggle">
                  <li>
                    <a href="#" className="translated">
                      {' '}
                      Translated{' '}
                    </a>
                    <p>CTRL ENTER</p>
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
        <div className="timetoedit" data-raw-time-to-edit="0">
          {' '}
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
      <div className="segment-side-container"> </div>
    </section>
  )
}
