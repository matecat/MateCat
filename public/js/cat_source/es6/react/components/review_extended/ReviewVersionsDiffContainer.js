/**
 * React Component .

 */
let ReviewVersionDiff =  require("./ReviewVersionsDiff").default;
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
class ReviewVersionsDiffContainer extends React.Component {

    constructor(props) {
        super(props);
		this.originalTranslation = this.props.segment.translation;
		this.state = {
			selectionObj: null,
			sid: this.props.segment.sid,
			diffPatch: this.getDiffPatch(this.originalTranslation)
		};
    }

	trackChanges(sid, editareaText) {
		let text = htmlEncode(UI.prepareTextToSend(editareaText));
		if (this.props.segment.sid === sid) {
			let newDiff = this.getDiffPatch(editareaText)
			this.setState({
				diffPatch: newDiff
			});
		}
	}

	getDiffPatch(newTranslation) {
			return getDiffPatch(this.originalTranslation, newTranslation);
	}

	componentWillReceiveProps(nextProps) {
		this.originalTranslation = nextProps.segment.translation;
		/*this.originalTranslation = htmlEncode(UI.prepareTextToSend(nextProps.segment.translation));*/
		this.setState({
			selectionObj: null,
			sid: nextProps.segment.sid,
			diffPatch: this.getDiffPatch(this.originalTranslation)
		});
	}

    componentDidMount() {
		SegmentStore.addListener(SegmentConstants.TRANSLATION_EDITED, this.trackChanges.bind(this));
    }

    componentWillUnmount() {
		SegmentStore.removeListener(SegmentConstants.TRANSLATION_EDITED, this.trackChanges);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        return <div className="re-version-diff">
				<div className="re-header-track">
					<h4>Revise Track changes</h4>
					<ReviewVersionDiff
						textSelectedFn={this.props.textSelectedFn}
						removeSelection={this.props.removeSelection}
						diffPatch={this.state.diffPatch}
						segment={this.props.segment}
						decodeTextFn={UI.decodeText}
						selectable={this.props.selectable}
						customClass={'head'}
					/>

					{this.props.selectable? (
						<div className="explain-selection">
							Select a
							<div className="selected start-end">word</div>
							or
							<div className="selected start-end">more words</div>
							to create a specific inssue card
						</div>
					): (null)}
				</div>
			</div>
    }
}

export default ReviewVersionsDiffContainer;