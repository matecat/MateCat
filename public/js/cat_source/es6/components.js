

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
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

window.ProjectsContainer = require('./components/projects/ProjectsContainer').default;
window.Header = require("./components/header/Header").default;
window.AnalyzeMain = require('./components/analyze/AnalyzeMain').default;

window.LanguageSelector = require('./components/languageSelector/LanguageSelector').default;

// ui.render
window.SegmentsContainer = require('./components/segments/SegmentsContainer').default;

/*
Todo move this
 */
window.ModalWindow = require('./modals/ModalWindowComponent').default;
window.SuccessModal = require('./modals/SuccessModal').default;
window.ConfirmRegister = require('./modals/ConfirmRegister').default;
window.PreferencesModal = require('./modals/PreferencesModal').default;
window.ResetPasswordModal = require('./modals/ResetPasswordModal').default;
window.LoginModal = require('./modals/LoginModal').default;
window.ForgotPasswordModal = require('./modals/ForgotPasswordModal').default;
window.RegisterModal = require('./modals/RegisterModal').default;
window.ConfirmMessageModal = require('./modals/ConfirmMessageModal').default;
window.OutsourceModal = require('./modals/OutsourceModal').default;
window.SplitJobModal = require('./modals/SplitJob').default;
window.DQFModal = require('./modals/DQFModal').default;
window.ShortCutsModal = require('./modals/ShortCutsModal').default;
window.CopySourceModal = require('./modals/CopySourceModal').default;
window.CreateTeamModal = require('./modals/CreateTeam').default;
window.ModifyTeamModal = require('./modals/ModifyTeam').default;
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
window.EditAreaUtils = require('./components/segments/utils/editarea');
window.CommonUtils = require('./utils/commonUtils');
window.CursorUtils = require('./utils/cursorUtils');
window.OfflineUtils = require('./utils/offlineUtils');
window.Shortcuts = require('./utils/shortcuts');
window.Customizations = require('./utils/customizations');

window.LXQ = require('./utils/lxq.main');
window.MBC = require('./utils/mbc.main');
window.Speech2Text = require('./utils/speech2text');
