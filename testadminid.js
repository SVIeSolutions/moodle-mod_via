

function testAdminId(obj) {
    /// This function will open a popup window to test the server parameters for
    /// successful connection.
    if ((obj.s__via_apiurl.value.length == 0) || (obj.s__via_apiurl.value == '')) {
        alert("<?php print_string('configserverblank', 'elluminate'); ?>");
        return false;
    }

    var queryString = "";

    queryString += "apiurl=" + escape(obj.s__via_apiurl.value);
    queryString += "&cleid=" + escape(obj.s__via_cleid.value);
    queryString += "&apiid=" + escape(obj.s__via_apiid.value);
    queryString += "&adminid=" + escape(obj.s__moodle_adminid.value);

    return openpopup(null, { url: '/mod/via/testadminid.php?' + queryString, name: 'testadminid', options: 'scrollbars=yes,resizable=no,width=760,height=400' });
}