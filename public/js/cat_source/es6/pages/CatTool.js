import React, {useEffect, useRef, useState} from 'react'
import {CattolFooter} from '../components/footer/CattoolFooter'
import {Header} from '../components/header/cattol/Header'
import NotificationBox from '../components/notificationsComponent/NotificationBox'
import SegmentsContainer from '../components/segments/SegmentsContainer'
import CatToolStore from '../stores/CatToolStore'
import CatToolConstants from '../constants/CatToolConstants'
import {getSegments} from '../api/getSegments'
import Cookies from 'js-cookie'
import OfflineUtils from '../utils/offlineUtils'
import SegmentActions from '../actions/SegmentActions'
import CatToolActions from '../actions/CatToolActions'
import SegmentFilter from '../components/header/cattol/segment_filter/segment_filter'

function CatTool() {
  const [options, setOptions] = useState({})
  const [isLoadingSegments, setIsLoadingSegments] = useState(false)
  const [wasSegmentsInit, setWasSegmentsInit] = useState(false)

  const startSegmentIdRef = useRef(UI.startSegmentId)

  // action listeners
  useEffect(() => {
    const onRenderHandler = (options) => {
      const {actionType, startSegmentId, ...restOptions} = options // eslint-disable-line
      setOptions((prevState) => ({...prevState, ...restOptions}))
      if (startSegmentId) startSegmentIdRef.current = startSegmentId
      console.log(options)
    }
    CatToolStore.addListener(CatToolConstants.ON_RENDER, onRenderHandler)

    return () => {
      CatToolStore.removeListener(CatToolConstants.ON_RENDER, onRenderHandler)
    }
  }, [])

  // get segments
  useEffect(() => {
    let wasCleaned = false

    const startSegmentId = startSegmentIdRef?.current
    const segmentToOpen = options?.segmentToOpen
    const openCurrentSegmentAfter = options?.openCurrentSegmentAfter
    const where = startSegmentId ? 'center' : 'after'

    // handle data segments init
    const parseDataInit = (data) => {
      // TODO: da verificare se serve: $(document).trigger('segments:load', data.data)
      $(document).trigger('segments:load', data.data)
      if (Cookies.get('tmpanel-open') == '1') UI.openLanguageResourcesPanel()

      const where = data.where

      if (!startSegmentId) {
        const firstFile = data.files[Object.keys(data.files)[0]]
        startSegmentIdRef.current = firstFile.segments[0].sid
      }
      // TODO: da verificare se serve: this.body.addClass('loaded')
      $('body').addClass('loaded')

      if (typeof data.files !== 'undefined') {
        const segments = Object.entries(data.files)
          .map(([, value]) => value.segments)
          .flat()
        SegmentActions.addSegments(segments, where)

        if (openCurrentSegmentAfter && !segmentToOpen)
          SegmentActions.openSegment(
            UI.firstLoad ? UI.currentSegmentId : startSegmentIdRef,
          )
      }
      CatToolActions.updateFooterStatistics()
      // TODO: da verificare se serve: $(document).trigger('getSegments_success')
      $(document).trigger('getSegments_success')

      setIsLoadingSegments(false)
      setWasSegmentsInit(true)
    }
    // handle errors segments init
    const onErrorInit = (errors) => {
      if (errors.length) UI.processErrors(errors, 'getSegments')
      OfflineUtils.failedConnection(0, 'getSegments')
    }

    getSegments({
      jid: config.id_job,
      password: config.password,
      step: where === 'center' ? 40 : UI.moreSegNum,
      segment: segmentToOpen ? segmentToOpen : startSegmentId,
      where,
    })
      .then((data) => {
        if (wasCleaned) return
        parseDataInit(data.data)
      })
      .catch((errors) => {
        if (wasCleaned) return
        onErrorInit(errors)
      })

    setIsLoadingSegments(true)

    return () => {
      wasCleaned = true
    }
  }, [options?.segmentToOpen, options?.openCurrentSegmentAfter])

  // On segments init
  useEffect(() => {
    if (!wasSegmentsInit) return

    UI.init()

    if (SegmentFilter.enabled() && SegmentFilter.getStoredState().reactState)
      SegmentFilter.openFilter()
    setTimeout(function () {
      UI.checkWarnings(true)
    }, 1000)

    UI.registerFooterTabs()
  }, [wasSegmentsInit])

  // Open segment on init
  useEffect(() => {
    if (!wasSegmentsInit || !startSegmentIdRef?.current) return
    SegmentActions.openSegment(startSegmentIdRef.current)
  }, [wasSegmentsInit])

  return (
    <>
      <Header
        pid={config.id_project}
        jid={config.job_id}
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
        <div id="outer" className={isLoadingSegments ? 'loading' : ''}>
          <article id="file" className="loading mbc-commenting-closed">
            <div className="article-segments-container">
              <SegmentsContainer
                isReview={Review.enabled()}
                isReviewExtended={ReviewExtended.enabled()}
                reviewType={Review.type}
                enableTagProjection={UI.enableTagProjection}
                tagModesEnabled={UI.tagModesEnabled}
                startSegmentId={UI.startSegmentId}
                firstJobSegment={config.first_job_segment}
              />
            </div>
          </article>
          <div id="loader-getMoreSegments" />
        </div>
        <div id="plugin-mount-point"></div>
      </div>

      <div className="notifications-wrapper">
        <NotificationBox />
      </div>

      <CattolFooter
        idProject={config.id_project}
        idJob={config.job_id}
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
