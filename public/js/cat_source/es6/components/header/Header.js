import React, {useContext, useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper'
import CatToolActions from '../../actions/CatToolActions'
import QualityReportStore from '../../stores/QualityReportStore'
import QualityReportConstants from '../../constants/QualityReportConstants'
import FilterProjects from './manage/FilterProjects'
import {ActionMenu} from './ActionMenu'
import {UserMenu} from './UserMenu'
import {ComponentExtendInterface} from '../../utils/ComponentExtendInterface'
import Immutable from 'immutable'
import {TeamDropdown} from './TeamDropdown'

export class HeaderInterface extends ComponentExtendInterface {
  getMoreLinks() {}
}

const headerInterface = new HeaderInterface()

const Header = ({
  isQualityReport,
  showFilterProjects,
  showLinks,
  showModals,
  showTeams,
  changeTeam,
  showUserMenu = true,
}) => {
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [jobUrls, setJobUrls] = useState()

  useEffect(() => {
    let tmOut

    if (isUserLogged) {
      tmOut = setTimeout(function () {
        CatToolActions.showHeaderTooltip()
      }, 2000)
    }

    return () => clearTimeout(tmOut)
  }, [isUserLogged])

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

  return (
    <section className="nav-bar ui grid">
      <nav className="sixteen wide column navigation">
        <div className="ui grid">
          <div className="three wide column" data-testid="logo">
            <a href="/" className="logo" />
          </div>
          {showFilterProjects && (
            <div className="nine wide column">
              <FilterProjects selectedTeam={Immutable.fromJS(selectedTeam)} />
            </div>
          )}

          <div
            className={`${showLinks ? 'user-teams thirteen' : 'user-teams four'} wide column right floated`}
          >
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
                    <a href="https://site.matecat.com/outsourcing/">
                      Outsource
                    </a>
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
        </div>
      </nav>
    </section>
  )
}

Header.propTypes = {
  isQualityReport: PropTypes.bool,
  showFilterProjects: PropTypes.bool,
  showLinks: PropTypes.bool,
  showModals: PropTypes.bool,
  showTeams: PropTypes.bool,
  changeTeam: PropTypes.func,
  showUserMenu: PropTypes.bool,
}

export default Header
