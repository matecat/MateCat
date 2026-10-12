import React from 'react'

import ReviewSideButton from '../review_extended/ReviewExtendedTranslationIssuesSideButton'

// A pass-through: props are forwarded whole rather than destructured, so the
// wrapper does not have to be edited whenever ReviewSideButton gains a prop.
const TranslationIssuesSideButton = (props) => <ReviewSideButton {...props} />

export default TranslationIssuesSideButton
