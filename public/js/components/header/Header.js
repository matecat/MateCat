import React, {useContext, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import QualityReportStore from '../../stores/QualityReportStore'
import QualityReportConstants from '../../constants/QualityReportConstants'
import FilterProjects from './manage/FilterProjects'
import {ActionMenu} from './ActionMenu'
import {UserMenu} from './UserMenu'
import {ComponentExtendInterface} from '../../utils/ComponentExtendInterface'
import {fromJS} from 'immutable'
import {TeamDropdown} from './TeamDropdown'
import MembersFilter from './manage/MembersFilter'

export class HeaderInterface extends ComponentExtendInterface {
  getMoreLinks() {}
}

const headerInterface = new HeaderInterface()

const Header = ({
  isQualityReport,
  showFilterProjects,
  showLinks,
  showModals,
  changeTeam,
  showUserMenu = true,
}) => {
  const {userInfo} = useContext(ApplicationWrapperContext)

  const [jobUrls, setJobUrls] = useState()

  const filterProjectsRef = useRef()

  useEffect(() => {
    const storeJobUrls = (jobInfo) => setJobUrls(jobInfo.get('urls'))

    if (isQualityReport) {
      QualityReportStore.addListener(
        QualityReportConstants.RENDER_REPORT,
        storeJobUrls,
      )
    }

    return () =>
      QualityReportStore.removeListener(
        QualityReportConstants.RENDER_REPORT,
        storeJobUrls,
      )
  }, [isQualityReport])

  const {teams = []} = userInfo ?? {}
  const selectedTeam = teams.find(({isSelected}) => isSelected)

  const canRenderMembersFilter =
    selectedTeam &&
    selectedTeam.type === 'general' &&
    selectedTeam.members &&
    selectedTeam.members.length > 1

  return (
    <section className="header-container">
      <a href="/" className="logo" />
      <div className="header-elements">
        {showFilterProjects && <FilterProjects ref={filterProjectsRef} />}
        <div>
          {showLinks ? (
            <div>
              <ul id="menu-site">
                <li>
                  <a href="https://site.matecat.com">About</a>
                </li>
                <li>
                  <a href="https://site.matecat.com/benefits/">Benefits</a>
                </li>
                <li>
                  <a href="https://site.matecat.com/outsourcing/">Outsource</a>
                </li>
                <li>
                  <a href="https://guides.matecat.com/">User Guide</a>
                </li>
                {headerInterface.getMoreLinks()}
              </ul>
            </div>
          ) : (
            ''
          )}
        </div>

        {canRenderMembersFilter && (
          <MembersFilter
            selectedTeam={fromJS(selectedTeam)}
            currentUser={filterProjectsRef.current.currentUser}
            setCurrentUser={filterProjectsRef.current.handleSetCurrentUser}
          />
        )}

        {!!showFilterProjects && (
          <TeamDropdown
            isManage={showFilterProjects}
            showModals={showModals}
            changeTeam={changeTeam}
          />
        )}
        {!!isQualityReport && jobUrls && (
          <ActionMenu jobUrls={jobUrls.toJS()} />
        )}
        {showUserMenu && <UserMenu />}
      </div>
    </section>
  )
}

Header.propTypes = {
  isQualityReport: PropTypes.bool,
  showFilterProjects: PropTypes.bool,
  showLinks: PropTypes.bool,
  showModals: PropTypes.bool,
  changeTeam: PropTypes.func,
  showUserMenu: PropTypes.bool,
}

export default Header
