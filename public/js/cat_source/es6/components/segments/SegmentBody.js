/**
 * React Component .

 */
import React  from 'react';
import SegmentSource  from './SegmentSource';
import TagUtils  from '../../utils/tagUtils';
import Shortcuts  from '../../utils/shortcuts';
import LXQ from '../../utils/lxq.main';
import SegmentTarget from "./SegmentTarget";


class SegmentBody extends React.Component {

    constructor(props) {
        super(props);
        this.openStatusSegmentMenu = this.openStatusSegmentMenu.bind(this);
        this.handleClickOutside = this.handleClickOutside.bind(this);
        this.state = {
            showStatusMenu: false,
            clickedTagId: null,
            clickedTagText: null,
            tagClickedInSource: false
        };
        this.isReviewExtended = this.props.reviewType === 'extended'
    }

    statusHandleTitleAttr(status) {
        status = status.toUpperCase();
        return status.charAt(0) + status.slice(1).toLowerCase()  + ', click to change it';
    }

    checkLockTags(area) {
        if (config.tagLockCustomizable || !UI.tagLockEnabled) {
            return false;
        } else return ( this.props.segment.segment.match(/&lt;.*?&gt;/gi) )
    }

    openStatusSegmentMenu(e) {
        e.preventDefault();
        this.setState({
            showStatusMenu: !this.state.showStatusMenu
        });
    }

    changeStatus(status) {
        UI.changeStatus(this.props.segment, status);
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

    hasSourceOrTargetTags() {
        var regExp = TagUtils.getXliffRegExpression();
        var sourceTags = this.props.segment.segment.match( regExp );
        return sourceTags && sourceTags.length > 0 ;
    }

    hasMissingTargetTags() {
        var regExp = TagUtils.getXliffRegExpression();
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
    copySource(e) {
        e.preventDefault();
        SegmentActions.copySourceToTarget( this.props.segment.sid);
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
        let copySourceShortcuts = (UI.isMac) ? Shortcuts.cattol.events.copySource.keystrokes.mac : Shortcuts.cattol.events.copySource.keystrokes.standard;
        return (
            <div className="text segment-body-content" ref={(body)=>this.segmentBody=body}>
                <div className="wrap">
                    <div className="outersource">
                        <SegmentSource
                            segment={this.props.segment}
                            segImmutable={this.props.segImmutable}
                            setClickedTagId={this.setClickedTagId}
                            clickedTagId={this.state.clickedTagId}
                            clickedTagText={this.state.clickedTagText}
                            tagClickedInSource={this.state.tagClickedInSource}
                        />
                        <div className="copy" title="Copy source to target" onClick={(e)=>this.copySource(e)}>
                            <a href="#"/>
                            <p>{copySourceShortcuts.toUpperCase()}</p>
                        </div>
                        <SegmentTarget
                            segment={this.props.segment}
                            segImmutable={this.props.segImmutable}
                            enableTagProjection={this.props.enableTagProjection}
                            isReview={this.props.isReview}
                            isReviewExtended={this.props.isReviewExtended}
                            isReviewImproved={this.props.isReviewImproved}
                            reviewType={this.props.reviewType}
                            tagModesEnabled={this.props.tagModesEnabled}
                            speech2textEnabledFn={this.props.speech2textEnabledFn}
                            locked={this.props.locked}
                            readonly={this.props.readonly}
                            openSegment={this.props.openSegment}
                            removeSelection={this.props.removeSelection}
                            setClickedTagId={this.setClickedTagId}
                            clickedTagId={this.state.clickedTagId}
                            clickedTagText={this.state.clickedTagText}
                            tagClickedInSource={this.state.tagClickedInSource}
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

    setClickedTagId = (id= null, clickedTagText= null, clickedInSource = false ) =>{
        this.setState({
            clickedTagId: id,
            clickedTagText: clickedTagText,
            tagClickedInSource: clickedInSource
        })
    }
}

export default SegmentBody;

