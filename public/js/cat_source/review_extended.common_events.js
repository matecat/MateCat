/*
    Common events used in translation and revise page when Review Extended is active
 */

if (ReviewExtended.enabled()) {
    $(document).on('files:appended', function initReactComponents() {
        SegmentActions.mountTranslationIssues();
    });

    $(document).on('translation:change', function(e, data) {
        UI.getSegmentVersionsIssues(data.sid, UI.getSegmentFileId(data.segment));
    });

}