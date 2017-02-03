

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
window.MC = {} ;

window.MC.SegmentFixedButton = require('./components/SegmentFixedButton').default;
window.MC.SegmentRebuttedButton = require('./components/SegmentRebuttedButton').default;
window.MC.SegmentMainButtons = require('./components/SegmentMainButtons').default ;

window.classnames = require('classnames');

window.TranslationIssuesSideButton = require('./components/TranslationIssuesSideButton').default ;

window.ReviewSidePanel = require('./components/ReviewSidePanel').default ;
window.TranslationIssuesOverviewPanel = require('./components/TranslationIssuesOverviewPanel').default ;
window.ReviewTranslationVersion = require('./components/ReviewTranslationVersion').default ;
window.ReviewIssuesContainer = require('./components/ReviewIssuesContainer').default ;
window.ReviewTranslationIssue = require('./components/ReviewTranslationIssue').default ;
window.ReviewTranslationIssueCommentsContainer = require('./components/ReviewTranslationIssueCommentsContainer').default ;
window.ReviewIssueSelectionPanel = require('./components/ReviewIssueSelectionPanel').default ;
window.ReviewIssueCategorySelector = require('./components/ReviewIssueCategorySelector').default ;


window.Review_QualityReportButton = require('./components/review/QualityReportButton').default ;

window.SegmentFilter_MainPanel = require('./components/segment_filter/MainPanel').default ;

window.NotificationBox = require('./components/notificationsComponent/NotificationBox').default;

window.ManageConstants = require('./constants/ManageConstants');
window.ManageActions = require('./actions/ManageActions');
window.ProjectsStore = require('./stores/ProjectsStore');
window.OrganizationsStore = require('./stores/OrganizationsStore');
window.ProjectsContainer = require('./components/projects/ProjectsContainer').default;

window.Header = require("./components/Header").default;

window.QAComponent = require('./components/QAComponent').default;

window.ModalWindow = require('./modals/ModalWindowComponent').default;
window.SuccessModal = require('./modals/SuccessModal').default;
window.ConfirmRegister = require('./modals/ConfirmRegister').default;
window.PreferencesModal = require('./modals/PreferencesModal').default;
window.ResetPasswordModal = require('./modals/ResetPasswordModal').default;
window.LoginModal = require('./modals/LoginModal').default;
window.ForgotPasswordModal = require('./modals/ForgotPasswordModal').default;
window.RegisterModal = require('./modals/RegisterModal').default;

window.CreateOrganizationModal = require('./modals/CreateOrganization').default;
window.ModifyOrganizationModal = require('./modals/ModifyOrganization').default;
window.CreateWorkspaceModal = require('./modals/CreateWorkspace').default;
window.ChangeProjectWorkspaceModal = require('./modals/ChangeProjectWorkspace').default;
window.AssignToTranslator = require('./modals/AssignToTranslator').default;


