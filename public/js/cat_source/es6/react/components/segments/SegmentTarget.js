
/**
 * React Component .

 */
var React = require('react');
var EditArea = require('./Editarea').default;
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');


class SegmentTarget extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            translation : this.decodeTranslation(this.props.segment, this.props.segment.translation)
        };
        this.replaceTranslation = this.replaceTranslation.bind(this);
    }

    replaceTranslation(sid, translation) {
        if (this.props.segment.sid == sid) {
            this.setState({
                translation: this.decodeTranslation(this.props.segment, translation)
            });
        }
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.REPLACE_TRANSLATION, this.replaceTranslation);

    }

    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.REPLACE_TRANSLATION, this.replaceTranslation);
    }

    componentWillMount() {}

    decodeTranslation(segment, translation) {
        return this.props.decodeTextFn(segment, translation);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var textAreaContainer = "";
        if (this.props.isReviewImproved) {

            textAreaContainer = <div data-mount="segment_text_area_container">
                                    <div className="textarea-container">
                                        <div className="targetarea issuesHighlightArea errorTaggingArea" dangerouslySetInnerHTML={ this.allowHTML(this.state.translation) }/>
                                    </div>
                                </div>

        } else {

            var s2tMicro = "";
            var tagModeButton = "";

            var tagLockCustomizable = ( this.props.segment.segment.match( /\&lt;.*?\&gt;/gi ) ? $('#tpl-taglock-customize').html() : null );

            //Speeche2Text
            var s2t_enabled = this.props.speech2textEnabledFn();
            if (s2t_enabled) {
                s2tMicro = <div className="micSpeech" title="Activate voice input" data-segment-id="{{originalId}}">
                    <div className="micBg"></div>
                    <div className="micBg2">
                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="20" height="20"
                             viewBox="0 0 20 20">
                            <g class="svgMic" transform="matrix(0.05555509,0,0,0.05555509,-3.1790007,-3.1109739)"
                               fill="#737373">
                                <path
                                    d="m 290.991,240.991 c 0,26.392 -21.602,47.999 -48.002,47.999 l -11.529,0 c -26.4,0 -48.002,-21.607 -48.002,-47.999 l 0,-136.989 c 0,-26.4 21.602,-48.004 48.002,-48.004 l 11.529,0 c 26.4,0 48.002,21.604 48.002,48.004 l 0,136.989 z"/>
                                <path
                                    d="m 342.381,209.85 -8.961,0 c -4.932,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,50.26 -37.109,91.001 -87.361,91.001 -50.26,0 -87.109,-40.741 -87.109,-91.001 l 0,-8.008 c 0,-4.927 -4.029,-8.961 -8.961,-8.961 l -8.961,0 c -4.924,0 -8.961,4.034 -8.961,8.961 l 0,8.008 c 0,58.862 40.229,107.625 96.07,116.362 l 0,36.966 -34.412,0 c -4.932,0 -8.961,4.039 -8.961,8.971 l 0,17.922 c 0,4.923 4.029,8.961 8.961,8.961 l 104.688,0 c 4.926,0 8.961,-4.038 8.961,-8.961 l 0,-17.922 c 0,-4.932 -4.035,-8.971 -8.961,-8.971 l -34.43,0 0,-36.966 c 55.889,-8.729 96.32,-57.5 96.32,-116.362 l 0,-8.008 c 0,-4.927 -4.039,-8.961 -8.961,-8.961 z"/>
                            </g>
                        </svg>
                    </div>
                </div>;
            }

            //Tag Mode Buttons
            if (this.props.tagModesEnabled && !this.props.enableTagProjection) {
                tagModeButton =
                    <a href="#" className="tagModeToggle" alt="Display full/short tags" title="Display full/short tags">
                        <span className="icon-chevron-left"/>
                        <span className="icon-tag-expand"/>
                        <span className="icon-chevron-right"/>
                    </a>;
            }

            //Text Area
            textAreaContainer = <div className="textarea-container">

                                    <span className="loader"/>

                                    <div className="editarea-container" id={"editarea-container-"+ this.props.segment.sid}></div>

                                    <EditArea segment={this.props.segment} translation={this.state.translation}/>

                                    {s2tMicro}

                                    <div className="toolbar">
                                        {tagLockCustomizable}
                                        {tagModeButton}
                                        <a href="#" className="autofillTag" alt="Copy missing tags from source to target" title="Copy missing tags from source to target"/>
                                        <ul className="editToolbar">
                                            <li className="uppercase" title="Uppercase"/>
                                            <li className="lowercase" title="Lowercase"/>
                                            <li className="capitalize" title="Capitalized"/>
                                        </ul>
                                    </div>
                                </div>;
        }
        return (
            <div className="target item" id={"segment-" + this.props.segment.sid + "-target"}>

                {textAreaContainer}
                <p className="warnings"/>

                <ul className="buttons toggle" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}/>
            </div>
        )
    }
}

export default SegmentTarget;
