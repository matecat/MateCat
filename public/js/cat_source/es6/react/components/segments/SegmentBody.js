/**
 * React Component .

 */
var React = require('react');
var SegmentSource = require('./SegmentSource').default;

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
        this.isReviewExtended = this.props.reviewType === 'extended'
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

        // if ( !this.props.segment.decoded_translation.indexOf('class="locked') > 0 ) {
        //     return false;
        // }
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
        if ( area && area.length > 0 && this.checkLockTags(area) ) {
            var segment = area.closest('section');
            if (LXQ.enabled()) {
                $.powerTip.destroy($('.tooltipa', segment));
                $.powerTip.destroy($('.tooltipas', segment));
            }
        }
    }

    afterRenderOrUpdate(area) {
        if ( area && area.length > 0 && this.checkLockTags(area)) {
            var segment = area.closest('section');

            if (LXQ.enabled()) {
                LXQ.reloadPowertip(segment);
            }

            if (UI.hasSourceOrTargetTags(segment)) {
                segment.addClass('hasTagsToggle');
                UI.detectTagType(area);

            } else {
                segment.removeClass('hasTagsToggle');
            }

            if (UI.hasMissingTargetTags(segment)) {
                segment.addClass('hasTagsAutofill');
            } else {
                segment.removeClass('hasTagsAutofill');
            }
        }
    }

    hasSourceOrTargetTags() {
        var regExp = UI.getXliffRegExpression();
        var sourceTags = this.props.segment.segment.match( regExp );
        return sourceTags && sourceTags.length > 0 ;
    }

    hasMissingTargetTags() {
        var regExp = UI.getXliffRegExpression();
        var sourceTags = this.props.segment.segment.match( regExp );
        if ( !sourceTags || sourceTags.length === 0 ) {
            return false;
        }
        var targetTags = this.props.segment.translation.match( regExp );

        return targetTags && sourceTags.length > targetTags.length || targetTags && !_.isEqual(sourceTags.sort(), targetTags.sort());
    }

    getStatusMenu() {
        if ( this.state.showStatusMenu && !this.isReviewExtended ) {
            return <ul className="statusmenu" ref={(menu)=>this.statusMenuRef=menu}>
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
                    <li><a className="approvedStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set approved as status"
                           onClick={this.changeStatus.bind(this, 'approved')}>APPROVED</a></li>

                    <li>
                        <a className="rejectedStatusMenu" data-sid={"segment-"+ this.props.segment.sid} title="set rejected as status"
                           onClick={this.changeStatus.bind(this, 'rejected')}>
                            REJECTED
                        </a>
                    </li>
                </ul>
            } else {
                return '';

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
                            enableTagProjection={this.props.enableTagProjection}
                            decodeTextFn={this.props.decodeTextFn}
                            tagModesEnabled={this.props.tagModesEnabled}
                            speech2textEnabledFn={this.props.speech2textEnabledFn}
                            afterRenderOrUpdate={this.afterRenderOrUpdate}
                            beforeRenderOrUpdate={this.beforeRenderOrUpdate}
                            locked={this.props.locked}
                            readonly={this.props.readonly}
                            removeSelection={this.props.removeSelection}
                        />

                    </div>
                </div>
                <div className="status-container">
                    { this.isReviewExtended ?  (
                        <a href="#" className="status no-hover" onClick={this.openStatusSegmentMenu}
                        />
                        ) : (
                        <a href="#" title={status_change_title}
                           className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}
                           onClick={this.openStatusSegmentMenu}
                        />
                    )}

                </div>

                {this.getStatusMenu()}



            </div>
        )
    }
}

export default SegmentBody;

