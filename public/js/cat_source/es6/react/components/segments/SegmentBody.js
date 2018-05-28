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
        this.openStatusSegmentMenu = this.openStatusSegmentMenu.bind(this);
        this.handleClickOutside = this.handleClickOutside.bind(this);
        this.state = {
            showStatusMenu: false
        };
    }

    statusHandleTitleAttr(status) {
        status = status.toUpperCase();
        return status.charAt(0) + status.slice(1).toLowerCase()  + ', click to change it';
    }

    checkLockTags(area) {
        if (config.tagLockCustomizable) {
            return false;
        }

        if (!UI.tagLockEnabled) {
            return false;
        }

        if (UI.noTagsInSegment({area: area, starting: false })) {
            return false;
        }
        return true;
    }

    openStatusSegmentMenu(e) {
        e.preventDefault();
        this.setState({
            showStatusMenu: !this.state.showStatusMenu
        });
    }

    changeStatus(status) {
        UI.changeStatus(this.segmentBody, status, 1);
        this.setState({
            showStatusMenu: false
        });
    }

    /**
     * Alert if clicked on outside of element
     */
    handleClickOutside(event) {
        if (this.statusMenuRef && !this.statusMenuRef.contains(event.target)) {
            this.setState({
                showStatusMenu: false
            });
        }
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

            if (LXQ.enabled()) {
                LXQ.reloadPowertip(segment);
            }
            if ($('span.locked', area).length != prevNumTags){
                UI.closeTagAutocompletePanel();
            }

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
        document.addEventListener('mousedown', this.handleClickOutside);
    }

    componentWillUnmount() {
        document.removeEventListener('mousedown', this.handleClickOutside);
    }

    render() {
        var status_change_title;
        if ( this.props.segment.status ) {
            status_change_title = this.statusHandleTitleAttr( this.props.segment.status );
        } else {
            status_change_title = 'Change segment status' ;
        }
        let copySourceShortcuts = (UI.isMac) ? UI.shortcuts.cattol.events.copySource.keystrokes.mac : UI.shortcuts.cattol.events.copySource.keystrokes.standard;
        return (
            <div className="text segment-body-content" ref={(body)=>this.segmentBody=body}>
                <div className="wrap">
                    <span className="loader"/>
                    <div className="outersource">
                        <SegmentSource
                            segment={this.props.segment}
                            decodeTextFn={this.props.decodeTextFn}
                            afterRenderOrUpdate={this.afterRenderOrUpdate}
                            beforeRenderOrUpdate={this.beforeRenderOrUpdate}
                        />
                        <div className="copy" title="Copy source to target">
                            <a href="#"/>
                            <p>{copySourceShortcuts.toUpperCase()}</p>
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
                            locked={this.props.locked}
                            readonly={this.props.readonly}
                        />

                    </div>
                </div>
                <div className="status-container">
                    <a href="#" title={status_change_title}
                       className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}
                       onClick={this.openStatusSegmentMenu}
                    />
                </div>

                {this.state.showStatusMenu ? (
                    <ul className="statusmenu" ref={(menu)=>this.statusMenuRef=menu}>
                        <li className="arrow"><span className="arrow-mcolor"/></li>

                        <li>
                            <a className="draftStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set draft as status"
                               onClick={this.changeStatus.bind(this, 'draft')}>
                            DRAFT
                            </a>
                        </li>
                        <li>
                            <a className="translatedStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set translated as status"
                               onClick={this.changeStatus.bind(this, 'translated')}>
                                TRANSLATED
                            </a>
                        </li>

                        {!this.props.isReviewImproved && config.reviewType !== 'improved' ? (
                            <li><a className="approvedStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set approved as status"
                                   onClick={this.changeStatus.bind(this, 'approved')}>APPROVED</a></li>
                        ) : (null) }
                            {!this.props.isReviewImproved && config.reviewType !== 'improved' ? (
                            <li>
                                <a className="rejectedStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set rejected as status"
                                   onClick={this.changeStatus.bind(this, 'rejected')}>
                                    REJECTED
                                </a>
                            </li>
                        ) : (null) }

                        {this.props.isReviewImproved || config.reviewType == 'improved' ? (
                            <li>
                                <a className="fx" data-sid={"segment-"+ this.props.segment.sid} title="set fixed as status"
                                   onClick={this.changeStatus.bind(this, 'fixed')}>
                                    FIXED
                                </a>
                            </li>
                        ) : (null) }
                        {this.props.isReviewImproved || config.reviewType == 'improved' ? (
                            <li>
                                <a className="rb" data-sid={"segment-"+ this.props.segment.sid} title="set rebutted as status"
                                   onClick={this.changeStatus.bind(this, 'rebutted')}>
                                    REBUTTED
                                </a>
                            </li>
                        ) : (null) }

                    </ul>
                ) : (null)}

            </div>
        )
    }
}

export default SegmentBody;

