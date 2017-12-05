/**
 * React Component .

 */
let ReviewVersionDiff =  require("./ReviewVersionsDiff").default;
let React = require('react');
class ReviewVersionsDiffContainer extends React.Component {

    constructor(props) {
        super(props);
		this.originalTranslation = this.props.segment.translation;
		this.state = {
			translation: this.props.segment.translation,
			selectionObj: null,
			diffPatch: null,
		};
    }


    componentDidMount() {
    }

    componentWillUnmount() {
    }
    componentWillMount() {}

    componentDidUpdate() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return <div className="re-version-diff">
				<div className="re-header-track">
					<h4>Revise Track changes</h4>
					<div className="explain-selection">
						Select a
						<div className="selected start-end">word</div>
						or
						<div className="selected start-end">more words</div>
						to create a specific inssue card
					</div>
					<ReviewVersionDiff
						textSelectedFn={this.props.textSelectedFn}
						removeSelection={this.props.removeSelection}
						previousVersion={this.originalTranslation}
						translation={this.state.translation}
						segment={this.props.segment}
						decodeTextFn={UI.decodeText}
						selectable={this.props.isReview}
					/>
				</div>
			</div>
    }
}

export default ReviewVersionsDiffContainer;