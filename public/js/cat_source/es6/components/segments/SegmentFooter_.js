import React, {useCallback, useEffect, useMemo, useState} from 'react'
import PropTypes from 'prop-types'
import {isUndefined, size} from 'lodash'
import Cookies from 'js-cookie'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentActions from '../../actions/SegmentActions'
import SegmentFooterMultiMatches from './SegmentFooterMultiMatches'
import SegmentTabConcordance from './SegmentFooterTabConcordance'
import SegmentTabGlossary from './SegmentFooterTabGlossary'
import SegmentTabConflicts from './SegmentFooterTabConflicts'
import SegmentFooterTabMatches from './SegmentFooterTabMatches'
import SegmentFooterTabMessages from './SegmentFooterTabMessages'

const TAB_ITEMS = {
  matches: {
    label: 'Translation Matches',
    code: 'tm',
    tabClass: 'matches',
  },
  concordances: {
    label: 'TM Search',
    code: 'cc',
    tabClass: 'concordances',
  },
  glossary: {
    label: 'Glossary',
    code: 'gl',
    tabClass: 'glossary',
  },
  alternatives: {
    label: 'Translation conflicts',
    code: 'al',
    tabClass: 'alternatives',
  },
  messages: {
    label: 'Messages',
    code: 'notes',
    tabClass: 'segment-notes',
  },
  multiMatches: {
    label: 'Crosslanguage Matches',
    code: 'cl',
    tabClass: 'cross-matches',
  },
}

function SegmentFooter_({sid, segment, fid}) {
  const [configurations, setConfigurations] = useState(
    SegmentStore._footerTabsConfig.toJS(),
  )
  const [tabItems, setTabItems] = useState(
    Object.entries(TAB_ITEMS).map(([key, value]) => {
      return {
        ...value,
        name: key,
        enabled: false,
        visible: false,
        open: false,
        elements: [],
        label:
          value.code === 'tm'
            ? `Translation Matches ${!config.mt_enabled ? ' (No MT) ' : ''}`
            : value.label,
      }
    }),
  )
  const [tabStateChanges, setTabStateChanges] = useState({})
  const [activeTab, setActiveTab] = useState({})

  const hideMatchesCookie = useMemo(() => {
    const cookieName = config.isReview ? 'hideMatchesReview' : 'hideMatches'
    if (!isUndefined(Cookies.get(`${cookieName}-${config.id_job}`)))
      return Cookies.get(`${cookieName}-${config.id_job}`) === 'true'
    return false
  }, [])

  const setHideMatchesCookie = useCallback((hideMatches) => {
    const cookieName = config.isReview ? 'hideMatchesReview' : 'hideMatches'
    Cookies.set(`${cookieName}-${config.id_job}`, hideMatches, {
      expires: 3,
      secure: true,
    })
  }, [])

  // add listeners
  useEffect(() => {
    const registerTab = (tabs, configs) => setConfigurations(configs)
    const modifyTabVisibility = (name, visible) =>
      setTabStateChanges({name, visible})
    const openTab = (sidProp, name) => sid === sidProp && setActiveTab({name})
    const addTabIndex = (sidProp, name, index) =>
      sid === sidProp && setTabStateChanges({name, index})

    //document.addEventListener('keydown', this.handleShortcutsKeyDown)
    SegmentStore.addListener(SegmentConstants.REGISTER_TAB, registerTab)
    SegmentStore.addListener(
      SegmentConstants.MODIFY_TAB_VISIBILITY,
      modifyTabVisibility,
    )
    SegmentStore.addListener(SegmentConstants.OPEN_TAB, openTab)
    SegmentStore.addListener(SegmentConstants.ADD_TAB_INDEX, addTabIndex)
    /*SegmentStore.addListener(SegmentConstants.CLOSE_TABS, this.closeAllTabs)
    SegmentStore.addListener(
      SegmentConstants.SHOW_FOOTER_MESSAGE,
      this.showMessage,
    ) */

    return () => {
      SegmentStore.removeListener(SegmentConstants.REGISTER_TAB, registerTab)
      SegmentStore.removeListener(
        SegmentConstants.MODIFY_TAB_VISIBILITY,
        modifyTabVisibility,
      )
      SegmentStore.removeListener(SegmentConstants.OPEN_TAB, openTab)
    }
  }, [sid])

  // add items
  useEffect(() => {
    setTabItems((prevState) =>
      prevState
        .map((item) => ({
          ...item,
          ...(item.name === 'alternatives' && {
            enabled: segment.alternatives && size(segment.alternatives) > 0,
            visible: segment.alternatives && size(segment.alternatives) > 0,
            open: segment.alternatives && size(segment.alternatives) > 0,
          }),
          ...(item.name === 'messages' && {
            enabled: segment.notes && segment.notes.length > 0,
            visible: segment.notes && segment.notes.length > 0,
          }),
          ...(item.name === 'multiMatches' && {
            enabled:
              UI.crossLanguageSettings && UI.crossLanguageSettings.primary,
            visible:
              UI.crossLanguageSettings && UI.crossLanguageSettings.primary,
          }),
        }))
        .map((item) => ({
          ...item,
          ...(configurations[item.name] && {...configurations[item.name]}),
        })),
    )
  }, [segment, configurations])

  // set active tab
  useEffect(() => {
    if (!activeTab?.name) return
    const hideMatches = hideMatchesCookie
    setTabItems((prevState) => {
      const tab = prevState.find(({name}) => name === activeTab.name)
      if (tab.open && !activeTab.forceOpen && !hideMatches) {
        tab.open = false
        setHideMatchesCookie(true)
      } else {
        tab.open = true
        tab.visible = true
        setHideMatchesCookie(false)
      }
      return prevState.map((item) =>
        item.name === tab.name ? {...tab} : {...item, open: false},
      )
    })
  }, [activeTab, hideMatchesCookie, setHideMatchesCookie])

  // on active tab call SegmentAction
  useEffect(() => {
    if (!activeTab?.name) return
    SegmentActions.setTabOpen(sid, activeTab.name)
  }, [activeTab, sid])

  // update tab state changes
  useEffect(() => {
    if (!Object.keys(tabStateChanges).length) return
    setTabItems((prevState) =>
      prevState.map((item) =>
        (tabStateChanges.name && item.name === tabStateChanges.name) ||
        !tabStateChanges.name
          ? {...item, ...tabStateChanges}
          : item,
      ),
    )
  }, [tabStateChanges])

  const isTabLoading = ({code}) => {
    switch (code) {
      case 'tm':
        return (
          isUndefined(segment.contributions) ||
          (isUndefined(segment.contributions.matches) &&
            segment.contributions.errors.length === 0)
        )
      case 'cl':
        return (
          isUndefined(segment.cl_contributions) ||
          (isUndefined(segment.cl_contributions.matches) &&
            segment.cl_contributions.errors.length === 0)
        )
      case 'gl':
        return isUndefined(segment.glossary)
      default:
        return false
    }
  }

  const getTabIndex = ({code, index}) => {
    switch (code) {
      case 'tm':
        return segment.contributions.matches.length
      case 'cl':
        return segment.cl_contributions.matches.length
      case 'gl':
        return size(segment.glossary)
      default:
        return index
    }
  }

  const getTabContainer = (tab, activeClass) => {
    const openClass = activeClass == 'active' ? 'open' : ''
    switch (tab.code) {
      case 'tm':
        return (
          <SegmentFooterTabMatches
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            id_segment={sid}
            segment={segment}
          />
        )
      case 'cc':
        return (
          <SegmentTabConcordance
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            id_segment={sid}
            segment={segment}
          />
        )
      case 'gl':
        return (
          <SegmentTabGlossary
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            id_segment={sid}
            segment={segment}
          />
        )
      case 'al':
        return (
          <SegmentTabConflicts
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            segment={segment}
            id_segment={sid}
          />
        )
      case 'notes':
        return (
          <SegmentFooterTabMessages
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            id_segment={sid}
            notes={segment.notes}
            metadata={segment.metadata}
            context_groups={segment.context_groups}
            segmentSource={segment.segment}
            segment={segment}
          />
        )
      case 'cl':
        return (
          <SegmentFooterMultiMatches
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            id_segment={sid}
            segment={segment}
          />
        )
      default:
        return ''
    }
  }

  const getListItem = (tab) => {
    const isLoading = isTabLoading(tab)
    const countResult = !isLoading && getTabIndex(tab)
    return (
      <li
        key={tab.code}
        className={`${!tab.visible ? 'hide' : ''} ${
          tab.open && !hideMatchesCookie ? 'active' : ''
        } tab-switcher tab-switcher-${tab.code} ${
          isLoading ? 'loading-tab' : ''
        }`}
        id={'segment-' + sid + tab.code}
        data-tab-class={tab.tabClass}
        data-code={tab.code}
        onClick={() => setActiveTab({name: tab.name})}
      >
        {!isLoading ? (
          <a tabIndex="-1">
            {tab.label}
            <span className="number">
              {!isLoading && countResult ? ' (' + countResult + ')' : ''}
            </span>
          </a>
        ) : (
          <a tabIndex="-1">
            {tab.label}
            <span className="loader loader_on" />
          </a>
        )}
      </li>
    )
  }

  return (
    <div className="footer toggle">
      <ul className="submenu">
        {tabItems.filter(({enabled}) => enabled).map((tab) => getListItem(tab))}

        {/* {this.state.showMessage ? (
          <li className="footer-message">{this.state.message}</li>
        ) : null} */}
      </ul>
      {/* {containers} */}
      {tabItems
        .filter(({enabled}) => enabled)
        .map((tab) =>
          getTabContainer(tab, tab.open && !hideMatchesCookie ? 'active' : ''),
        )}
      <div className="addtmx-tr white-tx">
        <a
          className="open-popup-addtm-tr"
          onClick={() => UI.openLanguageResourcesPanel()}
        >
          Add private resources
        </a>
      </div>
    </div>
  )
}

SegmentFooter_.propTypes = {
  sid: PropTypes.string,
  segment: PropTypes.object,
  fid: PropTypes.string,
}

export default SegmentFooter_
