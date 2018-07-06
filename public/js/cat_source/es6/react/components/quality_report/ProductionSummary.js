
class ProductionSummary extends React.Component {

    render () {

        return <div className="qr-production shadow-1">
            <div className="job-id">ID: 123458-8</div>
            <div className="source-to-target">
                <div className="qr-source"><b>Haitian Creole French</b></div>
                <div className="qr-to">
                    <i className="icon-chevron-right icon" />
                </div>
                <div className="qr-target"><b>Haitian Creole French</b></div>
            </div>
            <div className="progress-percent">
                <div className="progress-bar">
                    <div className="progr">
                        <div className="meter">
                            <a className="warning-bar translate-tooltip" data-variation="tiny" data-html="Rejected 0%" />
                            <a className="approved-bar translate-tooltip" data-variation="tiny" data-html="Approved 3%" />
                            <a className="translated-bar translate-tooltip" data-variation="tiny" data-html="Translated 88%" />
                            <a className="draft-bar translate-tooltip" data-variation="tiny" data-html="Draft 9%" />
                        </div>
                    </div>
                </div>
                <div className="percent">100%</div>
            </div>
            <div className="qr-effort">
                <div className="qr-label">Words</div>
                <div className="qr-info"><b>124,234</b></div>
            </div>
            <div className="qr-effort translator">
                <div className="qr-label">Translator</div>
                <div className="qr-info"><b>Silvia Corri</b></div>
            </div>
            <div className="qr-effort reviser">
                <div className="qr-label">Reviser</div>
                <div className="qr-info"><b>Naomi Lomartire</b></div>
            </div>
            <div className="qr-effort pee">
                <div className="qr-label">PEE</div>
                <div className="qr-info"><b>30%</b> <i className="icon-notice icon" /></div>
            </div>
            <div className="qr-effort time-edit">
                <div className="qr-label">Time Edit</div>
                <div className="qr-info"><b>30%</b> <i className="icon-notice icon" /></div>
            </div>
        </div>
    }
}

export default ProductionSummary ;