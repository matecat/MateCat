

// This namespace was initially intended to contain all React components,
// but I found this is not a good practice since the dot may create troubles.
// Underscores seem to be a better convention.
window.MC = {} ;

window.MC.SegmentFixedButton = require('./SegmentFixedButton').default;
window.MC.SegmentRebuttedButton = require('./SegmentRebuttedButton').default;
window.MC.SegmentMainButtons = require('./SegmentMainButtons').default ;

window.classnames = require('classnames');

window.TranslationIssuesSideButton = require('./TranslationIssuesSideButton').default ;

window.ReviewSidePanel = require('./ReviewSidePanel').default ;
window.TranslationIssuesOverviewPanel = require('./TranslationIssuesOverviewPanel').default ;
window.ReviewTranslationVersion = require('./ReviewTranslationVersion').default ; 
window.ReviewIssuesContainer = require('./ReviewIssuesContainer').default ;
window.ReviewTranslationIssue = require('./ReviewTranslationIssue').default ;
window.ReviewTranslationIssueCommentsContainer = require('./ReviewTranslationIssueCommentsContainer').default ;
window.ReviewIssueSelectionPanel = require('./ReviewIssueSelectionPanel').default ; 
window.ReviewIssueCategorySelector = require('./ReviewIssueCategorySelector').default ; 


window.Review_QualityReportButton = require('./review/QualityReportButton').default ;

window.SegmentFilter_MainPanel = require('./components/segment_filter/MainPanel').default ;

window.NotificationBox = require('./components/notificationsComponent/NotificationBox').default;

window.QAComponent = require('./QAComponent').default;

window.ModalWindow = require('./modals/ModalWindowComponent').default;
window.SuccessModal = require('./modals/SuccessModal').default;
window.ConfirmRegister = require('./modals/ConfirmRegister').default;
window.PreferencesModal = require('./modals/PreferencesModal').default;
window.ResetPasswordModal = require('./modals/ResetPasswordModal').default;
window.LoginModal = require('./modals/LoginModal').default;
window.ForgotPasswordModal = require('./modals/ForgotPasswordModal').default;
window.RegisterModal = require('./modals/RegisterModal').default;

// window.AppDispatcher = require('./dispatcher/AppDispatcher');
// window.SegmentStore = require('./stores/SegmentStore');
window.SegmentActions = require('./actions/SegmentActions');
window.SegmentStore = require('./stores/SegmentStore');
window.SegmentsContainer = require('./components/segments/SegmentsContainer').default;
