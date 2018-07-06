import AppDispatcher from '../dispatcher/AppDispatcher';
import QRApi from "../ajax_utils/quality_report/qrUtils";
import QRConstants from "./../constants/QualityReportConstants"
let QualityReportActions =  {

    loadInitialAjaxData() {
        QRApi.getSegmentsFiles().done(function ( response ) {
            if ( response.data.files ) {
                AppDispatcher.dispatch({
                    actionType: QRConstants.RENDER_SEGMENTS,
                    files: response.data.files,
                });
            }
        });
    }

}

export default QualityReportActions ;
