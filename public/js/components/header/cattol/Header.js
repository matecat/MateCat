import React, {useContext} from 'react'
import SubHeaderContainer from './SubHeaderContainer'
import SegmentFilter from './segment_filter/segment_filter'
import {FilesMenu} from './FilesMenu'
import {MarkAsCompleteButton} from './MarkAsCompleteButton'
import JobMetadata from './JobMetadata'
import {QualityReportButton} from '../../review/QualityReportButton'
import {DownloadMenu} from './DownloadMenu'
import {SegmentsQAButton} from './SegmetsQAButton'
import {SearchButton} from './SearchButton'
import {CommentsButton} from './CommentsButton'
import {SegmentsFilterButton} from './SegmentsFilterButton'
import {ActionMenu} from '../ActionMenu'
import {UserMenu} from '../UserMenu'
import {ApplicationWrapperContext} from '../../common/ApplicationWrapper/ApplicationWrapperContext'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../../common/Button/Button'
import SettingsIcon from '../../../../img/icons/SettingsIcon'

export const Header = ({
  jid,
  pid,
  password,
  reviewPassword,
  projectName,
  source_code,
  target_code,
  revisionNumber,
  projectCompletionEnabled,
  isReview,
  secondRevisionsCount,
  qualityReportHref,
  allowLinkToAnalysis,
  analysisEnabled,
  isGDriveProject,
  showReviseLink,
  openTmPanel,
  jobMetadata,
}) => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  return (
    <header>
      <div className="wrapper nav-bar">
        <div className="logo-menu">
          <a href="/" className="logo" />
        </div>
        {isUserLogged ? (
          <>
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
            <div className="header-menu">
              {projectCompletionEnabled && (
                <MarkAsCompleteButton
                  featureEnabled={projectCompletionEnabled}
                  isReview={isReview}
                />
              )}

              {/*Files instructions*/}
              <JobMetadata metadata={jobMetadata} />

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
              <Button
                type={BUTTON_TYPE.ICON}
                mode={BUTTON_MODE.GHOST}
                onClick={openTmPanel}
                size={BUTTON_SIZE.ICON_STANDARD}
              >
                <SettingsIcon size={20} />
              </Button>

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
          </>
        ) : null}
        {/*Profile menu*/}
        <UserMenu />
      </div>
      <div id="header-bars-wrapper">
        <SubHeaderContainer
          userInfo={userInfo}
          filtersEnabled={SegmentFilter.enabled()}
        />
      </div>
    </header>
  )
}
