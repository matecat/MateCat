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
import SegmentTabMatches from './components/segments/SegmentFooterTabMatches'
import SegmentTabMessages from './components/segments/SegmentFooterTabMessages'
import SegmentButtons from './components/segments/SegmentButtons'
import TranslationIssuesSideButton from './components/review/TranslationIssuesSideButton'
import QaCheckGlossary from './components/segments/utils/qaCheckGlossaryUtils'
import TagUtils from './utils/tagUtils'
import TextUtils from './utils/textUtils'
import CursorUtils from './utils/cursorUtils'
import OfflineUtils from './utils/offlineUtils'
import Shortcuts from './utils/shortcuts'
import SegmentFooterTabMatches from './components/segments/SegmentFooterTabMatches'

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
window.SegmentTabMatches = SegmentTabMatches
window.SegmentTabMessages = SegmentTabMessages
window.SegmentButtons = SegmentButtons
window.TranslationIssuesSideButton = TranslationIssuesSideButton

window.QaCheckGlossary = QaCheckGlossary

window.TagUtils = TagUtils
window.TextUtils = TextUtils
window.CommonUtils = CommonUtils
window.CursorUtils = CursorUtils
window.OfflineUtils = OfflineUtils
window.Shortcuts = Shortcuts

window.SegmentFooterTabMatches = SegmentFooterTabMatches
