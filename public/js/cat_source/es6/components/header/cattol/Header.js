import React, {useEffect, useRef} from 'react'
import SubHeaderContainer from './SubHeaderContainer'
import SegmentFilter from './segment_filter/segment_filter'
import SearchUtils from './search/searchUtils'
import CatToolActions from '../../../actions/CatToolActions'
import {FilesMenu} from './FilesMenu'
import {MarkAsCompleteButton} from './MarkAsCompleteButton'
import JobMetadata from './JobMetadata'
import {QualityReportButton} from '../../review/QualityReportButton'
import {logoutUser} from '../../../api/logoutUser'
import {ModalWindow} from '../../modals/ModalWindow'
import ShortCutsModal from '../../modals/ShortCutsModal'
import {DownloadMenu} from './DownloadMenu'
import {SegmentsQAButton} from './SegmetsQAButton'
import {SearchButton} from './SearchButton'
import {CommentsButton} from './CommentsButton'

export const Header = ({
  jid,
  pid,
  password,
  reviewPassword,
  projectName,
  source_code,
  target_code,
  revisionNumber,
  stats,
  user,
  userLogged,
  projectCompletionEnabled,
  isReview,
  secondRevisionsCount,
  overallQualityClass,
  qualityReportHref,
}) => {
  const dropdownInitialized = useRef(false)
  const dropdownMenuRef = useRef()
  const userMenuRef = useRef()

  const logoutUserFn = () => {
    logoutUser().then(() => {
      if ($('body').hasClass('manage')) {
        location.href = config.hostpath + config.basepath
      } else {
        window.location.reload()
      }
    })
  }
  const loginUser = () => {
    APP.openLoginModal()
  }
  const openOptionsPanel = (event) => {
    event.preventDefault()
    UI.openOptionsPanel()
  }
  const openPreferences = (event) => {
    event.preventDefault()
    event.stopPropagation()
    $('#modal').trigger('openpreferences')
  }

  const openSegmetsFilters = (event) => {
    event.preventDefault()
    if (!SegmentFilter.open) {
      SegmentFilter.openFilter()
    } else {
      SegmentFilter.closeFilter()
      SegmentFilter.open = false
    }
  }

  const openShortcutsModal = (event) => {
    event.preventDefault()
    event.stopPropagation()
    ModalWindow.showModalComponent(ShortCutsModal, {}, 'Shortcuts')
  }

  useEffect(() => {
    const initDropdown = () => {
      dropdownInitialized.current = true
      if (SearchUtils.searchEnabled)
        if ($(dropdownMenuRef.current).length) {
          $(dropdownMenuRef.current).dropdown()
        }
      if ($(userMenuRef.current).length) {
        $('#user-menu-dropdown').dropdown()
      }

      if (userLogged) {
        setTimeout(function () {
          CatToolActions.showHeaderTooltip()
        }, 3000)
      }
    }
    !dropdownInitialized.current && initDropdown()
  }, [])
  return (
    <>
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
          <DownloadMenu password={password} jid={jid} stats={stats} />

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
          {config.segmentFilterEnabled && (
            <div
              className="action-submenu ui floating"
              id="action-filter"
              title="Filter segments"
              onClick={openSegmetsFilters}
            >
              <svg
                width="30px"
                height="30px"
                viewBox="-6 -5 33 33"
                version="1.1"
                xmlns="http://www.w3.org/2000/svg"
              >
                <g
                  id="Icon/Filter/Active"
                  stroke="none"
                  strokeWidth="1"
                  fill="none"
                  fillRule="evenodd"
                >
                  <g id="filter" fill="none">
                    <path
                      strokeWidth="1.5"
                      stroke="#fff"
                      d="M22.9660561,1.79797063e-06 L1.03369998,1.79797063e-06 C0.646872201,-0.00071025154 0.292239364,0.210114534 0.115410779,0.545863698 C-0.0638568515,0.88613389 -0.0323935402,1.29588589 0.196629969,1.60665014 L8.23172155,12.6494896 C8.23440448,12.6532968 8.2373313,12.6568661 8.24001423,12.6606733 C8.53196433,13.0452025 8.68976863,13.510873 8.69074424,13.9896308 L8.69074424,22.9927526 C8.68903691,23.2594959 8.79635358,23.5155313 8.98903581,23.7047026 C9.18171797,23.8938738 9.44366823,24.0000018 9.71683793,24.0000018 C9.85586177,24.0000018 9.99317834,23.9728736 10.1214705,23.9210002 L14.6365754,22.2413027 C15.041208,22.1208994 15.3097436,21.7485057 15.3097436,21.2999677 L15.3097436,13.9896308 C15.3104753,13.5111109 15.4685235,13.0452025 15.7602297,12.6606733 C15.7629126,12.6568661 15.7658394,12.6532968 15.7685223,12.6494896 L23.80337,1.60617426 C24.0323936,1.2956479 24.0638568,0.88613389 23.8845893,0.545863698 C23.7077606,0.210114534 23.3531278,-0.00071025154 22.9660561,1.79797063e-06 Z"
                      id="Shape"
                    />
                  </g>
                </g>
              </svg>
            </div>
          )}

          {/*Settings Icon*/}
          <div
            className="action-submenu ui floating"
            id="action-settings"
            title="Settings"
            onClick={openOptionsPanel}
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="-3 -3 26 26"
              width="30px"
              height="30px"
            >
              <path
                fill="#fff"
                fillRule="evenodd"
                stroke="none"
                strokeWidth="1"
                d="M19.92 8.882c-.032-.281-.36-.492-.643-.492a2.22 2.22 0 01-2.068-1.37 2.225 2.225 0 01.558-2.47.555.555 0 00.061-.753 9.887 9.887 0 00-1.583-1.599.556.556 0 00-.76.062c-.595.66-1.664.904-2.491.56a2.22 2.22 0 01-1.35-2.17.554.554 0 00-.49-.583A9.975 9.975 0 008.906.06a.556.556 0 00-.494.571 2.223 2.223 0 01-1.369 2.132c-.816.334-1.878.09-2.473-.563a.557.557 0 00-.754-.064A9.924 9.924 0 002.2 3.735a.556.556 0 00.06.76c.695.63.92 1.631.558 2.493-.345.821-1.198 1.35-2.174 1.35a.543.543 0 00-.577.49c-.088.751-.089 1.516-.004 2.273.031.282.369.491.655.491.87-.022 1.705.517 2.056 1.37.349.851.124 1.844-.559 2.47a.555.555 0 00-.06.753c.464.591.996 1.13 1.58 1.6a.556.556 0 00.76-.061c.598-.661 1.668-.906 2.491-.56a2.216 2.216 0 011.352 2.168.555.555 0 00.49.584 9.931 9.931 0 002.248.006.556.556 0 00.495-.572 2.22 2.22 0 011.367-2.131c.822-.336 1.88-.09 2.474.563.198.215.524.24.754.063a9.947 9.947 0 001.616-1.598.555.555 0 00-.06-.76 2.214 2.214 0 01-.559-2.492 2.237 2.237 0 012.044-1.354l.123.003a.556.556 0 00.585-.49 10 10 0 00.005-2.272zm-9.913 4.463a3.336 3.336 0 01-3.333-3.333 3.336 3.336 0 013.333-3.332 3.336 3.336 0 013.333 3.332 3.336 3.336 0 01-3.333 3.333z"
                transform="translate(-1261 -285) translate(1261 285)"
              />
            </svg>
          </div>

          {/*Dropdown menu*/}
          <div
            className="action-submenu ui pointing top center floating dropdown"
            id="action-three-dots"
            title="Menu"
            ref={dropdownMenuRef}
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="5 5 32 32">
              <g
                fill="#fff"
                fillRule="evenodd"
                stroke="none"
                strokeWidth="1"
                transform="translate(9 9)"
              >
                <circle cx="12.5" cy="2.5" r="2.5" />
                <circle cx="12.5" cy="21.5" r="2.5" />
                <circle cx="12.5" cy="12.5" r="2.5" />
              </g>
            </svg>
            <ul className="menu">
              {!config.isReview && (
                <li className="item" title="Revise" data-value="revise">
                  <a
                    href={`/revise/${projectName}/${source_code}-${target_code}/${jid}-${reviewPassword}`}
                  >
                    Revise
                  </a>
                </li>
              )}
              {config.isReview && (
                <li className="item" title="Translate" data-value="translate">
                  <a
                    href={`/translate/${projectName}/${source_code}-${target_code}/${jid}-${password}`}
                  >
                    Translate
                  </a>
                </li>
              )}
              {config.allow_link_to_analysis && config.analysis_enabled && (
                <li className="item" title="Analysis" data-value="analisys">
                  <a
                    rel="noreferrer"
                    target="_blank"
                    href={`/jobanalysis/${pid}-${jid}-${password}`}
                  >
                    Volume analysis
                  </a>
                </li>
              )}

              <li
                className="item"
                title="XLIFF-to-target converter"
                data-value="target"
              >
                <a
                  rel="noreferrer"
                  target="_blank"
                  href={`/utils/xliff-to-target`}
                >
                  XLIFF-to-target converter
                </a>
              </li>
              <li
                className="item shortcuts"
                title="Shortcuts"
                data-value="shortcuts"
                onClick={openShortcutsModal}
              >
                <a>Shortcuts</a>
              </li>
              {/*<li class="item" title="Edit log" data-value="editlog" >*/}
              {/*    <a id="edit_log_link" target="_blank" href={`editlog/${jid}-${password}`}>Editing Log</a>*/}
              {/*</li>*/}
            </ul>
          </div>
        </div>

        {/*Profile menu*/}
        <div className="profile-menu">
          {!userLogged && (
            <div className="position-sing-in" onClick={loginUser}>
              <div className="ui user-nolog label open-login-modal sing-in-header">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 42 42">
                  <path
                    fill="#09C"
                    fillRule="evenodd"
                    stroke="none"
                    strokeWidth="1"
                    d="M11.878 0C5.318 0 0 5.319 0 11.879c0 6.56 5.318 11.877 11.878 11.877 6.56 0 11.878-5.317 11.878-11.877C23.756 5.318 18.438 0 11.878 0zm0 3.552a3.929 3.929 0 110 7.858 3.929 3.929 0 010-7.858zm-.003 17.098A8.717 8.717 0 016.2 18.557a1.674 1.674 0 01-.588-1.273c0-2.2 1.781-3.96 3.982-3.96h4.571a3.956 3.956 0 013.975 3.96c0 .49-.214.954-.587 1.272a8.714 8.714 0 01-5.677 2.094z"
                    transform="translate(9 9)"
                  />
                </svg>
              </div>
            </div>
          )}
          {userLogged && user && (
            <div
              className="user-menu-container ui floating pointing top right dropdown"
              id="user-menu-dropdown"
              ref={userMenuRef}
            >
              <div className="ui user circular image ui-user-top-image"></div>
              <div className="organization-name"></div>
              <div className="menu">
                <a
                  className="item"
                  data-value="manage"
                  id="manage-item"
                  href="/manage"
                >
                  My Projects
                </a>
                <div
                  className="item"
                  data-value="profile"
                  id="profile-item"
                  onClick={openPreferences}
                >
                  Profile
                </div>
                <div
                  className="item"
                  data-value="logout"
                  id="logout-item"
                  onClick={logoutUserFn}
                >
                  Logout
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
      <div id="header-bars-wrapper">
        <SubHeaderContainer filtersEnabled={SegmentFilter.enabled()} />
      </div>
    </>
  )
}
