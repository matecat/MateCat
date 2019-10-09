

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
window.MC = {} ;

window.classnames = require('classnames');

window.Review_QualityReportButton = require('./es6/components/review/QualityReportButton').default ;

window.SubHeaderContainer = require('./es6/components/header/cattol/SubHeaderContainer').default ;
window.SegmentFilter = require('./es6/components/header/cattol/segment_filter/segment_filter');
window.NotificationBox = require('./es6/components/notificationsComponent/NotificationBox').default;

window.ManageConstants = require('./es6/constants/ManageConstants');
window.TeamConstants = require('./es6/constants/TeamConstants');

window.ManageActions = require('./es6/actions/ManageActions');
window.AnalyzeActions = require('./es6/actions/AnalyzeActions');
window.TeamsActions = require('./es6/actions/TeamsActions');
window.ModalsActions = require('./es6/actions/ModalsActions');
window.OutsourceActions = require('./es6/actions/OutsourceActions');
window.CatToolActions = require('./es6/actions/CatToolActions');

window.ProjectsStore = require('./es6/stores/ProjectsStore');
window.TeamsStore = require('./es6/stores/TeamsStore');
window.OutsourceStore = require('./es6/stores/OutsourceStore');

window.ProjectsContainer = require('./es6/components/projects/ProjectsContainer').default;
window.ProjectContainer = require('./es6/components/projects/ProjectContainer').default;
window.JobContainer = require('./es6/components/projects/JobContainer').default;
window.JobMenu = require('./es6/components/projects/JobMenu').default;

window.Header = require("./es6/components/header/Header").default;

window.ModalWindow = require('./es6/modals/ModalWindowComponent').default;
window.SuccessModal = require('./es6/modals/SuccessModal').default;
window.ConfirmRegister = require('./es6/modals/ConfirmRegister').default;
window.PreferencesModal = require('./es6/modals/PreferencesModal').default;
window.ResetPasswordModal = require('./es6/modals/ResetPasswordModal').default;
window.LoginModal = require('./es6/modals/LoginModal').default;
window.ForgotPasswordModal = require('./es6/modals/ForgotPasswordModal').default;
window.RegisterModal = require('./es6/modals/RegisterModal').default;
window.ConfirmMessageModal = require('./es6/modals/ConfirmMessageModal').default;
window.OutsourceModal = require('./es6/modals/OutsourceModal').default;
window.SplitJobModal = require('./es6/modals/SplitJob').default;
window.DQFModal = require('./es6/modals/DQFModal').default;
window.ShortCutsModal = require('./es6/modals/ShortCutsModal').default;
window.CopySourceModal = require('./es6/modals/CopySourceModal').default;

window.CreateTeamModal = require('./es6/modals/CreateTeam').default;
window.ModifyTeamModal = require('./es6/modals/ModifyTeam').default;

window.AnalyzeMain = require('./es6/components/analyze/AnalyzeMain').default;
window.AnalyzeHeader = require('./es6/components/analyze/AnalyzeHeader').default;
window.AnalyzeChunksResume = require('./es6/components/analyze/AnalyzeChunksResume').default;
window.OpenJobBox = require('./es6/components/outsource/OpenJobBox').default;
window.SegmentActions = require('./es6/actions/SegmentActions');
window.SegmentStore = require('./es6/stores/SegmentStore');
window.SegmentsContainer = require('./es6/components/segments/SegmentsContainer').default;
window.Segment = require('./es6/components/segments/Segment').default;
window.SegmentBody = require('./es6/components/segments/SegmentBody').default;
window.SegmentTarget = require('./es6/components/segments/SegmentTarget').default;
window.SegmentFooter = require('./es6/components/segments/SegmentFooter').default;
window.SegmentTabMatches = require('./es6/components/segments/SegmentFooterTabMatches').default;
window.SegmentTabMessages = require('./es6/components/segments/SegmentFooterTabMessages').default;
window.SegmentWarnings = require('./es6/components/segments/SegmentWarnings').default;
window.SegmentButtons = require('./es6/components/segments/SegmentButtons').default;

window.CommentsActions = require('./es6/actions/CommentsActions');
window.CommentsStore = require('./es6/stores/CommentsStore');


window.TranslationIssuesSideButton = require('./es6/components/review/TranslationIssuesSideButton').default;

window.SearchUtils = require('./es6/components/header/cattol/search/searchUtils');
window.QaCheckGlossary = require('./es6/components/segments/utils/qaCheckGlossaryUtils');
window.QaCheckBlacklist = require('./es6/components/segments/utils/qaCheckBlacklistUtils');
window.TagUtils = require('./es6/utils/tagUtils');
window.TextUtils = require('./es6/utils/textUtils');
window.EditAreaUtils = require('./es6/components/segments/utils/editarea');
window.CommonUtils = require('./es6/utils/commonUtils');
window.CursorUtils = require('./es6/utils/cursorUtils');
window.OfflineUtils = require('./es6/utils/offlineUtils');
window.Shortcuts = require('./es6/utils/shortcuts');
window.Customizations = require('./es6/utils/customizations');

