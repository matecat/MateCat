/**
 * React Component .

 */
var React = require('react');
var SegmentSource = require('./SegmentSource').default;
var SegmentTarget = require('./SegmentTarget').default;

class SegmentBody extends React.Component {

    constructor(props) {
        super(props);
        this.beforeRenderOrUpdate = this.beforeRenderOrUpdate.bind(this);
        this.afterRenderOrUpdate = this.afterRenderOrUpdate.bind(this);

    }

    statusHandleTitleAttr(status) {
        status = status.toUpperCase();
        return status.charAt(0) + status.slice(1).toLowerCase()  + ', click to change it';
    }

    checkLockTags(area) {
        if (config.tagLockCustomizable) {
            return false;
        }

        if (!UI.taglockEnabled) {
            return false;
        }

        if (UI.noTagsInSegment({area: area, starting: false })) {
            return false;
        }
        return true;
    }

    beforeRenderOrUpdate(area) {
        if (this.checkLockTags(area)) {
            var segment = area.closest('section');
            if (LXQ.enabled()) {
                $.powerTip.destroy($('.tooltipa', segment));
                $.powerTip.destroy($('.tooltipas', segment));
            }
        }
    }

    afterRenderOrUpdate(area) {
        if (this.checkLockTags(area)) {
            var segment = area.closest('section');

            var prevNumTags = $('span.locked', area).length;

            if (LXQ.enabled())
                LXQ.reloadPowertip(segment);
            if ($('span.locked', area).length != prevNumTags){
                UI.closeTagAutocompletePanel();
            }

            UI.evalCurrentSegmentTranslationAndSourceTags(segment);

            if (UI.hasSourceOrTargetTags(segment)) {
                segment.addClass('hasTagsToggle');

            } else {
                segment.removeClass('hasTagsToggle');
            }

            if (UI.hasMissingTargetTags(segment)) {
                segment.addClass('hasTagsAutofill');
            } else {
                segment.removeClass('hasTagsAutofill');
            }

            $('span.locked', area).addClass('monad');

            UI.detectTagType(area);
        }
    }

    componentDidMount() {
        console.log("Mount SegmentBody" + this.props.segment.sid);
    }

    componentWillUnmount() {
        console.log("Unmount SegmentBody" + this.props.segment.sid);
    }

    componentWillMount() {}

    render() {
        var status_change_title;
        if ( this.props.segment.status ) {
            status_change_title = this.statusHandleTitleAttr( this.props.segment.status );
        } else {
            status_change_title = 'Change segment status' ;
        }
        return (
            <div className="text">
                <div className="wrap">
                    <div className="outersource">
                        <SegmentSource
                            segment={this.props.segment}
                            decodeTextFn={this.props.decodeTextFn}
                            afterRenderOrUpdate={this.afterRenderOrUpdate}
                            beforeRenderOrUpdate={this.beforeRenderOrUpdate}
                        />
                        <div className="copy" title="Copy source to target">
                            <a href="#"/>
                            <p>ALT+CTRL+I</p>
                        </div>
                        <SegmentTarget
                            segment={this.props.segment}
                            isReviewImproved={this.props.isReviewImproved}
                            enableTagProjection={this.props.enableTagProjection}
                            decodeTextFn={this.props.decodeTextFn}
                            tagModesEnabled={this.props.tagModesEnabled}
                            speech2textEnabledFn={this.props.speech2textEnabledFn}
                            afterRenderOrUpdate={this.afterRenderOrUpdate}
                            beforeRenderOrUpdate={this.beforeRenderOrUpdate}
                        />

                    </div>
                </div>
                <div className="status-container">
                    <a href="#" title={status_change_title}
                       className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}/>
                </div>
            </div>
        )
    }
}

export default SegmentBody;

