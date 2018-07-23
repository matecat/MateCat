

let QUALITY_REPORT =  {

    getSegmentsFiles() {

        let data = {
            step: 30,
            segment: "756469",
        };
        return $.ajax({
            data: data,
            type: "GET",
            url : "/api/v2/jobs/"+ config.id_job +"/" + config.password + "/quality-report/segments"
        });
    },
    getSegmentsFiles2() {

        let data = {
            step: 30,
            segment: "756512",
        };
        return $.ajax({
            data: data,
            type: "GET",
            url : "/api/v2/jobs/"+ config.id_job +"/" + config.password + "/quality-report/segments"
        });
    },

    getUserData() {
        return $.getJSON('/api/app/user');
    }
}

export default QUALITY_REPORT ;