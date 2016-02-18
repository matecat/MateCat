/*
  Component: ui.new
 */
$.extend(UI, {
  getIssues: function() {
    $.get('https://api.github.com/repos/matecat/matecat/issues')
      .done(function(data) {

        var issues = [];
        var i = 0;

        while (i < data.length && i < 5) { // Limit to 5 for now; change as needed
          issue = data[i];

          issues.push('<li class="issue"><a href="' + issue.html_url + '" target="_blank">' + issue.title + '</a></li>')

          i++;
        }

        $('#github-issues').html(issues);
      });
  },

  getClock: function() {

    function checkTime(i) {
      if (i < 10) {i = "0" + i};  // add zero in front of numbers < 10
      return i;
    }

    function startTime() {
        var today = new Date();
        var h = today.getHours();
        var m = today.getMinutes();
        var s = today.getSeconds();
        m = checkTime(m);
        s = checkTime(s);
        $('#clock').html(h + ":" + m + ":" + s);
        var t = setTimeout(startTime, 500);
    }

    startTime();
  }
});