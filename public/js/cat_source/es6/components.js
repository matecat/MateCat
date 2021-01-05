

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
import JobMetadata from "./components/header/cattol/JobMetadata";

window.MC = {} ;

window.classnames = require('classnames');

window.SegmentFilter = require('./components/header/cattol/segment_filter/segment_filter');
window.NotificationBox = require('./components/notificationsComponent/NotificationBox').default;

window.ManageConstants = require('./constants/ManageConstants');

window.ManageActions = require('./actions/ManageActions');
window.AnalyzeActions = require('./actions/AnalyzeActions');
window.TeamsActions = require('./actions/TeamsActions');
window.ModalsActions = require('./actions/ModalsActions');
window.OutsourceActions = require('./actions/OutsourceActions');
window.CatToolActions = require('./actions/CatToolActions');
window.SegmentActions = require('./actions/SegmentActions');
window.CommentsActions = require('./actions/CommentsActions');

window.ProjectsStore = require('./stores/ProjectsStore');
window.TeamsStore = require('./stores/TeamsStore');
window.SegmentStore = require('./stores/SegmentStore');
window.CatToolStore = require('./stores/CatToolStore');

window.ProjectsContainer = require('./components/projects/ProjectsContainer').default;
window.Header = require("./components/header/Header").default;
window.JobMetadata = require("./components/header/cattol/JobMetadata").default;
window.AnalyzeMain = require('./components/analyze/AnalyzeMain').default;

window.LanguageSelector = require('./components/languageSelector/LanguageSelector').default;

// ui.render
window.SegmentsContainer = require('./components/segments/SegmentsContainer').default;

/*
Todo move this
 */
window.ModalWindow = require('./components/modals/ModalWindowComponent').default;
window.SuccessModal = require('./components/modals/SuccessModal').default;
window.ConfirmRegister = require('./components/modals/ConfirmRegister').default;
window.PreferencesModal = require('./components/modals/PreferencesModal').default;
window.ResetPasswordModal = require('./components/modals/ResetPasswordModal').default;
window.LoginModal = require('./components/modals/LoginModal').default;
window.ForgotPasswordModal = require('./components/modals/ForgotPasswordModal').default;
window.RegisterModal = require('./components/modals/RegisterModal').default;
window.ConfirmMessageModal = require('./components/modals/ConfirmMessageModal').default;
window.OutsourceModal = require('./components/modals/OutsourceModal').default;
window.SplitJobModal = require('./components/modals/SplitJob').default;
window.DQFModal = require('./components/modals/DQFModal').default;
window.ShortCutsModal = require('./components/modals/ShortCutsModal').default;
window.CreateTeamModal = require('./components/modals/CreateTeam').default;
window.ModifyTeamModal = require('./components/modals/ModifyTeam').default;
window.ModifyTeamModal = require('./components/modals/ModifyTeam').default;
window.JobMetadataModal = require('./components/modals/JobMetadataModal').default;
/*****/


/*
Override by plugins
 */
window.SegmentBody = require('./components/segments/SegmentBody').default;
window.SegmentTarget = require('./components/segments/SegmentTarget').default;
window.SegmentFooter = require('./components/segments/SegmentFooter').default;
window.SegmentTabMatches = require('./components/segments/SegmentFooterTabMatches').default;
window.SegmentTabMessages = require('./components/segments/SegmentFooterTabMessages').default;
window.SegmentButtons = require('./components/segments/SegmentButtons').default;
window.TranslationIssuesSideButton = require('./components/review/TranslationIssuesSideButton').default;

window.QaCheckGlossary = require('./components/segments/utils/qaCheckGlossaryUtils');

/******/

window.SearchUtils = require('./components/header/cattol/search/searchUtils');
window.TagUtils = require('./utils/tagUtils');
window.TextUtils = require('./utils/textUtils');
window.CommonUtils = require('./utils/commonUtils');
window.CursorUtils = require('./utils/cursorUtils');
window.OfflineUtils = require('./utils/offlineUtils');
window.Shortcuts = require('./utils/shortcuts');
window.Customizations = require('./utils/customizations');
window.SegmentUtils = require('./utils/segmentUtils');
window.DraftMatecatUtils = require('./components/segments/utils/DraftMatecatUtils');

window.LXQ = require('./utils/lxq.main');
window.MBC = require('./utils/mbc.main');
window.Speech2Text = require('./utils/speech2text');
