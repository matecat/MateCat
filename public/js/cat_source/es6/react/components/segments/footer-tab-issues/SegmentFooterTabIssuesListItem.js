var React = require( 'react' );
var SegmentConstants = require( '../../../constants/SegmentConstants' );
var SegmentStore = require( '../../../stores/SegmentStore' );

class SegmentFooterTabIssuesListItem extends React.Component {

    constructor( props ) {
        super( props );
        this.state = {
            categories: props.categories
        }

    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }


    componentWillMount() {
    }

    allowHTML( string ) {
        return {__html: string};
    }

    deleteIssue(event) {
        event.preventDefault();
        event.stopPropagation();
        SegmentActions.deleteIssue(this.props.issue)
    }

    findCategory( id ) {
        return this.state.categories.find( category => {
            return id == category.id
        } )
    }

    render() {

        return <div className="issue">
            <p>
                <b>{this.findCategory( this.props.issue.id_category ).label}:</b>
                {this.props.issue.severity}
                <i className="icon-cancel3" onClick={this.deleteIssue.bind(this)}>

                </i>
            </p>
        </div>
    }
}

export default SegmentFooterTabIssuesListItem;