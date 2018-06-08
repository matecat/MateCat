

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
window.MC = {} ;

window.MC.SegmentFixedButton = require('./components/SegmentFixedButton').default;
window.MC.SegmentRebuttedButton = require('./components/SegmentRebuttedButton').default;
window.MC.SegmentMainButtons = require('./components/SegmentMainButtons').default ;

window.classnames = require('classnames');

window.ReviewSidePanel = require('./components/review/ReviewSidePanel').default ;
window.ReviewTranslationIssueCommentsContainer = require('./components/review_improved/ReviewTranslationIssueCommentsContainer').default ;
window.ReviewIssueCategorySelector = require('./components/review_improved/ReviewIssueCategorySelector').default ;
window.QualityReportVersions = require('./components/review_improved/QualityReportVersions').default ;


window.Review_QualityReportButton = require('./components/review/QualityReportButton').default ;

window.SubHeaderContainer = require('./components/header/SubHeaderContainer').default ;
window.SegmentFilter = require('./components/header/segment_filter/SegmentsFilter').default;
window.NotificationBox = require('./components/notificationsComponent/NotificationBox').default;

window.ManageConstants = require('./constants/ManageConstants');
window.TeamConstants = require('./constants/TeamConstants');

window.ManageActions = require('./actions/ManageActions');
window.AnalyzeActions = require('./actions/AnalyzeActions');
window.TeamsActions = require('./actions/TeamsActions');
window.ModalsActions = require('./actions/ModalsActions');
window.OutsourceActions = require('./actions/OutsourceActions');
window.CatToolActions = require('./actions/CatToolActions');

window.ProjectsStore = require('./stores/ProjectsStore');
window.TeamsStore = require('./stores/TeamsStore');
window.OutsourceStore = require('./stores/OutsourceStore');

window.ProjectsContainer = require('./components/projects/ProjectsContainer').default;
window.ProjectContainer = require('./components/projects/ProjectContainer').default;
window.JobContainer = require('./components/projects/JobContainer').default;
window.JobMenu = require('./components/projects/JobMenu').default;

window.Header = require("./components/Header").default;

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

window.AnalyzeMain = require('./components/analyze/AnalyzeMain').default;
window.AnalyzeHeader = require('./components/analyze/AnalyzeHeader').default;
window.AnalyzeChunksResume = require('./components/analyze/AnalyzeChunksResume').default;
window.OpenJobBox = require('./components/outsource/OpenJobBox').default;
window.SegmentActions = require('./actions/SegmentActions');
window.SegmentStore = require('./stores/SegmentStore');
window.SegmentsContainer = require('./components/segments/SegmentsContainer').default;
window.SegmentTabMatches = require('./components/segments/SegmentFooterTabMatches').default;
window.SegmentWarnings = require('./components/segments/SegmentWarnings').default;

