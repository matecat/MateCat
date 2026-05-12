import React, {
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from 'react'
import PropTypes from 'prop-types'
import Segment from '../../../segments/Segment'
import useResizeObserver from '../../../../hooks/useResizeObserver'
import CommonUtils from '../../../../utils/commonUtils'
import JobMetadataModal from '../../../modals/JobMetadataModal'
import CatToolStore from '../../../../stores/CatToolStore'
import ModalsActions from '../../../../actions/ModalsActions'
import SegmentUtils from '../../../../utils/segmentUtils'
import {se} from 'make-plural'
import {head} from 'lodash'
import {is} from 'immutable'
import SegmentStore from '../../../../stores/SegmentStore'

const LinkIcon = () => {
  return (
    <svg
      width="15"
      height="15"
      viewBox="0 0 17 17"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M10.5604 1.10486C11.2679 0.397428 12.2273 0 13.2278 0C15.3111 0 17 1.68888 17 3.77222C17 4.77267 16.6026 5.73215 15.8951 6.43958L12.3007 10.034L11.4993 9.23264L15.0938 5.63819C15.5886 5.1433 15.8667 4.47209 15.8667 3.77222C15.8667 2.3148 14.6852 1.13333 13.2278 1.13333C12.5279 1.13333 11.8567 1.41136 11.3618 1.90624L7.76736 5.50069L6.96597 4.69931L10.5604 1.10486ZM12.3007 5.50069L5.50069 12.3007L4.69931 11.4993L11.4993 4.69931L12.3007 5.50069ZM5.50069 7.76736L1.90624 11.3618C1.41136 11.8567 1.13333 12.5279 1.13333 13.2278C1.13333 14.6852 2.3148 15.8667 3.77222 15.8667C4.47209 15.8667 5.1433 15.5886 5.63819 15.0938L9.23264 11.4993L10.034 12.3007L6.43958 15.8951C5.73215 16.6026 4.77267 17 3.77222 17C1.68888 17 0 15.3111 0 13.2278C0 12.2273 0.397429 11.2678 1.10486 10.5604L4.69931 6.96597L5.50069 7.76736Z"
        fill="#F2F2F2"
      />
    </svg>
  )
}

function RowSegment({
  id,
  height,
  onChangeRowHeight,
  hasRendered,
  isLastRow = false,
  currentFileId,
  collectionTypeSeparator,
  previousSegment,
  nextSegment,
  ...restProps
}) {
  const ref = useRef()
  const {height: newHeight} = useResizeObserver(ref)

  useEffect(() => {
    if (!newHeight || (newHeight === height && hasRendered)) return
    onChangeRowHeight(id, newHeight)
  }, [id, newHeight, height, hasRendered, onChangeRowHeight])

  const isFirstSegmentOfGroup =
    previousSegment?.internal_id !== restProps.segment.internal_id &&
    restProps.segment.internal_id === nextSegment?.internal_id

  const isLastSegmentOfGroup =
    nextSegment?.internal_id !== restProps.segment.internal_id &&
    restProps.segment.internal_id === previousSegment?.internal_id

  const borderRadiusCssClasses =
    previousSegment?.internal_id !== restProps.segment.internal_id &&
    nextSegment?.internal_id !== restProps.segment.internal_id
      ? 'row-border-radius-top row-border-radius-bottom'
      : isFirstSegmentOfGroup && !isLastSegmentOfGroup
        ? 'row-border-radius-top'
        : isLastSegmentOfGroup && !isFirstSegmentOfGroup
          ? 'row-border-radius-bottom'
          : ''

  const idFileSegment = SegmentUtils.getSegmentFileId(restProps.segment)

  return (
    <div
      ref={ref}
      className={`row${isLastRow ? ' last-row' : ''} ${borderRadiusCssClasses}`}
    >
      {idFileSegment !== parseInt(currentFileId) && (
        <ProjectBar {...restProps} />
      )}
      {collectionTypeSeparator}
      <Segment {...restProps} />
    </div>
  )
}

RowSegment.propTypes = {
  id: PropTypes.string.isRequired,
  height: PropTypes.number.isRequired,
  onChangeRowHeight: PropTypes.func.isRequired,
  hasRendered: PropTypes.bool,
  isLastRow: PropTypes.bool,
  currentFileId: PropTypes.string,
  collectionTypeSeparator: PropTypes.node,
}

export default RowSegment

const projectBarCache = {
  headerHeight: 0,
  paddingTop: 0,
}

export const ProjectBar = ({
  segment,
  files,
  sideOpen,
  isSticky,
  listRef,
  scrollValue,
}) => {
  const [replacedSegment, setReplacedSegment] = useState()
  const [isBlinking, setIsBlinking] = useState(false)

  const ref = useRef()
  const previousFileIdRef = useRef()

  const openInstructionsModal = (id_file) => {
    const props = {
      showCurrent: true,
      files: CatToolStore.getJobFilesInfo(),
      currentFile: id_file,
    }
    const styleContainer = {
      minWidth: 600,
      minHeight: 400,
      maxWidth: 900,
    }
    ModalsActions.showModalComponent(
      JobMetadataModal,
      props,
      'File notes',
      styleContainer,
    )
  }

  useEffect(() => {
    const onReplaceSegment = ({detail: {segment}}) => {
      if (isSticky) setReplacedSegment(segment)
    }
    const onReplaceSegmentReset = ({detail: {sid}}) => {
      if (isSticky)
        setReplacedSegment((prevState) =>
          prevState?.sid === sid ? undefined : prevState,
        )
    }

    document.addEventListener(
      'segmentProjectBar.replaceSegment',
      onReplaceSegment,
    )
    document.addEventListener(
      'segmentProjectBar.replaceSegmentReset',
      onReplaceSegmentReset,
    )

    if (!isSticky) {
      const rect = ref.current?.getBoundingClientRect() ?? {}
      const isVisible =
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= window.innerHeight &&
        rect.right <= window.innerWidth

      if (isVisible) {
        if (!projectBarCache.headerHeight) {
          projectBarCache.headerHeight =
            document.getElementsByTagName('header')[0]?.getBoundingClientRect()
              .height || 0
        }

        if (!projectBarCache.paddingTop && ref.current) {
          projectBarCache.paddingTop = parseInt(
            window.getComputedStyle(ref.current).paddingTop.replace('px', ''),
          )
        }

        const diff =
          rect.y - (projectBarCache.headerHeight + projectBarCache.paddingTop)

        if (diff < -30 && diff > -60) {
          document.dispatchEvent(
            new CustomEvent('segmentProjectBar.replaceSegment', {
              detail: {segment},
            }),
          )
        } else {
          document.dispatchEvent(
            new CustomEvent('segmentProjectBar.replaceSegmentReset', {
              detail: {sid: segment.sid},
            }),
          )
        }
      }
    }

    return () => {
      document.removeEventListener(
        'segmentProjectBar.replaceSegment',
        onReplaceSegment,
      )
      document.removeEventListener(
        'segmentProjectBar.replaceSegmentReset',
        onReplaceSegmentReset,
      )
    }
  }, [isSticky, segment, scrollValue])

  const currentSegment = replacedSegment || segment

  const idFileSegment = SegmentUtils.getSegmentFileId(currentSegment)
  const file = files ? files.find((file) => file.id == idFileSegment) : false
  let fileType = ''
  if (file) {
    fileType = file.file_name ? file.file_name.split('.')[1] : ''
    //check metadata for jsont2 files
    fileType = file.metadata?.['data-type']
      ? file.metadata?.['data-type']
      : fileType
  }
  let classes = sideOpen ? 'slide-right' : ''
  const isFirstSegment =
    files?.length &&
    parseInt(currentSegment.sid) === parseInt(files[0].first_segment)
  classes = isFirstSegment ? classes + ' first-segment' : classes

  useEffect(() => {
    const stopBlinking = () => setIsBlinking(false)
    const filenameElement = ref.current.firstChild

    if (
      isSticky &&
      idFileSegment !== previousFileIdRef.current &&
      typeof previousFileIdRef.current === 'number'
    ) {
      filenameElement.addEventListener('animationend', stopBlinking)
      setIsBlinking(true)
    }

    previousFileIdRef.current = idFileSegment

    return () => {
      filenameElement.removeEventListener('animationend', stopBlinking)
      stopBlinking()
    }
  }, [isSticky, idFileSegment])

  useEffect(() => {
    let paddingTop = 0

    const onScroll = () => {
      if (listRef.scrollTop < paddingTop) {
        const newPaddingTop = Math.floor(paddingTop - listRef.scrollTop)
        if (newPaddingTop > 10)
          ref.current.style.paddingTop = `${newPaddingTop}px`
      } else {
        ref.current.style.paddingTop = '14px'
      }
    }

    if (isSticky) {
      paddingTop = parseInt(
        window.getComputedStyle(ref.current).paddingTop.replace('px', ''),
      )
      listRef?.addEventListener('scroll', onScroll)
    }

    return () => listRef?.removeEventListener('scroll', onScroll)
  }, [isSticky, listRef])

  return (
    <div ref={ref} className={'projectbar ' + classes}>
      {file ? (
        <div
          className={`projectbar-filename ${isBlinking ? 'sticky-project-bar-blink' : ''}`}
        >
          <span
            title={currentSegment.filename}
            className={'fileFormat ' + CommonUtils.getIconClass(fileType)}
          >
            {file.file_name}
          </span>
        </div>
      ) : null}
      {file && file.weighted_words > 0 ? (
        <div
          className={`projectbar-wordcounter ${isBlinking ? 'sticky-project-bar-blink sticky-project-bar-blink-wordcounter' : ''}`}
        >
          <span>
            Payable Words: <strong>{file.weighted_words}</strong>
          </span>
        </div>
      ) : null}
      {CommonUtils.fileHasInstructions(file) ? (
        <div
          className={'button-notes'}
          onClick={() => openInstructionsModal(idFileSegment)}
        >
          <LinkIcon />
          <span>View notes</span>
        </div>
      ) : null}
    </div>
  )
}
