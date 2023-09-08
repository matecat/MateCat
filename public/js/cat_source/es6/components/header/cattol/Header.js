import React, {useEffect, useState} from 'react'
import SubHeaderContainer from './SubHeaderContainer'
import SegmentFilter from './segment_filter/segment_filter'
import CatToolActions from '../../../actions/CatToolActions'
import {FilesMenu} from './FilesMenu'
import {MarkAsCompleteButton} from './MarkAsCompleteButton'
import JobMetadata from './JobMetadata'
import {QualityReportButton} from '../../review/QualityReportButton'
import {DownloadMenu} from './DownloadMenu'
import {SegmentsQAButton} from './SegmetsQAButton'
import {SearchButton} from './SearchButton'
import {CommentsButton} from './CommentsButton'
import {SegmentsFilterButton} from './SegmentsFilterButton'
import {SettingsButton} from './SettingsButton'
import {ActionMenu} from '../ActionMenu'
import TeamsStore from '../../../stores/TeamsStore'
import TeamConstants from '../../../constants/TeamConstants'
import {UserMenu} from '../UserMenu'

export const Header = ({
  jid,
  pid,
  password,
  reviewPassword,
  projectName,
  source_code,
  target_code,
  revisionNumber,
  userLogged,
  projectCompletionEnabled,
  isReview,
  secondRevisionsCount,
  overallQualityClass,
  qualityReportHref,
  allowLinkToAnalysis,
  analysisEnabled,
  isGDriveProject,
  showReviseLink,
  openTmPanel,
}) => {
  const [user, setUser] = useState()

  useEffect(() => {
    if (userLogged) {
      setTimeout(function () {
        CatToolActions.showHeaderTooltip()
      }, 3000)
    }
  }, [])

  useEffect(() => {
    const updateUser = (user) => setUser(user)

    TeamsStore.addListener(TeamConstants.UPDATE_USER, updateUser)
    return () => {
      TeamsStore.removeListener(TeamConstants.UPDATE_USER, updateUser)
    }
  }, [])
  return (
    <header>
      <div className="wrapper">
        <div className="logo-menu">
          <a href="/" className="logo" />
        </div>

        {/*Revision number  */}

        <div className="header-el-placeholder">
          {revisionNumber >= 1 && (
            <div
              className={`revision-mark revision-r${revisionNumber}`}
              title="Revision number"
            >
              R{revisionNumber}
            </div>
          )}
        </div>

        {/*Files Menu*/}
        <FilesMenu projectName={projectName} />

        {/*Icons header*/}
        <div className="action-menu">
          {projectCompletionEnabled && (
            <MarkAsCompleteButton
              featureEnabled={projectCompletionEnabled}
              isReview={isReview}
            />
          )}

          {/*Files instructions*/}
          <JobMetadata idJob={jid} password={password} />

          {/*Download Menu*/}
          <DownloadMenu
            password={password}
            jid={jid}
            isGDriveProject={isGDriveProject}
          />

          {/*Quality Report*/}
          <QualityReportButton
            isReview={isReview}
            revisionNumber={revisionNumber}
            overallQualityClass={overallQualityClass}
            qualityReportHref={qualityReportHref}
            secondRevisionsCount={secondRevisionsCount}
          />

          {/*Segments Issues*/}
          <SegmentsQAButton />

          {/*Search*/}
          <SearchButton />

          {/*Comments*/}
          <CommentsButton />

          {/*Segments filter*/}
          <SegmentsFilterButton />

          {/*Settings Icon*/}
          <SettingsButton openTmPanel={openTmPanel} />

          {/*Dropdown menu*/}
          <ActionMenu
            cattoolMenu={true}
            isReview={isReview}
            projectName={projectName}
            source_code={source_code}
            target_code={target_code}
            jid={jid}
            pid={pid}
            password={password}
            reviewPassword={reviewPassword}
            allowLinkToAnalysis={allowLinkToAnalysis}
            analysisEnabled={analysisEnabled}
            showReviseLink={showReviseLink}
          />
        </div>

        {/*Profile menu*/}
        <UserMenu user={user} userLogged={userLogged} />
      </div>
      <div id="header-bars-wrapper">
        <SubHeaderContainer filtersEnabled={SegmentFilter.enabled()} />
      </div>
    </header>
  )
}
