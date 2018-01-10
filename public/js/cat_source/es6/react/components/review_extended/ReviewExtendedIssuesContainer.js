let ReviewExtendedIssue =  require("./ReviewExtendedIssue").default;
let WrapperLoader =         require("../../common/WrapperLoader").default;
class ReviewExtendedIssuesContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {
            animateFirstIssue: false
        };

    }



    componentWillReceiveProps ( nextProps ) {
        if(nextProps.issues.length > this.props.issues.length && this.props.segment.sid === nextProps.segment.sid){
            this.setState({
                animateFirstIssue: true
            })
        }
    }

    componentDidUpdate () {
        if(this.state.animateFirstIssue){
            $('.issue-item:first-child .issue')
                .transition('jiggle');
            this.setState({
                animateFirstIssue: false
            })
        }

    }

    componentWillUnmount () {

    }


    render () {

        let cs = classnames({
            'review-issues-container' : true,
        });
        let issues,
            loaderHtml = '';

        if (this.props.issues.length > 0 ) {
            let sorted_issues = this.props.issues.sort(function(a,b) {
                return parseInt( a.id ) < parseInt( b.id );
            });

            issues = sorted_issues.map(function( item, index ) {
                let prog = sorted_issues.length - index;

                return <ReviewExtendedIssue
                    sid={this.props.sid}
					isReview={this.props.isReview}
                    progressiveNumber={prog}
                    issue={item}
                    key={item.id}
                />

            }.bind(this) );
        }
        if(this.props.issues.length > 0){
            return <div className="re-issues">
                {this.props.loader ? <WrapperLoader /> : null}
                <h4>Issues <span>{this.props.issues.length > 0? "("+this.props.issues.length+")" : ''}</span></h4>
                <div className="issues-list">
                    {issues}
                </div>
            </div>;
        }
        return "";

    }
}

export default ReviewExtendedIssuesContainer;
