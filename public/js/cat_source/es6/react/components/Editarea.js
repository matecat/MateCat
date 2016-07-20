/**
 * React Component for the editarea.
 
 */
var SegmentConstants = require('../constants/SegmentConstants');
var SegmentStore = require('../stores/SegmentStore');
class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            editareaClasses : ['targetarea', 'invisible']

        };
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.replaceContent = this.replaceContent.bind(this);
    }
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.REPLACE_CONTENT, this.replaceContent);
    }
    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA);
        SegmentStore.removeListener(SegmentConstants.REPLACE_CONTENT);
    }
    componentWillMount() {
        var decoded_translation;
        var segment = this.props.segment;
        if (UI.enableTagProjection && (UI.getSegmentStatus(segment) === 'draft' || UI.getSegmentStatus(segment) === 'new')
            && !UI.checkXliffTagsInText(segment.translation) ) {
            decoded_translation = UI.removeAllTags(segment.translation);
        } else {
            decoded_translation = segment.translation;
        }
        decoded_translation = UI.decodePlaceholdersToText(decoded_translation || '');
        this.translation = decoded_translation;

        var editareaClasses = this.state.editareaClasses;
        if ((this.props.segment.readonly == 'true')||(UI.body.hasClass('archived'))) {
            editareaClasses.push('area')
        } else {
            editareaClasses.push('editarea')
        }

        Speech2Text.enabled() && editareaClasses.push( 'micActive' ) ;

        this.setState({
            editareaClasses: editareaClasses
        });

    }
    allowHTML(string) {
        return { __html: string };
    }
    hightlightEditarea(sid) {
        if (this.props.segment.sid == sid) {
            var self = this;
            var editareaClasses = this.state.editareaClasses;
            editareaClasses.push('highlighted1');
            this.setState({
                editareaClasses: editareaClasses
            });
            setTimeout(function() {
                editareaClasses.push.apply(editareaClasses, ['highlighted2']);
                self.setState({
                    editareaClasses: editareaClasses
                });
            }, 300);
            setTimeout(function() {
                var index = editareaClasses.indexOf('highlighted1');
                editareaClasses.splice(index, 1);
                index = editareaClasses.indexOf('highlighted2');
                editareaClasses.splice(index, 1);
                self.setState({
                    editareaClasses: editareaClasses
                });
            }, 2000);
        }
    }
    replaceContent(sid, text) {
        if (this.props.segment.sid == sid) {
            console.log("Replace Content");
        }
    }
    render() {
        if (this.props.segment){
            return (
                <div className={this.state.editareaClasses.join(' ')}
                     id={'segment-' + this.props.segment.sid + '-editarea'}
                     data-sid={this.props.segment.sid}
                     dangerouslySetInnerHTML={ this.allowHTML(this.translation) }
                ></div>

            );
        }
    }
}

export default Editarea ;

