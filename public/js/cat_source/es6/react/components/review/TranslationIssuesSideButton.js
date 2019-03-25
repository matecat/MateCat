
let ReviewSideButton = require('../review_extended/ReviewExtendedTranslationIssuesSideButton').default;
class TranslationIssuesSideButton extends React.Component{

    render() {
        return <ReviewSideButton {...this.props}/>
    }
}

export default TranslationIssuesSideButton;
