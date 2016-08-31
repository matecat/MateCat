/**
 * React Component for the editarea.
 
 */
var React = require('react');
var $ = require('jquery');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class Editarea extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            editareaClasses : ['targetarea', 'invisible']
        };
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
    }
    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
    }
    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_EDITAREA_CLASS, this.addClass);
    }
    componentWillMount() {
        var editareaClasses = this.state.editareaClasses;
        if ((this.props.segment.readonly == 'true')||($("body").hasClass('archived'))) {
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

    addClass(sid, className) {
        if (this.props.segment.sid == sid) {
            var editareaClasses = this.state.editareaClasses;
            editareaClasses.push(className);
            this.setState({
                editareaClasses: editareaClasses
            });

        }
    }
    render() {
        if (this.props.segment){
            var lang = config.target_lang.toLowerCase();
            var readonly = ((this.props.segment.readonly == 'true')) ? true : false;
            return (
                <div className={this.state.editareaClasses.join(' ')}
                     id={'segment-' + this.props.segment.sid + '-editarea'}
                     lang={lang}
                     contentEditable={!readonly}
                     spellCheck="true"
                     data-sid={this.props.segment.sid}
                     dangerouslySetInnerHTML={ this.allowHTML(this.props.translation) }
                />

            );
        }
    }
}

export default Editarea ;

