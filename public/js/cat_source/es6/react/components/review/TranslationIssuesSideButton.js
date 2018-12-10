
let ReviewImprovedSideButton = require('../review_improved/ReviewImprovedTranslationIssuesSideButton').default;
let ReviewExtendedSideButton = require('../review_extended/ReviewExtendedTranslationIssuesSideButton').default;
class TranslationIssuesSideButton extends React.Component{

    render() {
        if ( this.props.reviewType === "extended" ) {
            return <ReviewExtendedSideButton {...this.props}/>
        } else {
            return <ReviewImprovedSideButton {...this.props}/>
        }
    }
}

export default TranslationIssuesSideButton;
