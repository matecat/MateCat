

const CommonUtils = {

    millisecondsToTime(milli) {
        var seconds = Math.round((milli / 1000) % 60);
        var minutes = Math.floor((milli / (60 * 1000)) % 60);
        return [minutes, seconds];
    }
};

module.exports = CommonUtils;