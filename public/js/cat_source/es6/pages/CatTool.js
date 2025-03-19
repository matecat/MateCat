import React, {
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react'
import {useHotkeys} from 'react-hotkeys-hook'
import {Header} from '../components/header/cattol/Header'
import SegmentsContainer from '../components/segments/SegmentsContainer'
import CatToolStore from '../stores/CatToolStore'
import CatToolConstants from '../constants/CatToolConstants'
import OfflineUtils from '../utils/offlineUtils'
import SegmentActions from '../actions/SegmentActions'
import CatToolActions from '../actions/CatToolActions'
import SegmentStore from '../stores/SegmentStore'
import SegmentConstants from '../constants/SegmentConstants'
import useSegmentsLoader from '../hooks/useSegmentsLoader'
import LXQ from '../utils/lxq.main'
import {getTmKeysUser} from '../api/getTmKeysUser'
import {getMTEngines as getMtEnginesApi} from '../api/getMTEngines'
import {
  DEFAULT_ENGINE_MEMORY,
  SETTINGS_PANEL_TABS,
  SettingsPanel,
} from '../components/settingsPanel'
import {getTmKeysJob} from '../api/getTmKeysJob'
import {getSupportedLanguages} from '../api/getSupportedLanguages'
import ApplicationStore from '../stores/ApplicationStore'
import useProjectTemplates from '../hooks/useProjectTemplates'
import {Shortcuts} from '../utils/shortcuts'
import CommonUtils from '../utils/commonUtils'
import {CattoolFooter} from '../components/footer/CattoolFooter'
import {mountPage} from './mountPage'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper'
import SocketListener from '../sse/SocketListener'
import Speech2Text from '../utils/speech2text'
import {initTagSignature} from '../components/segments/utils/DraftMatecatUtils/tagModel'
import {
  ONBOARDING_PAGE,
  OnboardingTooltips,
} from '../components/header/OnboardingTooltips'
import {
  CHARS_SIZE_COUNTER_TYPES,
  charsSizeCounter,
} from '../utils/charsSizeCounterUtil'
import {CatToolInterface} from './CatToolInterface'

const urlParams = new URLSearchParams(window.location.search)
const initialStateIsOpenSettings = Boolean(urlParams.get('openTab'))

const cattoolInterface = new CatToolInterface()

function CatTool() {
  useHotkeys(
    Shortcuts.cattol.events.openSettings.keystrokes[Shortcuts.shortCutsKeyType],
    () => CatToolActions.openSettingsPanel(SETTINGS_PANEL_TABS.editorSettings),
    {enableOnContentEditable: true},
  )
  const {isUserLogged, userInfo} = useContext(ApplicationWrapperContext)

  const [options, setOptions] = useState({})
  const [wasInitSegments, setWasInitSegments] = useState(false)
  const [isFreezingSegments, setIsFreezingSegments] = useState(false)
  const [openSettings, setOpenSettings] = useState({
    isOpen: initialStateIsOpenSettings,
  })
  const [tmKeys, setTmKeys] = useState()
  const [mtEngines, setMtEngines] = useState([DEFAULT_ENGINE_MEMORY])

  const [supportedLanguages, setSupportedLanguages] = useState([])
  const [isAnalysisCompleted, setIsAnalysisCompleted] = useState(false)

  const [jobMetadata, setJobMetadata] = useState()

  const startSegmentIdRef = useRef(UI.startSegmentId)
  const callbackAfterSegmentsResponseRef = useRef()

  const {isLoading: isLoadingSegments, result: segmentsResult} =
    useSegmentsLoader({
      segmentId: options?.segmentId
        ? options?.segmentId
        : startSegmentIdRef.current,
      where: options?.where,
      isAnalysisCompleted,
    })

  const {projectTemplates, currentProjectTemplate, modifyingCurrentTemplate} =
    useProjectTemplates()

  const closeSettings = useCallback(() => setOpenSettings({isOpen: false}), [])
  const openTmPanel = () =>
    setOpenSettings({isOpen: true, tab: SETTINGS_PANEL_TABS.editorSettings})

  const getTmKeys = () => {
    const promises = [getTmKeysJob(), getTmKeysUser()]
    let modifiedTemplate = {}
    Promise.all(promises)
      .then((values) => {
        const uniqueKeys = values
          .flatMap((item) => [...item.tm_keys])
          .reduce(
            (acc, cur) =>
              !acc.some(({key}) => key === cur.key) ? [...acc, cur] : acc,
            [],
          )
        const updatedTmKeys = uniqueKeys.map((key) => {
          return {
            ...key,
            id: key.key,
            isActive: Boolean(key.r || key.w),
            isLocked: !key.owner,
          }
        })
        setTmKeys(updatedTmKeys)
        modifyingCurrentTemplate((prevTemplate) => {
          modifiedTemplate = {
            ...prevTemplate,
            tm: updatedTmKeys.filter(({isActive}) => isActive),
            getPublicMatches: config.get_public_matches === 1,
          }
          return modifiedTemplate
        })
      })
      .catch(() => setTmKeys([]))
      .finally(() => getMTEngines(modifiedTemplate))
  }

  const getMTEngines = (prevTemplate) => {
    const setMTCurrentFakeTemplate = () => {
      if (config.active_engine && config.active_engine.id) {
        const activeMT = config.active_engine
        if (activeMT) {
          modifyingCurrentTemplate(() => ({
            ...prevTemplate,
            mt: {
              ...prevTemplate.mt,
              id: activeMT.id,
            },
          }))
        }
      }
    }

    if (config.ownerIsMe) {
      getMtEnginesApi().then((mtEngines) => {
        setMtEngines([DEFAULT_ENGINE_MEMORY, ...mtEngines])
        setMTCurrentFakeTemplate()
      })
    } else {
      setMTCurrentFakeTemplate()
    }
  }

  // actions listener
  useEffect(() => {
    // CatTool onRender action
    getTmKeys()
    const onRenderHandler = (options) => {
      const {
        actionType, // eslint-disable-line
        startSegmentId,
        callbackAfterSegmentsResponse,
        ...restOptions
      } = options
      setOptions((prevState) => ({
        ...prevState,
        ...(options.segmentToOpen && {
          segmentId: Symbol(options.segmentToOpen),
        }),
        ...restOptions,
      }))
      if (startSegmentId) startSegmentIdRef.current = startSegmentId.toString()
      if (callbackAfterSegmentsResponse)
        callbackAfterSegmentsResponseRef.current = callbackAfterSegmentsResponse
    }
    const openSettingsPanel = ({value}) =>
      setOpenSettings({isOpen: true, tab: value})

    CatToolStore.addListener(CatToolConstants.ON_RENDER, onRenderHandler)
    CatToolStore.addListener(
      CatToolConstants.OPEN_SETTINGS_PANEL,
      openSettingsPanel,
    )

    // segments action
    const freezingSegments = (isFreezing) => setIsFreezingSegments(isFreezing)
    const getMoreSegments = (where) => {
      const segmentId =
        where === 'after'
          ? SegmentStore.getLastSegmentId()
          : where === 'before'
            ? SegmentStore.getFirstSegmentId()
            : ''

      setOptions((prevState) => ({...prevState, segmentId, where}))
    }
    const checkAnalysisState = ({analysis_complete}) => {
      setIsAnalysisCompleted(analysis_complete)

      /*if (!analysis_complete)
        ModalsActions.showModalComponent(
          FatalErrorModal,
          {
            text: (
              <span>
                Access to the editor page is forbidden until the project's
                analysis is complete.
                <br />
                To follow the analysis' progress,{' '}
                <a
                  rel="noreferrer"
                  href={`/jobanalysis/${config.id_project}-${config.id_job}-${config.password}`}
                  target="_blank"
                >
                  click here
                </a>
                .
              </span>
            ),
          },
          'Analysis in progress',
          undefined,
          undefined,
          true,
        )*/
    }
    window.onbeforeunload = function (e) {
      return CommonUtils.goodbye(e)
    }
    SegmentStore.addListener(
      SegmentConstants.FREEZING_SEGMENTS,
      freezingSegments,
    )
    SegmentStore.addListener(
      SegmentConstants.GET_MORE_SEGMENTS,
      getMoreSegments,
    )
    CatToolStore.addListener(CatToolConstants.SET_PROGRESS, checkAnalysisState)

    const getJobMetadata = ({jobMetadata}) => setJobMetadata(jobMetadata)

    CatToolStore.addListener(CatToolConstants.GET_JOB_METADATA, getJobMetadata)
    CatToolActions.getJobMetadata({
      idJob: config.id_job,
      password: config.password,
    })

    return () => {
      CatToolStore.removeListener(CatToolConstants.ON_RENDER, onRenderHandler)
      CatToolStore.removeListener(
        CatToolConstants.OPEN_SETTINGS_PANEL,
        openSettingsPanel,
      )
      SegmentStore.removeListener(
        SegmentConstants.FREEZING_SEGMENTS,
        freezingSegments,
      )
      SegmentStore.removeListener(
        SegmentConstants.GET_MORE_SEGMENTS,
        getMoreSegments,
      )
      CatToolStore.removeListener(
        CatToolConstants.SET_PROGRESS,
        checkAnalysisState,
      )
      CatToolStore.removeListener(
        CatToolConstants.GET_JOB_METADATA,
        getJobMetadata,
      )
    }
  }, [])

  // on mount dispatch some actions
  useEffect(() => {
    getSupportedLanguages()
      .then((data) => {
        ApplicationStore.setLanguages(data)
        setSupportedLanguages(data)
      })
      .catch((error) =>
        console.log('Error retrieving supported languages', error),
      )
    CatToolActions.onRender()
    $('html').trigger('start')
    UI.splittedTranslationPlaceholder = '##$_SPLIT$##'
  }, [])

  // handle getSegments result
  useEffect(() => {
    if (!segmentsResult) return
    const {where, errors} = segmentsResult
    // Dispatch error get segments
    if (errors) {
      const {type, ...errors} = segmentsResult // eslint-disable-line
      if (errors.length)
        UI.processErrors(
          errors,
          where === 'center' ? 'getSegments' : 'getMoreSegments',
        )
      OfflineUtils.failedConnection()
      return
    }

    const {segmentId, data} = segmentsResult
    if (where === 'center') {
      // Init segments
      // TODO: da verificare se serve: $(document).trigger('segments:load', data)
      $(document).trigger('segments:load', data)

      if (
        !Object.entries(data.files)
          .map(([, value]) => value.segments)
          .flat()
          .find(
            (segment) =>
              segment.sid === startSegmentIdRef?.current.split('-')[0],
          )
      ) {
        const firstFile = data.files[Object.keys(data.files)[0]]
        if (firstFile) {
          startSegmentIdRef.current = firstFile.segments[0].sid
        }
      }
      // TODO: da verificare se serve: this.body.addClass('loaded')
      $('body').addClass('loaded')

      const haveDataFilesEntries = Boolean(
        typeof data.files !== 'undefined' && Object.keys(data?.files)?.length,
      )

      if (haveDataFilesEntries) {
        if (options?.openCurrentSegmentAfter && !segmentId)
          SegmentActions.openSegment(
            UI.firstLoad ? UI.currentSegmentId : startSegmentIdRef?.current,
          )
      }
      CatToolActions.updateFooterStatistics()
      // TODO: da verificare se serve: $(document).trigger('getSegments_success')
      $(document).trigger('getSegments_success')

      // Open segment
      if (startSegmentIdRef?.current && haveDataFilesEntries)
        SegmentActions.openSegment(startSegmentIdRef?.current)

      setWasInitSegments(true)
    } else {
      // more segments
      // TODO: da verificare se serve: $(window).trigger('segmentsAdded', {resp: data.files})
      $(window).trigger('segmentsAdded', {resp: data.files})
    }
    if (config.isReview) {
      SegmentActions.addPreloadedIssuesToSegment()
    }
  }, [segmentsResult, options?.openCurrentSegmentAfter])

  // execute callback option from onRender action
  useEffect(() => {
    if (
      !segmentsResult ||
      !segmentsResult?.data ||
      !callbackAfterSegmentsResponseRef?.current
    )
      return
    callbackAfterSegmentsResponseRef.current()
  }, [segmentsResult])

  // call UI.init execute after first segments request
  useEffect(() => {
    if (!wasInitSegments) return
    UI.init()
    setTimeout(function () {
      UI.checkWarnings(true)
    }, 1000)
    UI.registerFooterTabs()
  }, [wasInitSegments])

  // user metadata options initialization
  useEffect(() => {
    const metadata = userInfo?.metadata
    if (metadata) {
      if (Speech2Text.enabled(metadata)) Speech2Text.init()
      initTagSignature(metadata)
      if (LXQ.enabled(metadata)) LXQ.init()
    }
  }, [userInfo?.metadata])

  useEffect(() => {
    CatToolStore.setCurrentProjectTemplate(currentProjectTemplate)

    if (
      typeof currentProjectTemplate?.characterCounterMode === 'string' ||
      typeof currentProjectTemplate?.characterCounterCountTags === 'boolean'
    ) {
      charsSizeCounter.map = currentProjectTemplate.characterCounterMode
      SegmentActions.changeCharactersCounterRules()
    }
  }, [currentProjectTemplate])

  const isFakeCurrentTemplateReady =
    projectTemplates.length &&
    typeof projectTemplates[1] !== 'undefined' &&
    (!config.active_engine?.id ||
      (config.active_engine?.id &&
        typeof projectTemplates[1].mt !== 'undefined')) &&
    Array.isArray(projectTemplates[1].tm)

  useEffect(() => {
    if (isFakeCurrentTemplateReady && typeof jobMetadata?.job !== 'undefined') {
      const isValidPresetCharacterMode = Object.values(
        CHARS_SIZE_COUNTER_TYPES,
      ).some((value) => value === cattoolInterface.getCharacterCounterMode())

      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        tmPrioritization: jobMetadata?.job?.tm_prioritization === 1,
        characterCounterCountTags:
          jobMetadata?.job?.character_counter_count_tags === 1,
        characterCounterMode:
          typeof jobMetadata?.job?.character_counter_mode === 'string'
            ? jobMetadata?.job?.character_counter_mode
            : isValidPresetCharacterMode
              ? cattoolInterface.getCharacterCounterMode()
              : undefined,
      }))
    }
  }, [jobMetadata?.job, isFakeCurrentTemplateReady, modifyingCurrentTemplate])

  return (
    <>
      <Header
        pid={config.id_project}
        jid={config.id_job}
        password={config.password}
        reviewPassword={config.review_password}
        source_code={config.source_rfc}
        target_code={config.target_rfc}
        isReview={config.isReview}
        revisionNumber={config.revisionNumber}
        userLogged={isUserLogged}
        projectName={config.project_name}
        projectCompletionEnabled={config.project_completion_feature_enabled}
        secondRevisionsCount={config.secondRevisionsCount}
        overallQualityClass={config.overall_quality_class}
        qualityReportHref={config.quality_report_href}
        allowLinkToAnalysis={config.allow_link_to_analysis}
        analysisEnabled={config.analysis_enabled}
        isGDriveProject={config.isGDriveProject}
        showReviseLink={config.footer_show_revise_link}
        openTmPanel={openTmPanel}
        jobMetadata={jobMetadata}
      />
      <SocketListener
        isAuthenticated={isUserLogged}
        userId={isUserLogged ? userInfo.user.uid : null}
      />
      <div className="main-container">
        <div data-mount="review-side-panel"></div>
        <div
          id="outer"
          className={
            isLoadingSegments || !isUserLogged
              ? options?.where === 'before'
                ? 'loadingBefore'
                : options?.where === 'after'
                  ? 'loadingAfter'
                  : 'loading'
              : ''
          }
        >
          {isUserLogged ? (
            <article id="file" className="loading mbc-commenting-closed">
              <div className="article-segments-container">
                <SegmentsContainer
                  isReview={config.isReview}
                  startSegmentId={UI.startSegmentId?.toString()}
                  firstJobSegment={config.first_job_segment}
                  languages={supportedLanguages}
                />
              </div>
            </article>
          ) : (
            !isUserLogged &&
            typeof userInfo === 'undefined' && <div className="signin-bg" />
          )}
          <div id="loader-getMoreSegments" />
        </div>
        <div id="plugin-mount-point"></div>
        {isFreezingSegments && <div className="freezing-overlay"></div>}
      </div>

      {isUserLogged && openSettings.isOpen && isFakeCurrentTemplateReady && (
        <SettingsPanel
          {...{
            onClose: closeSettings,
            isOpened: openSettings.isOpen,
            tabOpen: openSettings.tab,
            tmKeys,
            setTmKeys,
            mtEngines,
            setMtEngines,
            sourceLang: {
              name: ApplicationStore.getLanguageNameFromLocale(
                config.source_rfc,
              ),
              code: config.source_rfc,
            },
            targetLangs: [
              {
                name: ApplicationStore.getLanguageNameFromLocale(
                  config.target_rfc,
                ),
                code: config.target_rfc,
              },
            ],
            projectTemplates,
            currentProjectTemplate,
            modifyingCurrentTemplate,
          }}
        />
      )}
      {isUserLogged && (
        <CattoolFooter
          idProject={config.id_project}
          idJob={config.id_job}
          password={config.password}
          source={config.source_rfc}
          target={config.target_rfc}
          isReview={config.isReview}
          isCJK={config.isCJK}
          languagesArray={supportedLanguages}
        />
      )}
      <OnboardingTooltips
        show={isUserLogged && userInfo.user}
        continous={true}
        page={ONBOARDING_PAGE.CATTOOL}
      />
    </>
  )
}

export default CatTool

UI.start()
mountPage({
  Component: CatTool,
  rootElement: document.getElementsByClassName('page-content')[0],
})
