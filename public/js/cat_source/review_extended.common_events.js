/*
    Common events used in translation and revise page when Review Extended is active
 */

if (ReviewExtended.enabled()) {
    $(document).on('files:appended', function initReactComponents() {
        SegmentActions.mountTranslationIssues();
    });

    $(document).on('translation:change', function(e, data) {
        if (data.sid === UI.getSegmentId(UI.currentSegment)) {
            UI.getSegmentVersionsIssues(data.sid, UI.getSegmentFileId(data.segment));
        }
    });

}