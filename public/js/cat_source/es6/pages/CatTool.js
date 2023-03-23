import React, {useEffect, useRef, useState} from 'react'
import {CattolFooter} from '../components/footer/CattoolFooter'
import {Header} from '../components/header/cattol/Header'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import SegmentsContainer from '../components/segments/SegmentsContainer'
import CatToolStore from '../stores/CatToolStore'
import CatToolConstants from '../constants/CatToolConstants'
import Cookies from 'js-cookie'
import OfflineUtils from '../utils/offlineUtils'
import SegmentActions from '../actions/SegmentActions'
import CatToolActions from '../actions/CatToolActions'
import SegmentFilter from '../components/header/cattol/segment_filter/segment_filter'
import SegmentStore from '../stores/SegmentStore'
import SegmentConstants from '../constants/SegmentConstants'
import useSegmentsLoader from '../hooks/useSegmentsLoader'
import LXQ from '../utils/lxq.main'
import CommonUtils from '../utils/commonUtils'

function CatTool() {
  const [options, setOptions] = useState({})
  const [wasInitSegments, setWasInitSegments] = useState(false)
  const [isFreezingSegments, setIsFreezingSegments] = useState(false)

  const startSegmentIdRef = useRef(UI.startSegmentId)
  const callbackAfterSegmentsResponseRef = useRef()

  const {isLoading: isLoadingSegments, result: segmentsResult} =
    useSegmentsLoader({
      segmentId: options?.segmentId
        ? options?.segmentId
        : startSegmentIdRef.current,
      where: options?.where,
    })

  // actions listener
  useEffect(() => {
    // CatTool onRender action
    const onRenderHandler = (options) => {
      const {
        actionType, // eslint-disable-line
        startSegmentId,
        callbackAfterSegmentsResponse,
        ...restOptions
      } = options
      setOptions((prevState) => ({
        ...prevState,
        ...(options.segmentToOpen && {
          segmentId: Symbol(options.segmentToOpen),
        }),
        ...restOptions,
      }))
      if (startSegmentId) startSegmentIdRef.current = startSegmentId.toString()
      if (callbackAfterSegmentsResponse)
        callbackAfterSegmentsResponseRef.current = callbackAfterSegmentsResponse
    }
    CatToolStore.addListener(CatToolConstants.ON_RENDER, onRenderHandler)

    // segments action
    const freezingSegments = (isFreezing) => setIsFreezingSegments(isFreezing)
    const getMoreSegments = (where) => {
      const segmentId =
        where === 'after'
          ? SegmentStore.getLastSegmentId()
          : where === 'before'
          ? SegmentStore.getFirstSegmentId()
          : ''

      setOptions((prevState) => ({...prevState, segmentId, where}))
    }
    SegmentStore.addListener(
      SegmentConstants.FREEZING_SEGMENTS,
      freezingSegments,
    )
    SegmentStore.addListener(
      SegmentConstants.GET_MORE_SEGMENTS,
      getMoreSegments,
    )

    return () => {
      CatToolStore.removeListener(CatToolConstants.ON_RENDER, onRenderHandler)
      SegmentStore.removeListener(
        SegmentConstants.FREEZING_SEGMENTS,
        freezingSegments,
      )
      SegmentStore.removeListener(
        SegmentConstants.GET_MORE_SEGMENTS,
        getMoreSegments,
      )
    }
  }, [])

  // on mount dispatch some actions
  useEffect(() => {
    CatToolActions.onRender()
    $('html').trigger('start')
    if (LXQ.enabled()) LXQ.initPopup()
    CatToolActions.startNotifications()
    UI.splittedTranslationPlaceholder = '##$_SPLIT$##'
  }, [])

  // handle getSegments result
  useEffect(() => {
    if (!segmentsResult) return
    const {where, errors} = segmentsResult
    // Dispatch error get segments
    if (errors) {
      const {type, ...errors} = segmentsResult // eslint-disable-line
      if (errors.length)
        UI.processErrors(
          errors,
          where === 'center' ? 'getSegments' : 'getMoreSegments',
        )
      OfflineUtils.failedConnection(
        where,
        where === 'center' ? 'getSegments' : 'getMoreSegments',
      )
      return
    }

    const {segmentId, data} = segmentsResult
    if (where === 'center') {
      // Init segments
      // TODO: da verificare se serve: $(document).trigger('segments:load', data)
      $(document).trigger('segments:load', data)
      if (Cookies.get('tmpanel-open') == '1') UI.openLanguageResourcesPanel()

      if (
        !Object.entries(data.files)
          .map(([, value]) => value.segments)
          .flat()
          .find(
            (segment) =>
              segment.sid === startSegmentIdRef?.current.split('-')[0],
          )
      ) {
        const firstFile = data.files[Object.keys(data.files)[0]]
        if (firstFile) {
          startSegmentIdRef.current = firstFile.segments[0].sid
        } else {
          const trackingMessage = `getSegments data: ${JSON.stringify(data)}`
          CommonUtils.dispatchTrackingError(trackingMessage)
        }
      }
      // TODO: da verificare se serve: this.body.addClass('loaded')
      $('body').addClass('loaded')

      const haveDataFilesEntries = Boolean(
        typeof data.files !== 'undefined' && Object.keys(data?.files)?.length,
      )

      if (haveDataFilesEntries) {
        if (options?.openCurrentSegmentAfter && !segmentId)
          SegmentActions.openSegment(
            UI.firstLoad ? UI.currentSegmentId : startSegmentIdRef?.current,
          )
      }
      CatToolActions.updateFooterStatistics()
      // TODO: da verificare se serve: $(document).trigger('getSegments_success')
      $(document).trigger('getSegments_success')

      // Open segment
      if (startSegmentIdRef?.current && haveDataFilesEntries)
        SegmentActions.openSegment(startSegmentIdRef?.current)

      setWasInitSegments(true)
    } else {
      // more segments
      // TODO: da verificare se serve: $(window).trigger('segmentsAdded', {resp: data.files})
      $(window).trigger('segmentsAdded', {resp: data.files})
    }
    $(document).trigger('files:appended')
  }, [segmentsResult, options?.openCurrentSegmentAfter])

  // execute callback option from onRender action
  useEffect(() => {
    if (
      !segmentsResult ||
      !segmentsResult?.data ||
      !callbackAfterSegmentsResponseRef?.current
    )
      return
    callbackAfterSegmentsResponseRef.current()
  }, [segmentsResult])

  // call UI.init execute after first segments request
  useEffect(() => {
    if (!wasInitSegments) return
    UI.init()
    if (SegmentFilter.enabled() && SegmentFilter.getStoredState().reactState)
      SegmentFilter.openFilter()
    setTimeout(function () {
      UI.checkWarnings(true)
    }, 1000)
    UI.registerFooterTabs()
  }, [wasInitSegments])

  return (
    <>
      <Header
        pid={config.id_project}
        jid={config.id_job}
        password={config.password}
        reviewPassword={config.review_password}
        source_code={config.source_rfc}
        target_code={config.target_rfc}
        isReview={config.isReview}
        revisionNumber={config.revisionNumber}
        userLogged={config.isLoggedIn}
        projectName={config.project_name}
        projectCompletionEnabled={config.project_completion_feature_enabled}
        secondRevisionsCount={config.secondRevisionsCount}
        overallQualityClass={config.overall_quality_class}
        qualityReportHref={config.quality_report_href}
        allowLinkToAnalysis={config.allow_link_to_analysis}
        analysisEnabled={config.analysis_enabled}
        isGDriveProject={config.isGDriveProject}
        showReviseLink={config.footer_show_revise_link}
      />

      <div className="main-container">
        <div data-mount="review-side-panel"></div>
        <div
          id="outer"
          className={
            isLoadingSegments
              ? options?.where === 'before'
                ? 'loadingBefore'
                : options?.where === 'after'
                ? 'loadingAfter'
                : 'loading'
              : ''
          }
        >
          <article id="file" className="loading mbc-commenting-closed">
            <div className="article-segments-container">
              <SegmentsContainer
                isReview={Review.enabled()}
                isReviewExtended={ReviewExtended.enabled()}
                reviewType={Review.type}
                enableTagProjection={UI.enableTagProjection}
                tagModesEnabled={UI.tagModesEnabled}
                startSegmentId={UI.startSegmentId?.toString()}
                firstJobSegment={config.first_job_segment}
              />
            </div>
          </article>
          <div id="loader-getMoreSegments" />
        </div>
        <div id="plugin-mount-point"></div>
        {isFreezingSegments && <div className="freezing-overlay"></div>}
      </div>

      <div className="notifications-wrapper">
        <NotificationBox />
      </div>

      <CattolFooter
        idProject={config.id_project}
        idJob={config.id_job}
        password={config.password}
        source={config.source_rfc}
        target={config.target_rfc}
        isReview={config.isReview}
        isCJK={config.isCJK}
        languagesArray={config.languages_array}
      />
    </>
  )
}

export default CatTool
