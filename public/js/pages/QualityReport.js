import React, {useContext, useEffect, useState} from 'react'
import ReactDOM from 'react-dom'
import QualityReportActions from '../actions/QualityReportActions'
import QualityReportStore from '../stores/QualityReportStore'
import QualityReportConstants from '../constants/QualityReportConstants'
import SegmentsDetails from '../components/quality_report/SegmentsDetailsContainer'
import JobSummary from '../components/quality_report/JobSummary'
import usePortal from '../hooks/usePortal'
import Header from '../components/header/Header'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import {CookieConsent} from '../components/common/CookieConsent'
import {mountPage} from './mountPage'
import SocketListener from '../sse/SocketListener'
import {DropdownMenu} from '../components/common/DropdownMenu/DropdownMenu'
import {BUTTON_SIZE} from '../components/common/Button/Button'

const getReviseUrlParameter = () => {
  const url = new URL(window.location.href)
  const revType = url.searchParams.get('revision_type')
  return revType ? revType : '1'
}

export const QualityReport = () => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [segmentsFiles, setSegmentsFiles] = useState()
  const [files, setFiles] = useState()
  const [lastSegment, setLastSegment] = useState()
  const [jobInfo, setJobInfo] = useState()
  const [moreSegments, setMoreSegments] = useState(true)
  const [revisionToShow, setRevisionToShow] = useState(() =>
    getReviseUrlParameter(),
  )
  const [idSegment, setIdSegment] = useState()
  const [qualitySummary, setQualitySummary] = useState()

  const headerMountPoint = document.querySelector('header')
  const HeaderPortal = usePortal(headerMountPoint)

  useEffect(() => {
    const url = new URL(window.location.href)
    const id = url.searchParams.get('id_segment')

    QualityReportActions.loadInitialAjaxData({id_segment: id})
    setIdSegment(id)
  }, [])

  useEffect(() => {
    const renderSegmentsFiles = (segmentsFiles, files, lastSegment) => {
      setSegmentsFiles(segmentsFiles)
      setFiles(files)
      setLastSegment(lastSegment)
      setMoreSegments(true)
    }
    const renderJobInfo = (jobInfo) => {
      setJobInfo(jobInfo)
      setQualitySummary(
        jobInfo.get('quality_summary').find((value) => {
          return value.get('revision_number') === parseInt(revisionToShow)
        }),
      )
    }
    const noMoreSegments = () => setMoreSegments(false)

    QualityReportStore.addListener(
      QualityReportConstants.RENDER_SEGMENTS_QR,
      renderSegmentsFiles,
    )
    QualityReportStore.addListener(
      QualityReportConstants.RENDER_REPORT,
      renderJobInfo,
    )
    QualityReportStore.addListener(
      QualityReportConstants.NO_MORE_SEGMENTS,
      noMoreSegments,
    )

    return () => {
      QualityReportStore.removeListener(
        QualityReportConstants.RENDER_SEGMENTS_QR,
        renderSegmentsFiles,
      )
      QualityReportStore.removeListener(
        QualityReportConstants.RENDER_REPORT,
        renderJobInfo,
      )
      QualityReportStore.removeListener(
        QualityReportConstants.NO_MORE_SEGMENTS,
        noMoreSegments,
      )
    }
  }, [])

  const secondPassReviewEnabled = jobInfo?.get('quality_summary').size > 1

  const updateUrlParameter = (revisionType) => {
    const url = new URL(window.location.href)
    url.searchParams.set('revision_type', revisionType)
    history.pushState(null, '', url)
  }

  const updateUrlIdSegment = (idSegment) => {
    const url = new URL(window.location.href)
    if (idSegment) {
      url.searchParams.set('id_segment', idSegment)
    } else {
      url.searchParams.delete('id_segment')
    }
    history.pushState(null, '', url)
  }

  useEffect(() => {
    if (secondPassReviewEnabled && revisionToShow && revisionToShow !== '') {
      updateUrlParameter(revisionToShow)
      setQualitySummary(
        jobInfo.get('quality_summary').find((item) => {
          return item.get('revision_number') === parseInt(revisionToShow)
        }),
      )
    }
  }, [jobInfo, revisionToShow])

  const spinnerContainer = {
    position: 'absolute',
    height: '100%',
    width: '100%',
    backgroundColor: 'rgba(76, 69, 69, 0.3)',
    top: document.getElementById('qr-root').scrollTop,
    left: 0,
    zIndex: 3,
  }

  const cookieBannerMountPoint = document.getElementsByTagName('footer')[0]

  return (
    <>
      <HeaderPortal>
        <Header
          showModals={true}
          isQualityReport={true}
          loggedUser={isUserLogged}
          user={isUserLogged ? userInfo.user : undefined}
        />
      </HeaderPortal>
      <div className="qr-container">
        <div className="qr-container-inside">
          <div className="qr-job-summary-container">
            <div className="qr-bg-head" />

            {jobInfo ? (
              <div className="qr-job-summary">
                <div className="qr-header">
                  <h3>QR Job summary</h3>

                  {secondPassReviewEnabled ? (
                    <div className="qr-filter-list">
                      <div className="filter-dropdown right-10">
                        <div className={'filter-reviewType active'}>
                          <DropdownMenu
                            dropdownClassName={'qr-reviewType-dropdown'}
                            toggleButtonProps={{
                              children: (
                                <div
                                  className="ui top left pointing dropdown basic tiny button right-0"
                                  style={{marginBottom: '12px'}}
                                >
                                  {revisionToShow === '1' ? (
                                    <div className="text">
                                      <div
                                        className={
                                          'ui revision-color empty circular label'
                                        }
                                      />
                                      Revision
                                    </div>
                                  ) : (
                                    <div className="text">
                                      <div
                                        className={
                                          'ui second-revision-color empty circular label'
                                        }
                                      />
                                      2nd Revision
                                    </div>
                                  )}
                                </div>
                              ),
                              size: BUTTON_SIZE.ICON_STANDARD,
                            }}
                            items={[
                              {
                                label: (
                                  <>
                                    <div
                                      className={
                                        'ui revision-color empty circular label'
                                      }
                                    />
                                    Revision
                                  </>
                                ),
                                onClick: () => {
                                  setRevisionToShow('1')
                                },
                              },
                              {
                                label: (
                                  <>
                                    <div
                                      className={
                                        'ui second-revision-color empty circular label'
                                      }
                                    />
                                    2nd Revision
                                  </>
                                ),
                                onClick: () => {
                                  setRevisionToShow('2')
                                },
                              },
                            ]}
                          />
                        </div>
                      </div>
                    </div>
                  ) : null}
                </div>

                <JobSummary
                  jobInfo={jobInfo}
                  qualitySummary={qualitySummary}
                  secondPassReviewEnabled={secondPassReviewEnabled}
                />

                <SegmentsDetails
                  revisionToShow={revisionToShow}
                  files={files}
                  segmentsFiles={segmentsFiles}
                  lastSegment={lastSegment}
                  segmentToFilter={idSegment}
                  updateSegmentToFilter={updateUrlIdSegment}
                  urls={jobInfo.get('urls')}
                  categories={qualitySummary.get('categories')}
                  moreSegments={moreSegments}
                  secondPassReviewEnabled={secondPassReviewEnabled}
                />
              </div>
            ) : (
              <div style={spinnerContainer}>
                <div className="ui active inverted dimmer">
                  <div className="ui massive text loader">Loading</div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
      {ReactDOM.createPortal(<CookieConsent />, cookieBannerMountPoint)}
      <SocketListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
    </>
  )
}

mountPage({
  Component: QualityReport,
  rootElement: document.getElementById('qr-root'),
})
