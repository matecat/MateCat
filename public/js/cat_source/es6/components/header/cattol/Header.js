import React, {useEffect, useRef} from 'react'
import SubHeaderContainer from './SubHeaderContainer'
import SegmentFilter from './segment_filter/segment_filter'
import SearchUtils from './search/searchUtils'
import CatToolActions from '../../../actions/CatToolActions'
import {FilesMenu} from './FilesMenu'

export const Header = ({
  jid,
  pid,
  password,
  projectName,
  source_code,
  target_code,
  revisionNumber,
  stats,
  user,
}) => {
  const dropdownInitialized = useRef(false)
  const searchRef = useRef()
  const dropdownMenuRef = useRef()
  const userMenuRef = useRef()
  useEffect(() => {
    const initDropdown = () => {
      dropdownInitialized.current = true
      if (SearchUtils.searchEnabled)
        if ($(dropdownMenuRef.current).length) {
          $(searchRef.current).show(100, function () {
            APP.fitText($('#pname-container'), $('#pname'), 25)
          })

          $(dropdownMenuRef.current).dropdown()
        }
      if ($(userMenuRef.current).length) {
        $('#user-menu-dropdown').dropdown()
      }

      if (config.isLoggedIn) {
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
          {/*Mark as complete*/}
          {config.project_completion_feature_enabled && (
            <button
              className={`action-submenu ui floating dropdown ${
                config.job_marked_complete
                  ? 'isMarkedComplete'
                  : config.mark_as_complete_button_enabled
                  ? 'isMarkableAsComplete'
                  : 'notMarkedComplete'
              }`}
              id="markAsCompleteButton"
              disabled={!config.mark_as_complete_button_enabled}
            />
          )}
          {/*Files instructions*/}
          <div
            className="action-submenu"
            id="files-instructions"
            title="Instructions"
          ></div>

          {/*Download Menu*/}
          <div
            className="action-submenu ui simple pointing top center floating dropdown"
            id="action-download"
            title="Download"
          >
            <div className="dropdown-menu-overlay"></div>
            <ul
              className="menu"
              id="previewDropdown"
              data-download={
                stats &&
                stats['TODO_FORMATTED'] == 0 &&
                stats['ANALYSIS_COMPLETE']
                  ? 'true'
                  : 'false'
              }
            >
              <li className="item previewLink" data-value="draft">
                <a title="Draft" alt="Draft" href="#">
                  Draft
                </a>
              </li>

              <li className="item downloadTranslation" data-value="translation">
                <a title="Translation" alt="Translation" href="#">
                  Download Translation
                </a>
              </li>
              {config.isGDriveProject && (
                <>
                  <li className="item" data-value="original">
                    <a
                      className="originalDownload"
                      title="Original"
                      alt="Original"
                      data-href={`/?action=downloadOriginal&id_job=${jid}&password=${password}&download_type=all`}
                      target="_blank"
                    >
                      Original
                    </a>
                  </li>

                  <li className="item">
                    <a
                      className="originalsGDrive"
                      title="Original in Google Drive"
                      alt="Original in Google Drive"
                      href="javascript:void(0)"
                    >
                      Original in Google Drive
                    </a>
                  </li>
                </>
              )}
              <li className="item" data-value="xlif">
                <a
                  className="sdlxliff"
                  title="Export XLIFF"
                  alt="Export XLIFF"
                  data-href={`/SDLXLIFF/${jid}/${password}/${jid}.zip`}
                  target="_blank"
                >
                  Export XLIFF
                </a>
              </li>

              <li className="item" data-value="tmx">
                <a
                  rel="noreferrer"
                  className="tmx"
                  title="Export job TMX for QA"
                  alt="Export job TMX for QA"
                  href={`/TMX/${jid}/${password}`}
                  target="_blank"
                >
                  Export Job TMX
                </a>
              </li>
            </ul>
          </div>

          {/*Quality Report*/}
          <div
            className="action-submenu ui floating ${header_quality_report_item_class}"
            id="quality-report-button"
            title="Quality Report"
          />

          {/*Segments Issues*/}
          <div className="action-submenu ui floating" id="notifbox">
            <a id="point2seg">
              <span className="numbererror"></span>
            </a>
            <svg
              xmlns="http://www.w3.org/2000/svg"
              x="0"
              y="0"
              enableBackground="new 0 0 42 42"
              version="1.1"
              viewBox="0 0 42 42"
              xmlSpace="preserve"
            >
              <g className="st0">
                <path
                  fill="#fff"
                  className="st1"
                  d="M18.5 26.8l1.8 2.1-1.8 1.5-1.9-2.3c-1 .5-2.2.7-3.5.7-4.9 0-7.9-3.6-7.9-8.3 0-4.7 3-8.3 7.9-8.3s7.9 3.6 7.9 8.3c0 2.6-.9 4.8-2.5 6.3zm-5.4-11.9c-3.2 0-5 2.4-5 5.7 0 3.3 1.8 5.7 5 5.7.6 0 1.2-.1 1.7-.4L13.2 24l1.8-1.4 1.8 2.1c.9-1 1.4-2.4 1.4-4.1-.1-3.3-2-5.7-5.1-5.7z"
                />
                <path
                  d="M34.7 28.5l-1.5-4.1h-6.6L25 28.5h-3l6.3-16h3.3l6.3 16h-3.2zM29.9 15l-2.6 7.1h5.1L29.9 15z"
                  className="st1"
                  fill="#fff"
                />
              </g>
            </svg>
          </div>

          {/*Search*/}
          <div
            className="action-submenu ui floating dropdown"
            id="action-search"
            style={{display: 'none'}}
            title="Search or Filter results"
            ref={searchRef}
          >
            <svg
              width="30px"
              height="30px"
              viewBox="-4 -4 31 31"
              version="1.1"
              xmlns="http://www.w3.org/2000/svg"
            >
              <g
                id="Icon/Search/Active"
                stroke="none"
                strokeWidth="1"
                fill="none"
                fillRule="evenodd"
              >
                <path
                  d="M23.3028148,20.1267654 L17.8057778,14.629284 C16.986716,15.9031111 15.9027654,16.9865185 14.6289383,17.8056296 L20.1264198,23.3031111 C21.0040494,24.1805432 22.4270123,24.1805432 23.3027654,23.3031111 C24.1804444,22.4271111 24.1804444,21.0041481 23.3028148,20.1267654 Z"
                  id="Path"
                  fill="#FFFFFF"
                />
                <circle
                  id="Oval"
                  stroke="#FFFFFF"
                  strokeWidth="1.5"
                  cx="9"
                  cy="9"
                  r="8.25"
                />
                <path
                  className="st1"
                  d="M9,16 C5.13400675,16 2,12.8659932 2,9 C2,5.13400675 5.13400675,2 9,2 C12.8659932,2 16,5.13400675 16,9 C16,12.8659932 12.8659932,16 9,16 Z M3.74404938,8.9854321 L5.2414321,8.9854321 C5.2414321,6.92108642 6.9211358,5.24153086 8.9854321,5.24153086 L8.9854321,3.744 C6.0957037,3.744 3.74404938,6.09565432 3.74404938,8.9854321 Z"
                  id="Combined-Shape"
                  fill="#FFFFFF"
                />
              </g>
            </svg>
          </div>

          {/*Comments*/}
          {config.comments_enabled && (
            <div
              id="mbc-history"
              title="View comments"
              className="mbc-history-balloon-icon-has-no-comments"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="3 3 36 36">
                <path
                  fill="#fff"
                  fillRule="evenodd"
                  stroke="none"
                  strokeWidth="1"
                  d="M33.125 13.977c-1.25-1.537-2.948-2.75-5.093-3.641C25.886 9.446 23.542 9 21 9c-2.541 0-4.885.445-7.031 1.336-2.146.89-3.844 2.104-5.094 3.64C7.625 15.514 7 17.188 7 19c0 1.562.471 3.026 1.414 4.39.943 1.366 2.232 2.512 3.867 3.439-.114.416-.25.812-.406 1.187-.156.375-.297.683-.422.922-.125.24-.294.505-.508.797a8.15 8.15 0 01-.484.617 249.06 249.06 0 00-1.023 1.133 1.1 1.1 0 00-.126.141l-.109.132-.094.141c-.052.078-.075.127-.07.148a.415.415 0 01-.031.156c-.026.084-.024.146.007.188v.016c.042.177.125.32.25.43a.626.626 0 00.422.163h.079a11.782 11.782 0 001.78-.344c2.73-.697 5.126-1.958 7.189-3.781.78.083 1.536.125 2.265.125 2.542 0 4.886-.445 7.032-1.336 2.145-.891 3.843-2.104 5.093-3.64C34.375 22.486 35 20.811 35 19c0-1.812-.624-3.487-1.875-5.023z"
                ></path>
              </svg>
            </div>
          )}

          {/*Segments filter*/}
          {config.segmentFilterEnabled && (
            <div
              className="action-submenu ui floating"
              id="action-filter"
              title="Filter segments"
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
                    href={`/revise/${projectName}/${source_code}-${target_code}/${jid}-${password}`}
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
          {!config.isLoggedIn && (
            <div className="position-sing-in">
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
          {config.isLoggedIn && user && (
            <div
              className="user-menu-container ui floating pointing top right dropdown"
              id="user-menu-dropdown"
              ref={userMenuRef}
            >
              <div className="ui user circular image ui-user-top-image"></div>
              <div className="organization-name"></div>
              <div className="menu">
                <div className="item" data-value="manage" id="manage-item">
                  My Projects
                </div>
                <div className="item" data-value="profile" id="profile-item">
                  Profile
                </div>
                <div className="item" data-value="logout" id="logout-item">
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
