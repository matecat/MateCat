// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
import classnames from 'classnames'

import JobMetadata from './components/header/cattol/JobMetadata'
import {ModalWindow} from './components/modals/ModalWindow'
import SegmentStore from './stores/SegmentStore'
import SegmentFilter from './components/header/cattol/segment_filter/segment_filter'
import AnalyzeActions from './actions/AnalyzeActions'
import CatToolActions from './actions/CatToolActions'
import SegmentActions from './actions/SegmentActions'
import CommonUtils from './utils/commonUtils'
import Header from './components/header/Header'
import ConfirmMessageModal from './components/modals/ConfirmMessageModal'
import JobMetadataModal from './components/modals/JobMetadataModal'
import SegmentBody from './components/segments/SegmentBody'
import SegmentTarget from './components/segments/SegmentTarget'
import SegmentFooter from './components/segments/SegmentFooter'

window.MC = {}

window.classnames = classnames

window.SegmentFilter = SegmentFilter

window.AnalyzeActions = AnalyzeActions
window.CatToolActions = CatToolActions
window.SegmentActions = SegmentActions

window.SegmentStore = SegmentStore

window.Header = Header
window.JobMetadata = JobMetadata

window.ModalWindow = ModalWindow
window.ConfirmMessageModal = ConfirmMessageModal
window.JobMetadataModal = JobMetadataModal

window.SegmentBody = SegmentBody
window.SegmentTarget = SegmentTarget
window.SegmentFooter = SegmentFooter
window.SegmentTabMatches =
  require('./components/segments/SegmentFooterTabMatches').default
window.SegmentTabMessages =
  require('./components/segments/SegmentFooterTabMessages').default
window.SegmentButtons = require('./components/segments/SegmentButtons').default
window.TranslationIssuesSideButton =
  require('./components/review/TranslationIssuesSideButton').default

window.QaCheckGlossary = require('./components/segments/utils/qaCheckGlossaryUtils')

window.SearchUtils = require('./components/header/cattol/search/searchUtils')
window.TagUtils = require('./utils/tagUtils')
window.TextUtils = require('./utils/textUtils')
window.CommonUtils = CommonUtils
window.CursorUtils = require('./utils/cursorUtils')
window.OfflineUtils = require('./utils/offlineUtils')
window.Shortcuts = require('./utils/shortcuts')
window.Customizations = require('./utils/customizations')
window.SegmentUtils = require('./utils/segmentUtils')
window.DraftMatecatUtils = require('./components/segments/utils/DraftMatecatUtils')

window.LXQ = require('./utils/lxq.main')
window.MBC = require('./utils/mbc.main')
window.Speech2Text = require('./utils/speech2text')
