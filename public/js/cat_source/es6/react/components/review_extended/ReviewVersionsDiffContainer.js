/**
 * React Component .

 */
let ReviewVersionDiff =  require("./ReviewVersionsDiff").default;
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
class ReviewVersionsDiffContainer extends React.Component {

    constructor(props) {
        super(props);
		this.state = {
			selectionObj: null,
            originalTranslation: this.props.segment.translation,
            translation: this.props.segment.translation,
			diffPatch: this.getDiffPatch(this.props.segment.translation, this.props.segment.translation)
		};
		this.props.updateDiffDataFn(this.state.diffPatch);
    }

	trackChanges(sid, editareaText) {
		let text = htmlEncode(UI.prepareTextToSend(editareaText));
		if (this.props.segment.sid === sid) {
			let newDiff = this.getDiffPatch(this.state.originalTranslation, text);
			this.props.updateDiffDataFn(newDiff);
            this.setState({
                translation: text,
                diffPatch: newDiff
            });
		}
	}

    setOriginalTranslation(sid, translation) {
        if (this.props.segment.sid == sid) {
            this.setState({
                originalTranslation: translation,
                diffPatch: this.getDiffPatch(translation, this.state.translation)
            });
            this.props.updateDiffDataFn(this.state.diffPatch);
        }
    }

	getDiffPatch(originalTranslation, newTranslation) {
        return getDiffPatch(originalTranslation, newTranslation);
	}

	componentWillReceiveProps(nextProps) {
        if ( this.props.segment.sid !== nextProps.segment.sid ) {
            this.originalTranslation = nextProps.segment.translation;
            let newDiff = this.getDiffPatch(nextProps.segment.translation, htmlEncode(UI.prepareTextToSend(nextProps.segment.translation)))
            this.setState({
                selectionObj: null,
                originalTranslation: nextProps.segment.translation,
                translation: nextProps.segment.translation,
                segment: nextProps.segment,
                diffPatch: newDiff
            });
            this.props.updateDiffDataFn(newDiff);
		}

	}

    componentDidMount() {
		SegmentStore.addListener(SegmentConstants.TRANSLATION_EDITED, this.trackChanges.bind(this));
		SegmentStore.addListener(SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION, this.setOriginalTranslation.bind(this));
    }

    componentWillUnmount() {
		SegmentStore.removeListener(SegmentConstants.TRANSLATION_EDITED, this.trackChanges);
		SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_ORIGINAL_TRANSLATION, this.setOriginalTranslation);
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