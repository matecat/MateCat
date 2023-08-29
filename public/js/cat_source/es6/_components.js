// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
import classnames from 'classnames'

import JobMetadata from './components/header/cattol/JobMetadata'
import SegmentStore from './stores/SegmentStore'
import CatToolStore from './stores/CatToolStore'
import SegmentFilter from './components/header/cattol/segment_filter/segment_filter'
import CatToolActions from './actions/CatToolActions'
import SegmentActions from './actions/SegmentActions'
import ModalsActions from './actions/ModalsActions'
import CreateProjectActions from './actions/CreateProjectActions'

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
import TagUtils from './utils/tagUtils'
import TextUtils from './utils/textUtils'
import CursorUtils from './utils/cursorUtils'
import OfflineUtils from './utils/offlineUtils'
import Shortcuts from './utils/shortcuts'
import SegmentUtils from './utils/segmentUtils'
import SegmentFooterTabMatches from './components/segments/SegmentFooterTabMatches'
import {ModalWindow} from './components/modals/ModalWindow'

window.MC = {}

window.classnames = classnames

window.SegmentFilter = SegmentFilter

window.CatToolActions = CatToolActions
window.SegmentActions = SegmentActions
window.ModalsActions = ModalsActions
window.CreateProjectActions = CreateProjectActions
window.SegmentStore = SegmentStore
window.CatToolStore = CatToolStore

window.Header = Header
window.JobMetadata = JobMetadata

window.ConfirmMessageModal = ConfirmMessageModal
window.JobMetadataModal = JobMetadataModal

window.SegmentBody = SegmentBody
window.SegmentTarget = SegmentTarget
window.SegmentFooter = SegmentFooter
window.SegmentTabMatches = SegmentTabMatches
window.SegmentTabMessages = SegmentTabMessages
window.SegmentButtons = SegmentButtons
window.TranslationIssuesSideButton = TranslationIssuesSideButton

window.TagUtils = TagUtils
window.TextUtils = TextUtils
window.CommonUtils = CommonUtils
window.CursorUtils = CursorUtils
window.OfflineUtils = OfflineUtils
window.Shortcuts = Shortcuts
window.SegmentUtils = SegmentUtils
window.ModalWindow = ModalWindow

window.SegmentFooterTabMatches = SegmentFooterTabMatches
