/**
 * React Component for the editarea.

 */
var SegmentStore = require('../stores/SegmentStore');
var EditArea = require('../components/Editarea').default;
class Segment extends React.Component {

    constructor(props) {
        super(props);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
        this.decodeTextSource = this.decodeTextSource.bind(this);
        this.createSegmentClasses = this.createSegmentClasses.bind(this);
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = UI.stripSpans(text);
        }

        this.escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        this.escapedSegment = this.escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        this.escapedSegment = this.escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        this.escapedSegment = this.escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
    }

    createSegmentClasses() {
        var classes = [];
        if ( this.readonly ) {
            classes.push('readonly');
        }

        if ( this.props.segment.status ) {
            classes.push( 'status-' + this.props.segment.status.toLowerCase() );
        }
        else {
            classes.push('status-new');
        }

        if ( this.props.segment.has_reference == 'true') {
            classes.push('has-reference');
        }

        if ( this.props.segment.sid == this.splitGroup[0] ) {
            classes.push( 'splitStart' );
        }
        else if ( this.props.segment.sid == this.splitGroup[this.splitGroup.length - 1] ) {
            classes.push( 'splitEnd' );
        }
        else if ( this.splitGroup.length ) {
            classes.push('splitInner');
        }
        this.segment_classes = classes;
    }

    decodeTextSource() {
        var decoded_source;
        /**if Tag Projection enabled and there are not tags in the segment translation, remove it and add the class that identify
         * tha Tag Projection is enabled
         */
        if (UI.enableTagProjection && (UI.getSegmentStatus(this.props.segment) === 'draft' || UI.getSegmentStatus(this.props.segment) === 'new')
            && !UI.checkXliffTagsInText(this.props.segment.translation) ) {
            // decoded_translation = UI.removeAllTags(segment.translation);
            decoded_source = UI.removeAllTags(this.props.segment.segment);
            this.segment_classes.push('enableTP');
            this.dataAttrTagged = "nottagged";
        } else {
            // decoded_translation = segment.translation;
            decoded_source = this.props.segment.segment;
            this.dataAttrTagged = "tagged";
        }

        decoded_source = UI.decodePlaceholdersToText(
            decoded_source || '',
            true, this.props.segment.sid, 'source');

        this.decoded_text = decoded_source;
    }

    componentDidMount() {}

    componentWillUnmount() {}

    componentWillMount() {
        this.readonly = ((this.props.segment.readonly == 'true')||(UI.body.hasClass('archived'))) ? true : false;
        this.autoPropagated = this.props.segment.autopropagated_from != 0;
        this.autoPropagable = (this.props.segment.repetitions_in_chunk != "1");
        this.originalId = this.props.segment.sid.split('-')[0];

        this.createEscapedSegment();

        this.splitGroup = this.props.segment.split_group || this.props.splitGroup || '';
        if ( this.props.segment.status ) {
            this.status_change_title = UI.statusHandleTitleAttr( this.props.segment.status );
        } else {
            this.status_change_title = 'Change segment status' ;
        }
        this.createSegmentClasses();

        if ( this.props.timeToEdit ) {
            this.segment_edit_min = this.props.segment.parsed_time_to_edit[1];
            this.segment_edit_sec = this.props.segment.parsed_time_to_edit[2];
        }
        this.decodeTextSource();

        this.shortened_sid = UI.shortenId( this.props.segment.sid );
        this.start_job_marker = this.props.segment.sid == config.first_job_segment;
        this.end_job_marker = this.props.segment.sid == config.last_job_segment;

        //Text area Container
        this.readonly;
        this.lang = config.target_lang.toLowerCase();
        this.tagLockCustomizable = ( this.props.segment.segment.match( /\&lt;.*?\&gt;/gi ) ? $('#tpl-taglock-customize').html() : null );
        this.tagModesEnabled = UI.tagModesEnabled;
        this.s2t_enabled = Speech2Text.enabled();
        this.notEnableTagProjection = !this.enableTagProjection
    }
    allowHTML(string) {
        return { __html: string };
    }
    render() {
        var job_marker = "";
        var timeToEdit = "";
        if (this.start_job_marker) {
            job_marker = <span className={"start-job-marker"}/>;
        } else if (this.end_job_marker) {
            job_marker = <span className={"end-job-marker"}/>;
        }

        if (this.props.timeToEdit) {
            timeToEdit = <span className="edit-min">{this.segment_edit_min}</span> + m + <span className="edit-sec">{this.segment_edit_sec}</span> + s;
        }

        var s2tMicro = "";
        if (this.s2t_enabled) {
            s2tMicro = <div className="micSpeech" title="Activate voice input" data-segment-id="{{originalId}}">
                <div className="micBg"></div>
                <div className="micBg2">
                    <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="20" height="20" viewBox="0 0 20 20">
                        <g class="svgMic" transform="matrix(0.05555509,0,0,0.05555509,-3.1790007,-3.1109739)" fill="#737373">
                            <path d="m 290.991,240.991 c 0,26.392 -21.602,47.999 -48.002,47.999 l -11.529,0 c -26.4,0 -48.002,-21.607 -48.002,-47.999 l 0,-136.989 c 0,-26.4 21.602,-48.004 48.002,-48.004 l 11.529,0 c 26.4,0 48.002,21.604 48.002,48.004 l 0,136.989 z" />
                            <path d="m 342.381,209.85 -8.961,0 c -4.932,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,50.26 -37.109,91.001 -87.361,91.001 -50.26,0 -87.109,-40.741 -87.109,-91.001 l 0,-8.008 c 0,-4.927 -4.029,-8.961 -8.961,-8.961 l -8.961,0 c -4.924,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,58.862 40.229,107.625 96.07,116.362 l 0,36.966 -34.412,0 c -4.932,0 -8.961,4.039 -8.961,8.971 l 0,17.922 c 0,4.923 4.029,8.961 8.961,8.961 l 104.688,0 c 4.926,0 8.961,-4.038 8.961,-8.961 l 0,-17.922 c 0,-4.932 -4.035,-8.971 -8.961,-8.971 l -34.43,0 0,-36.966 c 55.889,-8.729 96.32,-57.5 96.32,-116.362 l 0,-8.008 c 0,-4.927 -4.039,-8.961 -8.961,-8.961 z" />
                        </g>
                    </svg>
                </div>
            </div>;
        }
        var tagModeButton = "";
        if (this.tagModesEnabledes && this.notEnableTagProjection) {
            tagModeButton = <a href="#" className="tagModeToggle" alt="Display full/short tags" title="Display full/short tags">
                <span className="icon-chevron-left"/>
                <span className="icon-tag-expand"/>
                <span className="icon-chevron-right"/>
            </a>;
        }

        return (
            <section id={"segment-" + this.props.segment.sid}
                     className={this.segment_classes.join(' ')}
                     data-hash={this.props.segment.segment_hash}
                     data-autopropagated={this.autoPropagated}
                     data-propagable={this.autoPropagable}
                     data-version={this.props.segment.version}
                     data-split-group={this.splitGroup}
                     data-split-original-id={this.originalId}
                     data-tagmode="crunched"
                     data-tagprojection={this.dataAttrTagged}>

                <a tabindex="0" href={"#" + this.props.segment.sid}/>
                <div className={"sid"} title={this.props.segment.sid}>
                    <div className="txt" dangerouslySetInnerHTML={ this.allowHTML(this.shortened_sid) }></div>
                    <div className={"actions"}>
                        <a className={"split"} href={"#"} title={"Click to split segment"}>
                            <span className={"icon-split"}/>
                        </a>
                        <p className={"split-shortcut"}>CTRL + S</p>
                    </div>
                </div>
                {job_marker}

                {/*Segment body start*/}
                <div className={"body"}>
                    <div className={"header toggle"} id={"segment-" + this.props.segment.sid + "-header"}></div>
                    <div className={"text"}>
                        <div className={"wrap"}>
                            <div className={"outersource"}>
                                <div className={"source item"}
                                     tabindex={0}
                                     id={"segment-" + this.props.segment.sid +"-source"}
                                     data-original={this.escapedSegment}>{this.decoded_text}</div>
                                <div className={"copy"} title="Copy source to target">
                                    <a href="#"/>
                                    <p>ALT+CTRL+I</p>
                                </div>
                                <div className="target item" id={"segment-" + this.props.segment.sid + "-target"}>
                                    <span className="hide toggle"/>

                                    {/*{{> translate/_text_area_container }}*/}
                                    <div className="textarea-container">

                                        <span className="loader"/>

                                        <div className="editarea-container" id={"editarea-container-"+this.props.segment.sid}></div>

                                            <EditArea segment={this.props.segment}/>

                                        {s2tMicro}

                                        <div className="toolbar">
                                            {this.tagLockCustomizable}
                                            {tagModeButton}
                                            <a href="#" className="autofillTag" alt="Copy missing tags from source to target" title="Copy missing tags from source to target"/>
                                            <ul className="editToolbar">
                                                <li className="uppercase" title="Uppercase"/>
                                                <li className="lowercase" title="Lowercase"/>
                                                <li className="capitalize" title="Capitalized"/>
                                            </ul>
                                        </div>

                                    </div>
                                     {/*Text are container end     */}

                                    <p className="warnings"/>

                                    <ul className="buttons toggle" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}/>
                                </div>

                            </div>
                        </div>
                        <div className="status-container">
                            <a href="#" title={this.status_change_title}
                               className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}/>
                        </div>
                    </div>
                    <div className="timetoedit"
                         data-raw-time-to-edit={this.props.segment.time_to_edit}>

                        {timeToEdit}

                    </div>
                    <div className="footer toggle"></div>
                </div>
                {/*Segment body End */}


                <ul className={"statusmenu"}/>

                {/*//!-- TODO: place this element here only if it's not a split --*/}
                <div className={"segment-side-buttons"}>
                    <div data-mount={"translation-issues-button"} className={"translation-issues-button"} data-sid={this.props.segment.sid}></div>
                </div>
            </section>
        );
    }
}

export default Segment ;

