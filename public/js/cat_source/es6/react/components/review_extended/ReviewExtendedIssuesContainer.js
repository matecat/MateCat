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
                .transition('pulse');
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
            let sorted_issues = this.props.issues.sort(function(a, b) {
                a = new Date(a.created_at);
                b = new Date(b.created_at);
                return a>b ? -1 : a<b ? 1 : 0;
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
                <div className="re-issues-inner">
                    {this.props.loader ? <WrapperLoader /> : null}
                    <div className="issues-list-title">Issues <span>{this.props.issues.length > 0? "("+this.props.issues.length+")" : ''}</span></div>
                    <div className="issues-list">
                        {issues}
                    </div>
                </div>

            </div>;
        }
        return "";

    }
}

export default ReviewExtendedIssuesContainer;
