import React, {
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react'
import {isUndefined, size} from 'lodash'
import Cookies from 'js-cookie'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import SegmentActions from '../../actions/SegmentActions'
import SegmentFooterMultiMatches from './SegmentFooterMultiMatches'
import SegmentTabConcordance from './SegmentFooterTabConcordance'
import {SegmentFooterTabGlossary} from './SegmentFooterTabGlossary'
import SegmentTabConflicts from './SegmentFooterTabConflicts'
import SegmentFooterTabMatches from './SegmentFooterTabMatches'
import SegmentFooterTabMessages from './SegmentFooterTabMessages'
import {SegmentContext} from './SegmentContext'
import SegmentUtils from '../../utils/segmentUtils'
import {SegmentFooterTabAiAssistant} from './SegmentFooterTabAiAssistant'
import IconCloseCircle from '../icons/IconCloseCircle'
import CatToolActions from '../../actions/CatToolActions'
import {isMacOS} from '../../utils/Utils'

export const TAB = {
  MATCHES: 'matches',
  CONCORDANCES: 'concordances',
  GLOSSARY: 'glossary',
  ALTERNATIVES: 'alternatives',
  MESSAGES: 'messages',
  MULTIMATCHES: 'multiMatches',
  AI_ASSISTANT: 'AiAssistant',
}

const TAB_ITEMS = {
  [TAB.MATCHES]: {
    label: 'Translation Matches',
    code: 'tm',
    tabClass: 'matches',
    isLoading: false,
  },
  [TAB.CONCORDANCES]: {
    label: 'TM Search',
    code: 'cc',
    tabClass: 'concordances',
    isLoading: false,
  },
  [TAB.GLOSSARY]: {
    label: 'Glossary',
    code: 'gl',
    tabClass: 'glossary',
    isLoading: false,
  },
  [TAB.ALTERNATIVES]: {
    label: 'Translation conflicts',
    code: 'al',
    tabClass: 'alternatives',
    isLoading: false,
  },
  [TAB.MESSAGES]: {
    label: 'Messages',
    code: 'notes',
    tabClass: 'segment-notes',
    isLoading: false,
  },
  [TAB.MULTIMATCHES]: {
    label: 'Crosslanguage Matches',
    code: 'cl',
    tabClass: 'cross-matches',
    isLoading: false,
  },
  [TAB.AI_ASSISTANT]: {
    label: 'AI Assistant',
    code: 'ai',
    tabClass: 'ai-assistant',
    isLoading: false,
    isEnableCloseButton: true,
  },
}
const DELAY_MESSAGE = 7000

function SegmentFooter() {
  const isMac = isMacOS()

  const [configurations, setConfigurations] = useState(
    SegmentStore._footerTabsConfig.toJS(),
  )
  const [tabItems, setTabItems] = useState(
    Object.entries(TAB_ITEMS).map(([key, value]) => ({
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
    })),
  )
  const [tabStateChanges, setTabStateChanges] = useState(undefined)
  const [activeTab, setActiveTab] = useState(undefined)
  const [userChangedTab, setUserChangedTab] = useState(undefined)
  const [message, setMessage] = useState('')

  const {segment, clientConnected, multiMatchLangs} = useContext(SegmentContext)

  const getHideMatchesCookie = useCallback(() => {
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

  const setTabLoadingStatus = useCallback(({code, isLoading}) => {
    setTabItems((prevState) =>
      prevState.map((tab) => ({
        ...tab,
        isLoading: tab.code === code ? isLoading : tab.isLoading,
      })),
    )
  }, [])

  // Check tab messages has notes
  const hasNotes = useMemo(() => {
    if (!SegmentUtils.segmentHasNote(segment)) return false
    const tabMessagesContext = {
      props: {
        active_class: 'open',
        tab_class: 'segment-notes',
        id_segment: segment.sid,
        notes: segment.notes,
        metadata: segment.metadata,
        context_groups: segment.context_groups,
        segmentSource: segment.segment,
        segment: segment,
      },
      getMetadataNoteTemplate: () =>
        segment.metadata?.length > 0 ? segment.metadata : null,
      allowHTML: () => '',
      getNoteContentStructure: (note) => note,
    }
    const notes =
      SegmentFooterTabMessages.prototype.getNotes.call(tabMessagesContext)
    return Array.isArray(notes) && notes.length > 0
  }, [segment])

  const nextTab = useMemo(() => {
    const tabs = tabItems.filter(({enabled, visible}) => enabled && visible)
    const actualTabIndex = tabs.findIndex(({open}) => open)
    return actualTabIndex + 1 <= tabs.length - 1
      ? tabs[actualTabIndex + 1]?.name
      : tabs[0]?.name
  }, [tabItems])

  // add listeners
  useEffect(() => {
    const handleShortcutsKeyDown = (e) => {
      if (segment.opened) {
        if (
          (isMac && e.ctrlKey && e.altKey && e.code === 'KeyS') ||
          (!isMac && e.altKey && e.code === 'KeyS')
        ) {
          setUserChangedTab({[Symbol()]: nextTab})
        }
      }
    }
    const registerTab = (tabs, configs) => setConfigurations(configs)
    const modifyTabVisibility = (name, visible) =>
      setTabStateChanges({name, visible, enabled: visible})
    const openTab = (sidProp, name) =>
      segment.sid === sidProp && setActiveTab({name, forceOpen: true})
    const addTabIndex = (sidProp, name, index) =>
      segment.sid === sidProp && setTabStateChanges({name, index})
    const closeAllTabs = () => setTabStateChanges({visible: false})
    const showMessage = (sidProp, message) =>
      segment.sid === sidProp && setMessage(message)
    const hideAIAssistant = () =>
      setTabStateChanges({
        name: TAB.AI_ASSISTANT,
        visible: false,
        enabled: false,
      })

    document.addEventListener('keydown', handleShortcutsKeyDown)
    SegmentStore.addListener(SegmentConstants.REGISTER_TAB, registerTab)
    SegmentStore.addListener(
      SegmentConstants.MODIFY_TAB_VISIBILITY,
      modifyTabVisibility,
    )
    SegmentStore.addListener(
      SegmentConstants.HIDE_AI_ASSISTANT,
      hideAIAssistant,
    )
    SegmentStore.addListener(SegmentConstants.OPEN_TAB, openTab)
    SegmentStore.addListener(SegmentConstants.ADD_TAB_INDEX, addTabIndex)
    SegmentStore.addListener(SegmentConstants.CLOSE_TABS, closeAllTabs)
    SegmentStore.addListener(SegmentConstants.SHOW_FOOTER_MESSAGE, showMessage)

    return () => {
      document.removeEventListener('keydown', handleShortcutsKeyDown)
      SegmentStore.removeListener(SegmentConstants.REGISTER_TAB, registerTab)
      SegmentStore.removeListener(
        SegmentConstants.MODIFY_TAB_VISIBILITY,
        modifyTabVisibility,
      )
      SegmentStore.removeListener(
        SegmentConstants.HIDE_AI_ASSISTANT,
        hideAIAssistant,
      )
      SegmentStore.removeListener(SegmentConstants.OPEN_TAB, openTab)
      SegmentStore.removeListener(SegmentConstants.ADD_TAB_INDEX, addTabIndex)
      SegmentStore.removeListener(SegmentConstants.CLOSE_TABS, closeAllTabs)
      SegmentStore.removeListener(
        SegmentConstants.SHOW_FOOTER_MESSAGE,
        showMessage,
      )
    }
  }, [segment?.sid, segment?.opened, nextTab])

  // merge with configurations
  useEffect(() => {
    if (!configurations) return
    setTabItems((prevState) =>
      prevState.map((item) => ({
        ...item,
        ...(configurations[item.name] && {...configurations[item.name]}),
        open: hasNotes
          ? item.name === TAB.MESSAGES
          : !hasNotes && item.name === TAB.MESSAGES
            ? false
            : configurations[item.name]?.open,
      })),
    )
  }, [configurations, segment, hasNotes])

  // add items
  useEffect(() => {
    const hasAlternatives = Boolean(
      segment.alternatives && size(segment.alternatives) > 0,
    )
    const hasMultiMatches = Boolean(multiMatchLangs && multiMatchLangs.primary)

    setTabItems((prevState) =>
      prevState.map((item) => ({
        ...item,
        open:
          item.name !== TAB.ALTERNATIVES && hasAlternatives ? false : item.open,
        ...(item.name === TAB.ALTERNATIVES && {
          visible: hasAlternatives,
          open: hasAlternatives,
        }),
        ...(item.name === TAB.MESSAGES && {
          enabled: hasNotes,
          visible: hasNotes,
        }),
        ...(item.name === TAB.MULTIMATCHES && {
          enabled: hasMultiMatches,
          visible: hasMultiMatches,
        }),
      })),
    )
  }, [segment, multiMatchLangs])

  // check if no tab is open
  useEffect(() => {
    setTabItems((prevState) => {
      const openedTab = prevState.find(({open}) => open)
      return !openedTab || (openedTab && openedTab.open && !openedTab.visible)
        ? prevState.map((item) =>
            item.name === TAB.MATCHES
              ? {...item, open: true}
              : {...item, open: false},
          )
        : prevState
    })
  }, [configurations, segment])

  // set active tab
  useEffect(() => {
    if (!activeTab?.name) return
    const hideMatches = getHideMatchesCookie()
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
  }, [activeTab, getHideMatchesCookie, setHideMatchesCookie])

  // on user change tab
  useEffect(() => {
    const name =
      userChangedTab &&
      userChangedTab[Object.getOwnPropertySymbols(userChangedTab)[0]]
    if (!name) return
    if (!TAB_ITEMS[name].isEnableCloseButton)
      setTimeout(() => SegmentActions.setTabOpen(segment.sid, name))
    setActiveTab({name: name})
  }, [userChangedTab, segment?.sid])

  // update tab state changes
  useEffect(() => {
    if (!tabStateChanges || !Object.keys(tabStateChanges).length) return
    setTabItems((prevState) =>
      prevState.map((item) =>
        (tabStateChanges.name && item.name === tabStateChanges.name) ||
        !tabStateChanges.name
          ? {...item, ...tabStateChanges}
          : item,
      ),
    )
  }, [tabStateChanges])

  // restore active tab
  useEffect(() => {
    if (!activeTab?.name) return
    setTabItems((prevState) =>
      prevState.map((item) => ({
        ...item,
        open: activeTab.name === item.name,
      })),
    )
  }, [segment, activeTab])

  // remove message after a few seconds
  useEffect(() => {
    if (!message) return
    const timeout = setTimeout(() => setMessage(''), DELAY_MESSAGE)

    return () => clearTimeout(timeout)
  }, [message])

  const isInitTabLoading = ({code}) => {
    switch (code) {
      case 'tm':
        if (!clientConnected) return true
        return (
          isUndefined(segment.contributions) ||
          (isUndefined(segment.contributions.matches) &&
            segment.contributions.errors.length === 0)
        )
      case 'cl':
        if (!clientConnected) return true
        return (
          isUndefined(segment.cl_contributions) ||
          (isUndefined(segment.cl_contributions.matches) &&
            segment.cl_contributions.errors.length === 0)
        )
      case 'gl':
        //if (!clientConnected) return true
        return tabItems.find(({code}) => code === 'gl')?.isLoading
      case 'cc':
        if (!clientConnected) return true
        return tabItems.find(({code}) => code === 'cc')?.isLoading
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
        return size(segment.glossary_search_results)
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
            segment={segment}
          />
        )
      case 'gl':
        return (
          <SegmentFooterTabGlossary
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            segment={segment}
            notifyLoadingStatus={setTabLoadingStatus}
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
          />
        )
      case 'notes':
        return (
          <SegmentFooterTabMessages
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
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
            segment={segment}
          />
        )
      case 'ai':
        return (
          <SegmentFooterTabAiAssistant
            key={'container_' + tab.code}
            code={tab.code}
            active_class={openClass}
            tab_class={tab.tabClass}
            segment={segment}
          />
        )
      default:
        return ''
    }
  }

  const getListItem = (tab) => {
    const isLoading = isInitTabLoading(tab)
      ? true
      : tabItems.find(({code}) => code === tab.code)?.isLoading
        ? true
        : false
    const countResult = !isLoading && getTabIndex(tab)
    const onClickRemoveTab = (event) => {
      setTabStateChanges({
        name: TAB.AI_ASSISTANT,
        visible: false,
        enabled: false,
      })
      if (tab.open) SegmentActions.activateTab(segment.sid, TAB.MATCHES)
      event.stopPropagation()
    }

    return (
      <li
        key={tab.code}
        className={`${!tab.visible ? 'hide' : ''} ${
          tab.open && !getHideMatchesCookie() ? 'active' : ''
        } tab-switcher tab-switcher-${tab.code} ${
          isLoading ? 'loading-tab' : ''
        }`}
        id={'segment-' + segment.sid + tab.code}
        data-tab-class={tab.tabClass}
        data-code={tab.code}
        onClick={() => setUserChangedTab({[Symbol()]: tab.name})}
        data-testid={tab.name}
      >
        <a tabIndex="-1">
          {tab.label}
          {!isLoading ? (
            <span className="number">
              {countResult ? ' (' + countResult + ')' : ''}
            </span>
          ) : clientConnected ? (
            <span className="loader loader_on" />
          ) : (
            <i className="icon-warning2 icon" />
          )}

          {tab.isEnableCloseButton && (
            <span className="icon-close" onClick={onClickRemoveTab}>
              <IconCloseCircle />
            </span>
          )}
        </a>
      </li>
    )
  }

  return (
    <div className="footer toggle">
      <ul className="submenu">
        {tabItems.filter(({enabled}) => enabled).map((tab) => getListItem(tab))}
        {message && <li className="footer-message">{message}</li>}
      </ul>
      {tabItems
        .filter(({enabled}) => enabled)
        .map((tab) =>
          getTabContainer(
            tab,
            tab.open && !getHideMatchesCookie() ? 'active' : '',
          ),
        )}
      <div className="addtmx-tr white-tx">
        <a
          className="open-popup-addtm-tr"
          onClick={() => CatToolActions.openSettingsPanel()}
        >
          Add private resources
        </a>
      </div>
    </div>
  )
}

export default SegmentFooter
