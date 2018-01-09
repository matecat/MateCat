let ReviewExtendedIssue =  require("./ReviewExtendedIssue").default;
class ReviewExtendedIssuesContainer extends React.Component {

    constructor(props) {
        super(props);
        this.state = {

        };

    }



    componentWillReceiveProps ( nextProps ) {

    }

    componentDidMount () {

    }

    componentWillUnmount () {

    }


    render () {

        let cs = classnames({
            'review-issues-container' : true,
        });
        let issues;

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
        else {
            issues = <div className="review-no-issues">No issues on this version</div>;
        }

        return <div className="re-issues">
			<h4>Issues <span>{this.props.issues.length > 0? "("+this.props.issues.length+")" : ''}</span></h4>
			<div className="issues-list">
				{issues}
			</div>
		</div>;

    }
}

export default ReviewExtendedIssuesContainer;
